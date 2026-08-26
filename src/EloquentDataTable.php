<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Yajra\DataTables\Exceptions\Exception;

/**
 * @property EloquentBuilder $query
 */
class EloquentDataTable extends QueryDataTable
{
    /**
     * Flag to enable the generation of unique table aliases on eagerly loaded join columns.
     * You may want to enable it if you encounter a "Not unique table/alias" error when performing a search or applying ordering.
     */
    protected bool $enableEagerJoinAliases = false;

    /**
     * EloquentEngine constructor.
     */
    public function __construct(Model|EloquentBuilder $model)
    {
        $builder = match (true) {
            $model instanceof Model => $model->newQuery(),
            $model instanceof Relation => $model->getQuery(),
            $model instanceof EloquentBuilder => $model,
        };

        parent::__construct($builder->getQuery());

        $this->query = $builder;
    }

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param  mixed  $source
     */
    public static function canCreate($source): bool
    {
        return $source instanceof EloquentBuilder;
    }

    /**
     * Add columns in collection.
     *
     * @param  bool|int  $order
     * @return $this
     */
    public function addColumns(array $names, $order = false)
    {
        foreach ($names as $name => $attribute) {
            if (is_int($name)) {
                $name = $attribute;
            }

            $this->addColumn($name, fn ($model) => $model->getAttribute($attribute), is_int($order) ? $order++ : $order);
        }

        return $this;
    }

    /**
     * If column name could not be resolved then use primary key.
     */
    protected function getPrimaryKeyName(): string
    {
        return $this->query->getModel()->getKeyName();
    }

    /**
     * {@inheritDoc}
     */
    protected function compileQuerySearch($query, string $column, string $keyword, string $boolean = 'or', bool $nested = false): void
    {
        $relation = $this->resolveSearchableRelation($query, $column, $nested);

        if (! $relation) {
            parent::compileQuerySearch($query, $column, $keyword, $boolean);

            return;
        }

        $this->compileRelationSearch($query, $relation['relation'], [$relation['column']], $keyword, $boolean);
    }

    /**
     * Resolve the relation that should be searched for the given column.
     *
     * Returns null when the column can be searched on the query itself,
     * e.g. when its relation is not eager loaded and is therefore joined.
     *
     * @param  QueryBuilder|EloquentBuilder  $query
     * @return array{relation: string, column: string}|null
     */
    protected function resolveSearchableRelation($query, string $column, bool $nested = false): ?array
    {
        $parts = explode('.', $column);

        if (count($parts) > 2) {
            if ($this->isTableQualifiedColumn($query, $column)) {
                return null;
            }

            $relation = array_shift($parts);

            return ['relation' => $relation, 'column' => implode('.', $parts)];
        }

        $columnName = array_pop($parts);
        $relation = implode('.', $parts);

        if (! $relation || (! $nested && $this->isNotEagerLoaded($relation))) {
            return null;
        }

        return ['relation' => $relation, 'column' => $columnName];
    }

    /**
     * Compile a single relation search query for the given related columns.
     *
     * All columns of the same relation are compiled into one where has query
     * so a single sub-query is used instead of one per column.
     *
     * @param  QueryBuilder|EloquentBuilder  $query
     * @param  array<int, string>  $columns
     */
    protected function compileRelationSearch($query, string $relation, array $columns, string $keyword, string $boolean = 'or'): void
    {
        $isMorph = $this->isMorphRelation($relation);

        $search = function (EloquentBuilder $query) use ($columns, $keyword, $isMorph) {
            foreach (array_values($columns) as $index => $column) {
                $boolean = $index === 0 ? '' : 'or';

                if (! $isMorph && str_contains($column, '.')) {
                    self::compileQuerySearch($query, $column, $keyword, $boolean, true);
                } else {
                    parent::compileQuerySearch($query, $column, $keyword, $boolean);
                }
            }
        };

        // A single column does not need to be grouped as it cannot be
        // mixed up with the relation constraint of the where has query.
        $callback = count($columns) > 1
            ? fn (EloquentBuilder $query) => $query->where($search)
            : $search;

        if ($isMorph) {
            $query->{$boolean.'WhereHasMorph'}($relation, '*', $callback);
        } else {
            $query->{$boolean.'WhereHas'}($relation, $callback);
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function compileGlobalSearch($query, Collection $columns, string $keyword): void
    {
        /** @var array<int, string|null> $relations */
        $relations = [];

        /** @var array<int, array<int, string>> $groups */
        $groups = [];

        foreach ($columns as $column) {
            $relation = $this->hasFilterColumn($column)
                ? null
                : $this->resolveSearchableRelation($query, $column);

            if (! $relation) {
                $relations[] = null;
                $groups[] = [$column];

                continue;
            }

            $index = array_search($relation['relation'], $relations, true);

            if ($index === false) {
                $relations[] = $relation['relation'];
                $groups[] = [$relation['column']];
            } else {
                $groups[$index][] = $relation['column'];
            }
        }

        foreach ($relations as $index => $relation) {
            if ($relation === null) {
                $this->compileGlobalSearchColumn($query, $groups[$index][0], $keyword);
            } else {
                $this->compileRelationSearch($query, $relation, $groups[$index], $keyword);
            }
        }
    }

    /**
     * Check if a relation was not used on eager loading.
     *
     * @param  string  $relation
     * @return bool
     */
    protected function isNotEagerLoaded($relation)
    {
        return ! $relation
            || ! array_key_exists($relation, $this->query->getEagerLoads())
            || $relation === $this->query->getModel()->getTable();
    }

    /**
     * Check if a relation is a morphed one or not.
     *
     * @param  string  $relation
     * @return bool
     */
    protected function isMorphRelation($relation)
    {
        $isMorph = false;
        if ($relation !== null && $relation !== '') {
            $relationParts = explode('.', $relation);
            $firstRelation = array_shift($relationParts);
            $model = $this->query->getModel();
            $isMorph = method_exists($model, $firstRelation) && $model->$firstRelation() instanceof MorphTo;
        }

        return $isMorph;
    }

    /**
     * Check if a column is already prefixed by the current schema-qualified table.
     */
    protected function isTableQualifiedColumn(QueryBuilder|EloquentBuilder $query, string $column): bool
    {
        $table = $this->getTablePrefix($query);

        return is_string($table)
            && str_contains($table, '.')
            && str_starts_with($column, $table.'.');
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception
     */
    protected function resolveRelationColumn(string $column): string
    {
        $parts = explode('.', $column);
        $columnName = array_pop($parts);
        $relation = preg_replace('/\[.*?\]/', '', implode('.', $parts));

        if ($this->isNotEagerLoaded($relation)) {
            return parent::resolveRelationColumn($column);
        }

        return $this->joinEagerLoadedColumn($relation, $columnName);
    }

    /**
     * Join eager loaded relation and get the related column name.
     *
     * @param  string  $relation
     * @param  string  $relationColumn
     * @return string
     *
     * @throws Exception
     */
    protected function joinEagerLoadedColumn($relation, $relationColumn)
    {
        $tableAlias = $pivotAlias = '';
        $lastQuery = $this->query;
        foreach (explode('.', $relation) as $eachRelation) {
            $model = $lastQuery->getRelation($eachRelation);
            if ($this->enableEagerJoinAliases) {
                $lastAlias = $tableAlias ?: $this->getTablePrefix($lastQuery);
                $tableAlias = $tableAlias.'_'.$eachRelation;
                $pivotAlias = $tableAlias.'_pivot';
            } else {
                $lastAlias = $tableAlias ?: $lastQuery->getModel()->getTable();
            }
            switch (true) {
                case $model instanceof BelongsToMany:
                    if ($this->enableEagerJoinAliases) {
                        $pivot = $model->getTable().' as '.$pivotAlias;
                    } else {
                        $pivot = $pivotAlias = $model->getTable();
                    }
                    $pivotPK = $pivotAlias.'.'.$model->getForeignPivotKeyName();
                    $pivotFK = ltrim($lastAlias.'.'.$model->getParentKeyName(), '.');
                    $this->performJoin($pivot, $pivotPK, $pivotFK);

                    $related = $model->getRelated();
                    if ($this->enableEagerJoinAliases) {
                        $table = $related->getTable().' as '.$tableAlias;
                    } else {
                        $table = $tableAlias = $related->getTable();
                    }
                    $tablePK = $model->getRelatedPivotKeyName();
                    $foreign = $pivotAlias.'.'.$tablePK;
                    $other = $tableAlias.'.'.$related->getKeyName();

                    $lastQuery->addSelect($tableAlias.'.'.$relationColumn);

                    break;

                case $model instanceof HasOneThrough:
                    if ($this->enableEagerJoinAliases) {
                        $pivot = explode('.', $model->getQualifiedParentKeyName())[0].' as '.$pivotAlias;
                    } else {
                        $pivot = $pivotAlias = explode('.', $model->getQualifiedParentKeyName())[0];
                    }
                    $pivotPK = $pivotAlias.'.'.$model->getFirstKeyName();
                    $pivotFK = ltrim($lastAlias.'.'.$model->getLocalKeyName(), '.');
                    $this->performJoin($pivot, $pivotPK, $pivotFK);

                    $related = $model->getRelated();
                    if ($this->enableEagerJoinAliases) {
                        $table = $related->getTable().' as '.$tableAlias;
                    } else {
                        $table = $tableAlias = $related->getTable();
                    }
                    $tablePK = $model->getSecondLocalKeyName();
                    $foreign = $pivotAlias.'.'.$tablePK;
                    $other = $tableAlias.'.'.$related->getKeyName();

                    $lastQuery->addSelect($lastQuery->getModel()->getTable().'.*');

                    break;

                case $model instanceof HasOneOrMany:
                    if ($this->enableEagerJoinAliases) {
                        $table = $model->getRelated()->getTable().' as '.$tableAlias;
                    } else {
                        $table = $tableAlias = $model->getRelated()->getTable();
                    }
                    $foreign = $tableAlias.'.'.$model->getForeignKeyName();
                    $other = ltrim($lastAlias.'.'.$model->getLocalKeyName(), '.');
                    break;

                case $model instanceof BelongsTo:
                    if ($this->enableEagerJoinAliases) {
                        $table = $model->getRelated()->getTable().' as '.$tableAlias;
                    } else {
                        $table = $tableAlias = $model->getRelated()->getTable();
                    }
                    $foreign = ltrim($lastAlias.'.'.$model->getForeignKeyName(), '.');
                    $other = $tableAlias.'.'.$model->getOwnerKeyName();
                    break;

                default:
                    throw new Exception('Relation '.$model::class.' is not yet supported.');
            }
            $this->performRelationJoin($model, $table, $tableAlias, $foreign, $other);
            $lastQuery = $model->getQuery();
        }

        return $tableAlias.'.'.$relationColumn;
    }

    /**
     * Enable the generation of unique table aliases on eagerly loaded join columns.
     * You may want to enable it if you encounter a "Not unique table/alias" error when performing a search or applying ordering.
     *
     * @return $this
     */
    public function enableEagerJoinAliases(): static
    {
        $this->enableEagerJoinAliases = true;

        return $this;
    }

    /**
     * Perform join query.
     *
     * @param  string  $table
     * @param  string  $foreign
     * @param  string  $other
     * @param  string  $type
     */
    protected function performJoin($table, $foreign, $other, $type = 'left'): void
    {
        if ($this->isJoined($table)) {
            return;
        }

        $this->getBaseQueryBuilder()->join($table, $foreign, '=', $other, $type);
    }

    /**
     * Perform the join of a relation, keeping the constraints it was declared with.
     *
     * A relation like hasOne(Translation::class)->where('lang', 'en') would
     * otherwise be joined on its keys only, returning the rows of every language.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     */
    protected function performRelationJoin(
        Relation $relation,
        string $table,
        string $alias,
        string $foreign,
        string $other,
        string $type = 'left'
    ): void {
        $constraints = $this->getRelationConstraints($relation, $alias);

        if (! $constraints) {
            $this->performJoin($table, $foreign, $other, $type);

            return;
        }

        if ($this->isJoined($table)) {
            return;
        }

        $this->getBaseQueryBuilder()->join(
            $table,
            function (JoinClause $join) use ($foreign, $other, $constraints) {
                $join->on($foreign, '=', $other)
                    ->mergeWheres($constraints['wheres'], $constraints['bindings']);
            },
            null,
            null,
            $type
        );
    }

    /**
     * Get the constraints a relation was declared with, if any.
     *
     * The relation is resolved without constraints, so its query only holds the
     * conditions of the relation itself and not the ones on the related keys.
     *
     * @param  Relation<Model, Model, mixed>  $relation
     * @return array{wheres: array, bindings: array}|null
     */
    protected function getRelationConstraints(Relation $relation, string $alias): ?array
    {
        $query = $relation->getQuery()->getQuery();

        if (empty($query->wheres)) {
            return null;
        }

        return [
            'wheres' => $this->qualifyRelationWheres($query->wheres, $alias),
            'bindings' => $query->getRawBindings()['where'] ?? [],
        ];
    }

    /**
     * Qualify the columns of the given wheres with the table of the joined relation.
     */
    protected function qualifyRelationWheres(array $wheres, string $alias): array
    {
        foreach ($wheres as $index => $where) {
            $nested = $where['query'] ?? null;

            if (($where['type'] ?? null) === 'Nested' && $nested instanceof BaseQueryBuilder) {
                $nested = clone $nested;
                $nested->wheres = $this->qualifyRelationWheres($nested->wheres, $alias);
                $wheres[$index]['query'] = $nested;

                continue;
            }

            $column = $where['column'] ?? null;

            if (is_string($column) && ! str_contains($column, '.')) {
                $wheres[$index]['column'] = $alias.'.'.$column;
            }
        }

        return $wheres;
    }

    /**
     * Check if the given table is already joined.
     */
    protected function isJoined(string $table): bool
    {
        $joins = [];
        foreach ($this->getBaseQueryBuilder()->joins ?? [] as $join) {
            $joins[] = $join->table;
        }

        return in_array($table, $joins);
    }
}
