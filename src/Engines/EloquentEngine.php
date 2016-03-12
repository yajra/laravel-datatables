<?php

namespace Yajra\Datatables\Engines;

use Illuminate\Database\Eloquent\Builder;
use Yajra\Datatables\Request;

/**
 * Laravel Datatables Eloquent Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */
class EloquentEngine extends QueryBuilderEngine
{
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
}
