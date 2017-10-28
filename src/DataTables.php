<?php

namespace Yajra\DataTables;

use Exception;
use Illuminate\Support\Str;

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
        foreach (config('datatables.builders') as $engine => $types) {
            foreach ((array) $types as $type) {
                if ($this->checkType($source, $type)) {
                    return $this->createDataTable($engine, $source);
                }
            }
        }

        throw new Exception('No available engine for ' . gettype($source));
    }

    /**
     * Check whether a variable is the given type.
     *
     * @param  mixed  $var
     * @param  string  $type
     * @return bool
     */
    protected function checkType($var, $type)
    {
        if (is_object($var)) {
            return $var instanceof $type;
        }

        if (function_exists($func = "is_$type")) {
            return $func($var);
        }

        return false;
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
        $class = class_exists($engine) ? $engine : config("datatables.engines.$engine");

        if (! $class) {
            throw new Exception("Unsupported DataTable engine [$engine]");
        }

        return new $class($source);
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
