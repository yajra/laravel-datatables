<?php

namespace Yajra\Datatables\Engines;

/**
 * Laravel Datatables Moloquent Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Navid Sadeghieh <navid@sadeghieh.ir>
 */

use Jenssegers\Mongodb\Eloquent\Builder;
use Yajra\Datatables\Contracts\DataTableEngine;
use Yajra\Datatables\Request;

class MoloquentEngine extends QueryBuilderEngine implements DataTableEngine
{

    /**
     * @param mixed $model
     * @param \yajra\Datatables\Request $request
     */
    public function __construct($model, Request $request)
    {
        $this->query = $model instanceof Builder ? $model : $model->getQuery();
        $this->init($request, $this->query->getQuery(), 'moloquent');
    }

    /**
     * Counts current query.
     *
     * @return int
     */
    public function count()
    {
        $myQuery = clone $this->query;
        return $myQuery->count();
    }
}
