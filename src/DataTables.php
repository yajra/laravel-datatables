<?php

namespace Yajra\DataTables;

use Illuminate\Support\Collection;

class DataTables
{
    /**
     * DataTables request object.
     *
     * @var \Yajra\DataTables\Utilities\Request
     */
    protected $request;

    /**
     * HTML builder instance.
     *
     * @var \Yajra\DataTables\Html\Builder
     */
    protected $html;

    /**
     * Make a DataTable instance from source.
     * Alias of make for backward compatibility.
     *
     * @param  mixed $source
     * @return mixed
     * @throws \Exception
     */
    public static function of($source)
    {
        return self::make($source);
    }

    /**
     * Make a DataTable instance from source.
     *
     * @param mixed $source
     * @return mixed
     * @throws \Exception
     */
    public static function make($source)
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
     * @return \Yajra\DataTables\Utilities\Request
     */
    public function getRequest()
    {
        return app('datatables.request');
    }

    /**
     * Get config instance.
     *
     * @return \Yajra\DataTables\Utilities\Config
     */
    public function getConfig()
    {
        return app('datatables.config');
    }

    /**
     * DataTables using Query Builder.
     *
     * @param \Illuminate\Database\Query\Builder|mixed $builder
     * @return \Yajra\DataTables\QueryDataTable
     */
    public function queryBuilder($builder)
    {
        return new QueryDataTable($builder);
    }

    /**
     * DataTables using Eloquent Builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder|mixed $builder
     * @return \Yajra\DataTables\EloquentDataTable
     */
    public function eloquent($builder)
    {
        return new EloquentDataTable($builder);
    }

    /**
     * DataTables using Collection.
     *
     * @param \Illuminate\Support\Collection|array $collection
     * @return \Yajra\DataTables\CollectionDataTable
     */
    public function collection($collection)
    {
        if (is_array($collection)) {
            $collection = new Collection($collection);
        }

        return new CollectionDataTable($collection);
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

        return $this->html ?: $this->html = app('datatables.html');
    }
}
