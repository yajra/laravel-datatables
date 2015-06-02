<?php namespace yajra\Datatables;

/**
 * Laravel Datatables Package
 * This Package is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net)
 *
 * @package    Laravel
 * @category   Package
 * @author     Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use yajra\Datatables\Engine\CollectionEngine;
use yajra\Datatables\Engine\EloquentEngine;
use yajra\Datatables\Engine\QueryBuilderEngine;

/**
 * Class Datatables
 *
 * @package yajra\Datatables
 */
class Datatables
{

    /**
     * Input Request
     *
     * @var Request
     */
    public $request;

    /**
     * Datatables builder
     *
     * @var mixed
     */
    public $builder;

    /**
     * Class Constructor
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Gets query and returns instance of class
     *
     * @param $builder
     * @return mixed
     */
    public static function of($builder)
    {
        $datatables = app('datatables');
        $datatables->builder = $builder;

        if ($builder instanceof QueryBuilder) {
            $ins = $datatables->usingQueryBuilder($builder);
        } else {
            $ins = $builder instanceof Collection ? $datatables->usingCollection($builder) : $datatables->usingEloquent($builder);
        }

        return $ins;
    }

    /**
     * Datatables using Query Builder
     *
     * @param QueryBuilder $builder
     * @return QueryBuilderEngine
     */
    public function usingQueryBuilder(QueryBuilder $builder)
    {
        return new QueryBuilderEngine($builder, $this->request->all());
    }

    /**
     * Datatables using Collection
     *
     * @param Collection $builder
     * @return CollectionEngine
     */
    public function usingCollection(Collection $builder)
    {
        return new CollectionEngine($builder, $this->request->all());
    }

    /**
     * Datatables using Eloquent
     *
     * @param mixed $builder
     * @return EloquentEngine
     */
    public function usingEloquent($builder)
    {
        return new EloquentEngine($builder, $this->request->all());
    }

}
