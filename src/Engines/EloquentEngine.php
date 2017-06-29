<?php

namespace Yajra\Datatables\Engines;

use Illuminate\Database\Eloquent\Builder;
use Yajra\Datatables\Request;

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
     * @param mixed                     $model
     * @param \Yajra\Datatables\Request $request
     */
    public function __construct($model, Request $request)
    {
        $builder = $model instanceof Builder ? $model : $model->getQuery();
        parent::__construct($builder->getQuery(), $request);

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
}
