<?php

namespace yajra\Datatables\Engine;

/**
 * Laravel Datatables Query Builder Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Database\Query\Builder;

class QueryBuilderEngine extends BaseEngine implements EngineContract
{
    /**
     * @param Builder $builder
     * @param array $request
     */
    public function __construct(Builder $builder, array $request)
    {
        $this->query_type = 'builder';
        $this->query      = $builder;
        $this->columns    = $this->query->columns;
        $this->connection = $this->query->getConnection();

        if ($this->isDebugging()) {
            $this->connection->enableQueryLog();
        }

        parent::__construct($request);
    }

    /**
     * @inheritdoc
     */
    public function getResults()
    {
        $this->result_object = $this->query->get();

        return $this->result_object;
    }
}
