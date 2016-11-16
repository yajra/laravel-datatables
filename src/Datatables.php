<?php

namespace Yajra\Datatables;

use Config;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Yajra\Datatables\Engines\CollectionEngine;
use Yajra\Datatables\Engines\EloquentEngine;
use Yajra\Datatables\Engines\QueryBuilderEngine;
use Yajra\Datatables\Html\Builder as HtmlBuilder;

/**
 * Class Datatables.
 *
 * @package Yajra\Datatables
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
class Datatables
{
    /**
     * Datatables request object.
     *
     * @var \Yajra\Datatables\Request
     */
    protected $request;

    /**
     * Datatables builder.
     *
     * @var \Yajra\Datatables\Html\Builder
     */
    protected $builder;

    /**
     * Datatables constructor.
     *
     * @param \Yajra\Datatables\Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request->request->count() ? $request : Request::capture();
    }

    /**
     * Gets query and returns instance of class.
     *
     * @param  mixed $object
     * @return mixed
     * @throws \Exception
     */
    public static function of($object)
    {
        $datatables = app('datatables');

        $engines  = Config::get('datatables.engines');
        $builders = Config::get('datatables.builders');
        $builder  = get_class($object);

        if (array_key_exists($builder, $builders)) {
            $engine = $builders[$builder];
            $class  = $engines[$engine];

            return new $class($object, $datatables->getRequest());
        }

        throw new \Exception('No available engine for ' . $builder);
    }

    /**
     * Get request object.
     *
     * @return \Yajra\Datatables\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Datatables using Query Builder.
     *
     * @param \Illuminate\Database\Query\Builder $builder
     * @return \Yajra\Datatables\Engines\QueryBuilderEngine
     */
    public function queryBuilder(QueryBuilder $builder)
    {
        return new QueryBuilderEngine($builder, $this->request);
    }

    /**
     * Datatables using Eloquent.
     *
     * @param mixed $builder
     * @return \Yajra\Datatables\Engines\EloquentEngine
     */
    public function eloquent($builder)
    {
        return new EloquentEngine($builder, $this->request);
    }

    /**
     * Datatables using Collection.
     *
     * @param \Illuminate\Support\Collection $builder
     * @return \Yajra\Datatables\Engines\CollectionEngine
     */
    public function collection(Collection $builder)
    {
        return new CollectionEngine($builder, $this->request);
    }

    /**
     * Get html builder class.
     *
     * @return \Yajra\Datatables\Html\Builder
     */
    public function getHtmlBuilder()
    {
        if (is_null($this->builder)) {
            return app(HtmlBuilder::class);
        }

        return $this->builder;
    }
}
