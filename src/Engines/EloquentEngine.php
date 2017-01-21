<?php

namespace Yajra\Datatables\Engines;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
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
     * By default we do not select soft deleted records
     *
     * @var bool
     */
    protected $withTrashed = false;

    /**
     * Select only trashed records in count function for models with soft deletes trait.
     * By default we do not select soft deleted records
     *
     * @var bool
     */
    protected $onlyTrashed = false;

    /**
     * @param mixed $model
     * @param \Yajra\Datatables\Request $request
     */
    public function __construct($model, Request $request)
    {
        $builder = $model instanceof Builder ? $model : $model->getQuery();
        parent::__construct($builder->getQuery(), $request);

        $this->query      = $builder;
        $this->query_type = 'eloquent';
    }

    /**
     * Counts current query.
     *
     * @return int
     */
    public function count()
    {
        $myQuery = clone $this->query;
        // if its a normal query ( no union, having and distinct word )
        // replace the select with static text to improve performance
        if (! Str::contains(Str::lower($myQuery->toSql()), ['union', 'having', 'distinct', 'order by', 'group by'])) {
            $row_count = $this->wrap('row_count');
            $myQuery->select($this->connection->raw("'1' as {$row_count}"));
        }

        // check for select soft deleted records
        if (! $this->withTrashed && ! $this->onlyTrashed && $this->modelUseSoftDeletes()) {
            $myQuery->whereNull($myQuery->getModel()->getQualifiedDeletedAtColumn());
        }

        if ($this->onlyTrashed && $this->modelUseSoftDeletes()) {
            $myQuery->whereNotNull($myQuery->getModel()->getQualifiedDeletedAtColumn());
        }

        return $this->connection->table($this->connection->raw('(' . $myQuery->toSql() . ') count_row_table'))
                                ->setBindings($myQuery->getBindings())->count();
    }

    /**
     * Check if model use SoftDeletes trait
     *
     * @return boolean
     */
    protected function modelUseSoftDeletes()
    {
        if ($this->query_type == 'eloquent') {
            return in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->query->getModel()));
        }

        return false;
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
}
