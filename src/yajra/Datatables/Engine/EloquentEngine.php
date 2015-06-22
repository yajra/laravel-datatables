<?php

namespace yajra\Datatables\Engine;

/**
 * Laravel Datatables Eloquent Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Database\Eloquent\Builder;
use yajra\Datatables\Request;

class EloquentEngine extends QueryBuilderEngine
{

    /**
     * @param mixed $model
     * @param \yajra\Datatables\Request $request
     */
    public function __construct($model, Request $request)
    {
        $this->query_type = 'eloquent';
        $this->query      = $model instanceof Builder ? $model : $model->getQuery();
        $this->columns    = $this->query->getQuery()->columns;
        $this->connection = $this->query->getQuery()->getConnection();

        if ($this->isDebugging()) {
            $this->connection->enableQueryLog();
        }

        $this->request = $request;
        $this->getTotalRecords();
    }

    /**
     * Get results of query and convert to array.
     *
     * @return array
     */
    public function getResults()
    {
        $this->result_object = $this->query->get();

        return $this->result_object->toArray();
    }

}
