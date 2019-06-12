<?php

namespace Yajra\DataTables;

use Illuminate\Database\Eloquent\Builder;
use Yajra\DataTables\Exceptions\Exception;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class EloquentDataTable extends QueryDataTable
{
    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $query;

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param mixed $source
     * @return bool
     */
    public static function canCreate($source)
    {
        return $source instanceof Builder || $source instanceof Relation;
    }

    /**
     * EloquentEngine constructor.
     *
     * @param mixed $model
     */
    public function __construct($model)
    {
        $builder = $model instanceof Builder ? $model : $model->getQuery();
        parent::__construct($builder->getQuery());

        $this->query = $builder;
    }

    /**
     * Add columns in collection.
     *
     * @param  array  $names
     * @param  bool|int  $order
     * @return $this
     */
    public function addColumns(array $names, $order = false)
    {
        foreach ($names as $name => $attribute) {
            if (is_int($name)) {
                $name = $attribute;
            }

            $this->addColumn($name, function ($model) use ($attribute) {
                return $model->getAttribute($attribute);
            }, is_int($order) ? $order++ : $order);
        }

        return $this;
    }

    /**
     * If column name could not be resolved then use primary key.
     *
     * @return string
     */
    protected function getPrimaryKeyName()
    {
        return $this->query->getModel()->getKeyName();
    }

    /**
     * Compile query builder where clause depending on configurations.
     *
     * @param mixed  $query
     * @param string $columnName
     * @param string $keyword
     * @param string $boolean
     */
    protected function compileQuerySearch($query, $columnName, $keyword, $boolean = 'or')
    {
        $parts    = explode('.', $columnName);
        $column   = array_pop($parts);
        $relation = implode('.', $parts);

        if ($this->isNotEagerLoaded($relation)) {
            return parent::compileQuerySearch($query, $columnName, $keyword, $boolean);
        }

        $query->{$boolean . 'WhereHas'}($relation, function (Builder $query) use ($column, $keyword) {
            parent::compileQuerySearch($query, $column, $keyword, '');
        });
    }

    /**
     * Resolve the proper column name be used.
     *
     * @param string $column
     * @return string
     */
    protected function resolveRelationColumn($column)
    {
        $parts      = explode('.', $column);
        $columnName = array_pop($parts);
        $relation   = implode('.', $parts);

        if ($this->isNotEagerLoaded($relation)) {
            return $column;
        }

        return $this->joinEagerLoadedColumn($relation, $columnName);
    }

    /**
     * Check if a relation was not used on eager loading.
     *
     * @param  string $relation
     * @return bool
     */
    protected function isNotEagerLoaded($relation)
    {
        return ! $relation
            || ! array_key_exists($relation, $this->query->getEagerLoads())
            || $relation === $this->query->getModel()->getTable();
    }

    /**
     * Join eager loaded relation and get the related column name.
     *
     * @param string $relation
     * @param string $relationColumn
     * @return string
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    protected function joinEagerLoadedColumn($relation, $relationColumn)
    {
        $table     = '';
        $lastQuery = $this->query;
        foreach (explode('.', $relation) as $eachRelation) {
            $model = $lastQuery->getRelation($eachRelation);
            switch (true) {
                case $model instanceof BelongsToMany:
                    $pivot   = $model->getTable();
                    $pivotPK = $model->getExistenceCompareKey();
                    $pivotFK = $model->getQualifiedParentKeyName();
                    $this->performJoin($pivot, $pivotPK, $pivotFK);

                    $related = $model->getRelated();
                    $table   = $related->getTable();
                    $tablePK = $related->getForeignKey();
                    $foreign = $pivot . '.' . $tablePK;
                    $other   = $related->getQualifiedKeyName();

                    $lastQuery->addSelect($table . '.' . $relationColumn);
                    $this->performJoin($table, $foreign, $other);

                    break;

                case $model instanceof HasOneOrMany:
                    $table     = $model->getRelated()->getTable();
                    $foreign   = $model->getQualifiedForeignKeyName();
                    $other     = $model->getQualifiedParentKeyName();
                    break;

                case $model instanceof BelongsTo:
                    $table     = $model->getRelated()->getTable();
                    $foreign   = $model->getQualifiedForeignKeyName();
                    $other     = $model->getQualifiedOwnerKeyName();
                    break;

                default:
                    throw new Exception('Relation ' . get_class($model) . ' is not yet supported.');
            }
            $this->performJoin($table, $foreign, $other);
            $lastQuery = $model->getQuery();
        }

        return $table . '.' . $relationColumn;
    }

    /**
     * Perform join query.
     *
     * @param string $table
     * @param string $foreign
     * @param string $other
     * @param string $type
     */
    protected function performJoin($table, $foreign, $other, $type = 'left')
    {
        $joins = [];
        foreach ((array) $this->getBaseQueryBuilder()->joins as $key => $join) {
            $joins[] = $join->table;
        }

        if (! in_array($table, $joins)) {
            $this->getBaseQueryBuilder()->join($table, $foreign, '=', $other, $type);
        }
    }
}
