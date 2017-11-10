<?php

namespace Yajra\DataTables;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
    public function of($source)
    {
        return $this->make($source);
    }

    /**
     * Make a DataTable instance from source.
     *
     * @param mixed $source
     * @return mixed
     * @throws \Exception
     */
    public function make($source)
    {
        if (is_array($source)) {
            $source = new Collection($source);
        }

        if ($engine = $this->getEngineForSource($source)) {
            return $this->createDataTable($engine, $source);
        }

        throw new Exception('No available engine for ' . get_class($source));
    }

    /**
     * Get the optimum engine for the given data source.
     *
     * @param  mixed  $source
     * @return string|null
     */
    protected function getEngineForSource($source)
    {
        $result = null;

        foreach (config('datatables.builders') as $type => $engine) {
            if ($source instanceof $type) {
                if (! isset($tmpType) || is_subclass_of($type, $tmpType)) {
                    $tmpType = $type;
                    $result = $engine;
                }
            }
        }

        return $result;
    }

    /**
     * Create a new DataTable instance.
     *
     * @param  string  $engine
     * @param  mixed  $source
     * @return mixed
     * @throws \Exception
     */
    protected function createDataTable($engine, $source)
    {
        if ($class = class_exists($engine) ? $engine : Arr::get(config('datatables.engines'), $engine)) {
            return new $class($source);
        }

        throw new Exception("Unsupported DataTable engine [$engine]");
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
     * @deprecated Please use query() instead, this method will be removed in a next version.
     * @param $builder
     * @return QueryDataTable
     */
    public function queryBuilder($builder)
    {
        return $this->query($builder);
    }

    /**
     * Get html builder instance.
     *
     * @return \Yajra\DataTables\Html\Builder
     * @throws \Exception
     */
    public function getHtmlBuilder()
    {
        if (! class_exists('\Yajra\DataTables\Html\Builder')) {
            throw new \Exception('Please install yajra/laravel-datatables-html to be able to use this function.');
        }

        return $this->html ?: $this->html = app('datatables.html');
    }

    /**
     * Make a DataTable instance by using method name as engine.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->createDataTable(Str::snake($method), ...$parameters);
    }
}
