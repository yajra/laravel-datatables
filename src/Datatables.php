<?php

namespace Yajra\Datatables;

use Yajra\Datatables\Html\Builder;

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
     * HTML builder instance.
     *
     * @var \Yajra\Datatables\Html\Builder
     */
    protected $html;

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
        $config     = app('config');
        $engines    = $config->get('datatables.engines');
        $builders   = $config->get('datatables.builders');
        $builder    = get_class($object);

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
     * @param \Illuminate\Database\Query\Builder|mixed $builder
     * @return \Yajra\Datatables\Engines\QueryBuilderEngine
     */
    public function queryBuilder($builder)
    {
        return new Engines\QueryBuilderEngine($builder, $this->request);
    }

    /**
     * Datatables using Eloquent Builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder|mixed $builder
     * @return \Yajra\Datatables\Engines\EloquentEngine
     */
    public function eloquent($builder)
    {
        return new Engines\EloquentEngine($builder, $this->request);
    }

    /**
     * Datatables using Collection.
     *
     * @param \Illuminate\Support\Collection|mixed $builder
     * @return \Yajra\Datatables\Engines\CollectionEngine
     */
    public function collection($builder)
    {
        return new Engines\CollectionEngine($builder, $this->request);
    }

    /**
     * Get html builder instance.
     *
     * @return \Yajra\Datatables\Html\Builder
     * @throws \Exception
     */
    public function getHtmlBuilder()
    {
        if (! class_exists('Yajra\Datatables\Html\Builder')) {
            throw new \Exception('Please install yajra/laravel-datatables-html to be able to use this function.');
        }

        return $this->html ?: app('datatables.html');
    }
}
