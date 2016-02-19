<?php

namespace Yajra\Datatables\Engines;

/**
 * Laravel Datatables Eloquent Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Database\Eloquent\Builder;
use Yajra\Datatables\Request;

class EloquentEngine extends QueryBuilderEngine
{
    /**
     * @param mixed $model
     * @param \Yajra\Datatables\Request $request
     */
    public function __construct($model, Request $request)
    {
        $this->query = $model instanceof Builder ? $model : $model->getQuery();
        $this->init($request, $this->query->getQuery(), 'eloquent');
    }
}
