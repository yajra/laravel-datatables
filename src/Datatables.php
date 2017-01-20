<?php

namespace Yajra\Datatables;

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
}
