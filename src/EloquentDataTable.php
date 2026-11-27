<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as BaseQueryBuilder;
use Yajra\DataTables\Exceptions\Exception;

/**
 * @property EloquentBuilder $query
 */
class EloquentDataTable extends QueryDataTable
{
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
    protected function compileQuerySearch($query, string $column, string $keyword, string $boolean = 'or'): void
    {
        $parts = explode('.', $column);
        $attribute = array_pop($parts);
        if (count($parts) > 1) {
            $rootTable = $this->matchingTableName($this->query->getQuery(), implode('.', $parts));
            if ($rootTable !== null) {
                parent::compileQuerySearch($query, $rootTable.'.'.$attribute, $keyword, $boolean);

                return;
            }
        }

        [$relation, $qualifier] = $this->splitSearchRelation($parts);

        if ($relation === null) {
            parent::compileQuerySearch($query, $column, $keyword, $boolean);

            return;
        }

        $parts = explode('.', $relation);
        $filter = function (EloquentBuilder $query) use ($qualifier, $attribute, $keyword) {
            if ($qualifier !== null) {
                $table = $this->matchingTableName($query->getQuery(), $qualifier);
                if ($table === null) {
                    // A different polymorphic type cannot match this table's column.
                    $query->whereRaw('1 = 0');

                    return;
                }

                $attribute = $table.'.'.$attribute;
            }

            parent::compileQuerySearch($query, $attribute, $keyword, '');
        };

        // Each morph type must traverse the same remaining relations independently.
        while (count($parts) > 1) {
            $nestedRelation = array_pop($parts);
            $filter = static fn (EloquentBuilder $query) => $query->whereHas($nestedRelation, $filter);
        }

        $query->{$boolean.'WhereHas'}($parts[0], $filter);
    }

    /**
     * Match the qualifier to the FROM table and return the name used in SQL.
     */
    private function matchingTableName(BaseQueryBuilder $query, string $qualifier): ?string
    {
        $from = $query->from;
        if (! is_string($from) || $qualifier === '') {
            return null;
        }

        $names = preg_split('/\s+as\s+/i', $from, 2) ?: [$from];
        $table = $names[0];
        $alias = $names[1] ?? $table;
        $sqlName = $alias;

        $connection = $query->getConnection();
        if ($connection instanceof Connection && $connection->getDriverName() === 'sqlite') {
            $qualifier = strtolower($qualifier);
            $table = strtolower($table);
            $alias = strtolower($alias);
        }

        return $qualifier === $alias || $qualifier === $table || str_ends_with($table, '.'.$qualifier) ? $sqlName : null;
    }

    /**
     * Prefer the longest configured relationship. Any remaining segments are a SQL table qualifier.
     *
     * @param  list<string>  $parts
     * @return array{?string, ?string}
     */
    private function splitSearchRelation(array $parts): array
    {
        if ($parts === []) {
            return [null, null];
        }

        $length = count($parts);
        $depths = [$length => true];
        foreach (array_keys($this->query->getEagerLoads()) as $path) {
            $depth = substr_count((string) $path, '.') + 1;
            if ($depth < $length) {
                $depths[$depth] = true;
            }
        }

        krsort($depths);
        foreach (array_keys($depths) as $depth) {
            $relation = implode('.', array_slice($parts, 0, $depth));
            if (! $this->isNotEagerLoaded($relation)) {
                return [$relation, $depth === $length ? null : implode('.', array_slice($parts, $depth))];
            }
        }

        return [null, null];
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
     * Resolve the proper column name be used.
     *
     *
     * @throws Exception
     */
    protected function resolveRelationColumn(string $column): string
    {
        $parts = explode('.', $column);
        $columnName = array_pop($parts);
        $relation = implode('.', $parts);

        if ($this->isNotEagerLoaded($relation)) {
            return $column;
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
        $table = '';
        $lastQuery = $this->query;
        foreach (explode('.', $relation) as $eachRelation) {
            $model = $lastQuery->getRelation($eachRelation);
            switch (true) {
                case $model instanceof BelongsToMany:
                    $pivot = $model->getTable();
                    $pivotPK = $model->getExistenceCompareKey();
                    $pivotFK = $model->getQualifiedParentKeyName();
                    $this->performJoin($pivot, $pivotPK, $pivotFK);

                    $related = $model->getRelated();
                    $table = $related->getTable();
                    $tablePK = $model->getRelatedPivotKeyName();
                    $foreign = $pivot.'.'.$tablePK;
                    $other = $related->getQualifiedKeyName();

                    $lastQuery->addSelect($table.'.'.$relationColumn);
                    $this->performJoin($table, $foreign, $other);

                    break;

                case $model instanceof HasOneThrough:
                    $pivot = explode('.', $model->getQualifiedParentKeyName())[0]; // extract pivot table from key
                    $pivotPK = $pivot.'.'.$model->getFirstKeyName();
                    $pivotFK = $model->getQualifiedLocalKeyName();
                    $this->performJoin($pivot, $pivotPK, $pivotFK);

                    $related = $model->getRelated();
                    $table = $related->getTable();
                    $tablePK = $model->getSecondLocalKeyName();
                    $foreign = $pivot.'.'.$tablePK;
                    $other = $related->getQualifiedKeyName();

                    $lastQuery->addSelect($lastQuery->getModel()->getTable().'.*');

                    break;

                case $model instanceof HasOneOrMany:
                    $table = $model->getRelated()->getTable();
                    $foreign = $model->getQualifiedForeignKeyName();
                    $other = $model->getQualifiedParentKeyName();
                    break;

                case $model instanceof BelongsTo:
                    $table = $model->getRelated()->getTable();
                    $foreign = $model->getQualifiedForeignKeyName();
                    $other = $model->getQualifiedOwnerKeyName();
                    break;

                default:
                    throw new Exception('Relation '.$model::class.' is not yet supported.');
            }
            $this->performJoin($table, $foreign, $other);
            $lastQuery = $model->getQuery();
        }

        return $table.'.'.$relationColumn;
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
        $joins = [];
        $builder = $this->getBaseQueryBuilder();
        foreach ($builder->joins ?? [] as $join) {
            $joins[] = $join->table;
        }

        if (! in_array($table, $joins)) {
            $this->getBaseQueryBuilder()->join($table, $foreign, '=', $other, $type);
        }
    }
}
