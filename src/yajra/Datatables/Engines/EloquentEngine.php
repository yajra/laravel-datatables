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
        $this->request    = $request;
        $this->query_type = 'eloquent';
        $this->query      = $model instanceof Builder ? $model : $model->getQuery();
        $this->columns    = $this->query->getQuery()->columns;
        $this->connection = $this->query->getQuery()->getConnection();
        $this->database   = $this->connection->getDriverName();

        if ($this->isDebugging()) {
            $this->connection->enableQueryLog();
        }
    }

}
