<?php

namespace Rafaelqm\Datatables\Engines;

/**
 * Laravel Datatables Eloquent Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Database\Eloquent\Builder;
use Rafaelqm\Datatables\Contracts\DataTableEngineContract;
use Rafaelqm\Datatables\Request;

class EloquentEngine extends QueryBuilderEngine implements DataTableEngineContract
{
    /**
     * @param mixed $model
     * @param \Rafaelqm\Datatables\Request $request
     */
    public function __construct($model, Request $request)
    {
        $this->query = $model instanceof Builder ? $model : $model->getQuery();
        $this->init($request, $this->query->getQuery(), 'eloquent');
    }
}
