<?php namespace yajra\Datatables\Engine;

/**
 * Laravel Datatables Eloquent Engine
 *
 * @package    Laravel
 * @category   Package
 * @author     Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Database\Eloquent\Builder;

class EloquentEngine extends BaseEngine implements EngineContract
{

    /**
     * Read Input into $this->input according to jquery.dataTables.js version
     *
     * @param Builder|HasMany|... $model
     */
    public function __construct($model)
    {
        $this->query_type = 'eloquent';
        $this->query = $model instanceof Builder ? $model : $model->getQuery();
        $this->columns = $this->query->getQuery()->columns;
        $this->connection = $this->query->getQuery()->getConnection();

        parent::__construct();

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
        }, $this->result_object->toArray());
    }

}
