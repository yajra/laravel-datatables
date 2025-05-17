<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
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
        if (substr_count($column, '.') > 1) {
            $parts = explode('.', $column);
            $firstRelation = array_shift($parts);
            $column = implode('.', $parts);

            if ($this->isMorphRelation($firstRelation)) {
                $query->{$boolean.'WhereHasMorph'}(
                    $firstRelation,
                    '*',
                    function (EloquentBuilder $query) use ($column, $keyword) {
                        parent::compileQuerySearch($query, $column, $keyword, '');
                    }
                );
            } else {
                $query->{$boolean.'WhereHas'}($firstRelation, function (EloquentBuilder $query) use ($column, $keyword) {
                    self::compileQuerySearch($query, $column, $keyword, '', true);
                });
            }

            return;
        }

        $parts = explode('.', $column);
        $newColumn = array_pop($parts);
        $relation = implode('.', $parts);

        if (! $nested && $this->isNotEagerLoaded($relation)) {
            parent::compileQuerySearch($query, $column, $keyword, $boolean);

            return;
        }

        if ($this->isMorphRelation($relation)) {
            $query->{$boolean.'WhereHasMorph'}(
                $relation,
                '*',
                function (EloquentBuilder $query) use ($newColumn, $keyword) {
                    parent::compileQuerySearch($query, $newColumn, $keyword, '');
                }
            );
        } else {
            $query->{$boolean.'WhereHas'}($relation, function (EloquentBuilder $query) use ($newColumn, $keyword) {
                parent::compileQuerySearch($query, $newColumn, $keyword, '');
            });
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
     * {@inheritDoc}
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    protected function resolveRelationColumn(string $column): string
    {
        $parts = explode('.', $column);
        $columnName = array_pop($parts);
        $relation = str_replace('[]', '', implode('.', $parts));

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
     * @throws \Yajra\DataTables\Exceptions\Exception
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
            $this->performJoin($table, $foreign, $other);
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
