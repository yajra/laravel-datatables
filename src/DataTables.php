<?php

namespace Yajra\DataTables;

use Illuminate\Support\Collection;

class DataTables
{
    /**
     * Datatables request object.
     *
     * @var \Yajra\DataTables\Request
     */
    protected $request;

    /**
     * HTML builder instance.
     *
     * @var \Yajra\DataTables\Html\Builder
     */
    protected $html;

    /**
     * Gets query and returns instance of class.
     *
     * @param  mixed $source
     * @return mixed
     * @throws \Exception
     */
    public static function of($source)
    {
        $engines  = config('datatables.engines');
        $builders = config('datatables.builders');

        if (is_array($source)) {
            $source = new Collection($source);
        }

        foreach ($builders as $class => $engine) {
            if ($source instanceof $class) {
                $class = $engines[$engine];

                return new $class($source);
            }
        }

        throw new \Exception('No available engine for ' . get_class($source));
    }

    /**
     * Get request object.
     *
     * @return \Yajra\DataTables\Request
     */
    public function getRequest()
    {
        return resolve('datatables.request');
    }

    /**
     * Datatables using Query Builder.
     *
     * @param \Illuminate\Database\Query\Builder|mixed $builder
     * @return \Yajra\DataTables\Engines\QueryBuilderEngine
     */
    public function queryBuilder($builder)
    {
        return new Engines\QueryBuilderEngine($builder);
    }

    /**
     * Datatables using Eloquent Builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder|mixed $builder
     * @return \Yajra\DataTables\Engines\EloquentEngine
     */
    public function eloquent($builder)
    {
        return new Engines\EloquentEngine($builder);
    }

    /**
     * Datatables using Collection.
     *
     * @param \Illuminate\Support\Collection|mixed $collection
     * @return \Yajra\DataTables\Engines\CollectionEngine
     */
    public function collection($collection)
    {
        if (is_array($collection)) {
            $collection = new Collection($collection);
        }

        return new Engines\CollectionEngine($collection);
    }

    /**
     * Get html builder instance.
     *
     * @return \Yajra\DataTables\Html\Builder
     * @throws \Exception
     */
    public function getHtmlBuilder()
    {
        if (!class_exists('\Yajra\DataTables\Html\Builder')) {
            throw new \Exception('Please install yajra/laravel-datatables-html to be able to use this function.');
        }

        return $this->html ?: $this->html = resolve('datatables.html');
    }
}
