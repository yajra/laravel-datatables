<?php

namespace Yajra\Datatables\Engines;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class EloquentEngine.
 *
 * @package Yajra\Datatables\Engines
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
class EloquentEngine extends QueryBuilderEngine
{
    /**
     * Select trashed records in count function for models with soft deletes trait.
     * By default we do not select soft deleted records.
     *
     * @var bool
     */
    protected $withTrashed = false;

    /**
     * Select only trashed records in count function for models with soft deletes trait.
     * By default we do not select soft deleted records.
     *
     * @var bool
     */
    protected $onlyTrashed = false;

    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $query;

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
     * Counts current query.
     *
     * @return int
     */
    public function count()
    {
        $builder = $this->prepareCountQuery();

        if ($this->isSoftDeleting()) {
            $builder->whereNull($builder->getModel()->getQualifiedDeletedAtColumn());
        }

        if ($this->isOnlyTrashed()) {
            $builder->whereNotNull($builder->getModel()->getQualifiedDeletedAtColumn());
        }

        $table = $this->connection->raw('(' . $builder->toSql() . ') count_row_table');

        return $this->connection->table($table)
                                ->setBindings($builder->getBindings())
                                ->count();
    }

    /**
     * Check if engine uses soft deletes.
     *
     * @return bool
     */
    private function isSoftDeleting()
    {
        return !$this->withTrashed && !$this->onlyTrashed && $this->modelUseSoftDeletes();
    }

    /**
     * Check if model use SoftDeletes trait.
     *
     * @return boolean
     */
    private function modelUseSoftDeletes()
    {
        return in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->query->getModel()));
    }

    /**
     * Check if engine uses only trashed.
     *
     * @return bool
     */
    private function isOnlyTrashed()
    {
        return $this->onlyTrashed && $this->modelUseSoftDeletes();
    }

    /**
     * Change withTrashed flag value.
     *
     * @param bool $withTrashed
     * @return $this
     */
    public function withTrashed($withTrashed = true)
    {
        $this->withTrashed = $withTrashed;

        return $this;
    }

    /**
     * Change onlyTrashed flag value.
     *
     * @param bool $onlyTrashed
     * @return $this
     */
    public function onlyTrashed($onlyTrashed = true)
    {
        $this->onlyTrashed = $onlyTrashed;

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
     * Perform global search for the given keyword.
     *
     * @param string $keyword
     */
    protected function globalSearch($keyword)
    {
        $this->query->where(function ($query) use ($keyword) {
            $query = $this->getBaseQueryBuilder($query);

            foreach ($this->request->searchableColumnIndex() as $index) {
                $columnName = $this->getColumnName($index);
                if ($this->isBlacklisted($columnName) && !$this->hasCustomFilter($columnName)) {
                    continue;
                }

                if ($this->hasCustomFilter($columnName)) {
                    $this->applyFilterColumn($query, $columnName, $keyword);
                } else {
                    if (count(explode('.', $columnName)) > 1) {
                        $this->eagerLoadSearch($query, $columnName, $keyword);
                    } else {
                        $this->compileQuerySearch($query, $columnName, $keyword);
                    }
                }

                $this->isFilterApplied = true;
            }
        });
    }

    /**
     * Perform search on eager loaded relation column.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string                                $columnName
     * @param string                                $keyword
     */
    private function eagerLoadSearch($query, $columnName, $keyword)
    {
        $eagerLoads     = $this->getEagerLoads();
        $parts          = explode('.', $columnName);
        $relationColumn = array_pop($parts);
        $relation       = implode('.', $parts);
        if (in_array($relation, $eagerLoads)) {
            $this->compileRelationSearch(
                $query,
                $relation,
                $relationColumn,
                $keyword
            );
        } else {
            $this->compileQuerySearch($query, $columnName, $keyword);
        }
    }

    /**
     * Add relation query on global search.
     *
     * @param Builder $query
     * @param string  $relation
     * @param string  $column
     * @param string  $keyword
     */
    private function compileRelationSearch($query, $relation, $column, $keyword)
    {
        $myQuery = clone $this->query;

        /**
         * For compile nested relation, we need store all nested relation as array
         * and reverse order to apply where query.
         * With this method we can create nested sub query with properly relation.
         */

        /**
         * Store all relation data that require in next step
         */
        $relationChunk = [];

        /**
         * Store last eloquent query builder for get next relation.
         */
        $lastQuery = $query;

        $relations    = explode('.', $relation);
        $lastRelation = end($relations);
        foreach ($relations as $relation) {
            $relationType = $myQuery->getModel()->{$relation}();
            $myQuery->orWhereHas($relation, function ($builder) use (
                $column,
                $keyword,
                $query,
                $relationType,
                $relation,
                $lastRelation,
                &$relationChunk,
                &$lastQuery
            ) {
                $builder->select($this->connection->raw('count(1)'));

                // We will perform search on last relation only.
                if ($relation == $lastRelation) {
                    $this->compileQuerySearch($builder, $column, $keyword, '');
                }

                // Put require object to next step!!
                $relationChunk[$relation] = [
                    'builder'      => $builder,
                    'relationType' => $relationType,
                    'query'        => $lastQuery,
                ];

                // This is trick make sub query.
                $lastQuery = $builder;
            });

            // This is trick to make nested relation by pass previous relation to be next query eloquent builder
            $myQuery = $relationType;
        }

        /**
         * Reverse them all
         */
        $relationChunk = array_reverse($relationChunk, true);

        /**
         * Create valuable for use in check last relation
         */
        end($relationChunk);
        $lastRelation = key($relationChunk);
        reset($relationChunk);

        /**
         * Walking ...
         */
        foreach ($relationChunk as $relation => $chunk) {
            /** @var Builder $builder */
            $builder  = $chunk['builder'];
            $query    = $chunk['query'];
            $bindings = $builder->getBindings();
            $builder  = "({$builder->toSql()}) >= 1";

            // Check if it last relation we will use orWhereRaw
            if ($lastRelation == $relation) {
                $relationMethod = "orWhereRaw";
            } else {
                // For case parent relation of nested relation.
                // We must use and for properly query and get correct result
                $relationMethod = "whereRaw";
            }

            $query->{$relationMethod}($builder, $bindings);
        }
    }
}
