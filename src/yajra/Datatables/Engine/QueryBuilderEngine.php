<?php namespace yajra\Datatables\Engine;

/**
 * Laravel Datatables Query Builder Engine
 *
 * @package    Laravel
 * @category   Package
 * @author     Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

class QueryBuilderEngine extends BaseEngine implements EngineContract
{

    /**
     * Read Input into $this->input according to jquery.dataTables.js version
     *
     * @param Builder $builder
     */
    public function __construct(Builder $builder, $request)
    {
        $this->query_type = 'builder';
        $this->query = $builder;
        $this->columns = $this->query->columns;
        $this->connection = $this->query->getConnection();

        if ($this->isDebugging()) {
            $this->connection->enableQueryLog();
        }

        parent::__construct($request);

        return $this;
    }

    /**
     * Set results from prepared query
     */
    public function setResults()
    {
        $this->result_object = $this->query->get();
        $this->result_array = array_map(function ($object) {
            return (array) $object;
        }, $this->result_object);
    }

}
