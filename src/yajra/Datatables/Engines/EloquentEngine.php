<?php

namespace yajra\Datatables\Engines;

/**
 * Laravel Datatables Eloquent Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Contracts\Support\Arrayable;
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
        $this->query_type = 'eloquent';
        $this->query      = $model instanceof Builder ? $model : $model->getQuery();
        $this->columns    = $this->query->getQuery()->columns;
        $this->connection = $this->query->getQuery()->getConnection();

        if ($this->isDebugging()) {
            $this->connection->enableQueryLog();
        }

        $this->request = $request;
    }

    /**
     * Get results of query and convert to array.
     *
     * @return array
     */
    public function setResults()
    {
        $this->result_object = $this->query->get();

        return $this->result_array = array_map(
            function ($object) {
                return $object instanceof Arrayable ? $object->toArray() : (array) $object;
            }, $this->result_object->toArray()
        );
    }

}
