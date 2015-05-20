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
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
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
     * Gets query and returns instance of class
     *
     * @param $builder
     * @return mixed
     */
    public static function of($builder)
    {
        $datatables = new Datatables;
        $datatables->request = Request::capture();
        $datatables->builder = $builder;

        if ($builder instanceof QueryBuilder) {
            $ins = $datatables->usingQueryBuilder($builder);
        } else {
            $ins = $builder instanceof Collection ? $datatables->usingCollection() : $datatables->usingEloquent();
        }

        return $ins;
    }

    /**
     * Datatables using Query Builder
     *
     * @return QueryBuilderEngine
     */
    public function usingQueryBuilder()
    {
        return new QueryBuilderEngine($this->builder, $this->request);
    }

    /**
     * Datatables using Collection
     *
     * @return CollectionEngine
     */
    public function usingCollection()
    {
        return new CollectionEngine($this->builder, $this->request);
    }

    /**
     * Datatables using Eloquent
     *
     * @return EloquentEngine
     */
    public function usingEloquent()
    {
        return new EloquentEngine($this->builder, $this->request);
    }

}
