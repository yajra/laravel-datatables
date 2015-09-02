<?php

namespace yajra\Datatables\Engines;

/**
 * Laravel Datatables Eloquent Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Database\Eloquent\Builder;
use yajra\Datatables\Contracts\DataTableEngine;
use yajra\Datatables\Request;

class EloquentEngine extends QueryBuilderEngine implements DataTableEngine
{

    /**
     * @param mixed $model
     * @param \yajra\Datatables\Request $request
     */
    public function __construct($model, Request $request)
    {
        $this->query = $model instanceof Builder ? $model : $model->getQuery();
        $this->init($request, $this->query->getQuery(), 'eloquent');
    }
}
