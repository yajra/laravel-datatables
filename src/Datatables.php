<?php

namespace Yajra\Datatables;

/**
 * Laravel Datatables Package
 * This Package is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net)
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Jenssegers\Mongodb\Model as Moloquent;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Yajra\Datatables\Engines\CollectionEngine;
use Yajra\Datatables\Engines\EloquentEngine;
use Yajra\Datatables\Engines\QueryBuilderEngine;
use Yajra\Datatables\Engines\MoloquentEngine;

/**
 * Class Datatables
 *
 * @package Yajra\Datatables
 * @method  EloquentEngine eloquent($builder)
 * @method  CollectionEngine collection(Collection $builder)
 * @method  QueryBuilderEngine queryBuilder(QueryBuilder $builder)
 */
class Datatables
{
    /**
     * Datatables request object.
     *
     * @var \Yajra\Datatables\Request
     */
    public $request;

    /**
     * Datatables builder.
     *
     * @var mixed
     */
    public $builder;

    /**
     * Class Constructor
     *
     * @param \Yajra\Datatables\Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request->request->count() ? $request : Request::capture();
    }

    /**
     * Gets query and returns instance of class
     *
     * @param  mixed $builder
     * @return mixed
     */
    public static function of($builder)
    {
        $datatables          = app('datatables');
        $datatables->builder = $builder;

        switch (true) {
            case $builder instanceof QueryBuilder:
                $ins = $datatables->usingQueryBuilder($builder);
                break;
            case $builder instanceof Moloquent:
                $ins = $datatables->usingMoloquent($builder);
                break;
            case $builder instanceof Collection:
                $ins = $datatables->usingCollection($builder);
                break;
            default:
                $ins = $datatables->usingEloquent($builder);
                break;
        }

        return $ins;
    }

    /**
     * Datatables using Query Builder.
     *
     * @param \Illuminate\Database\Query\Builder $builder
     * @return \Yajra\Datatables\Engines\QueryBuilderEngine
     */
    public function usingQueryBuilder(QueryBuilder $builder)
    {
        return new QueryBuilderEngine($builder, $this->request);
    }

    /**
     * Datatables using Collection.
     *
     * @param \Illuminate\Support\Collection $builder
     * @return \Yajra\Datatables\Engines\CollectionEngine
     */
    public function usingCollection(Collection $builder)
    {
        return new CollectionEngine($builder, $this->request);
    }

    /**
     * Allows api call without the "using" word.
     *
     * @param  string $name
     * @param  mixed $arguments
     * @return $this|mixed
     */
    public function __call($name, $arguments)
    {
        $name = 'using' . ucfirst($name);

        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $arguments);
        }

        return trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
    }

    /**
     * Datatables using Eloquent
     *
     * @param  mixed $builder
     * @return \Yajra\Datatables\Engines\EloquentEngine
     */
    public function usingEloquent($builder)
    {
        return new EloquentEngine($builder, $this->request);
    }
    
    /**
     * Datatables using Moloquent
     * 
     * @param mixed $builder
     * @return \Yajra\Datatables\Engines\MoloquentEngine
     */
    public function usingMoloquent($builder)
    {
        return new MoloquentEngine($builder, $this->request);
    }

    /**
     * Get html builder class.
     *
     * @return \Yajra\Datatables\Html\Builder
     */
    public function getHtmlBuilder()
    {
        return app('Yajra\Datatables\Html\Builder');
    }

    /**
     * Get request object.
     *
     * @return \Yajra\Datatables\Request|static
     */
    public function getRequest()
    {
        return $this->request;
    }
}
