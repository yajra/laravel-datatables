<?php

namespace Yajra\DataTables;

use Illuminate\Support\Traits\Macroable;

class DataTables
{
    use Macroable;

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

        $args = func_get_args();
        foreach ($builders as $class => $engine) {
            if ($source instanceof $class) {
                return call_user_func_array([$engines[$engine], 'create'], $args);
            }
        }

        foreach ($engines as $engine => $class) {
            if (call_user_func_array([$engines[$engine], 'canCreate'], $args)) {
                return call_user_func_array([$engines[$engine], 'create'], $args);
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
     * @deprecated Please use query() instead, this method will be removed in a next version.
     * @param $builder
     * @return QueryDataTable
     */
    public function queryBuilder($builder)
    {
        return $this->query($builder);
    }

    /**
     * DataTables using Query.
     *
     * @param \Illuminate\Database\Query\Builder|mixed $builder
     * @return DataTableAbstract|QueryDataTable
     */
    public function query($builder)
    {
        return QueryDataTable::create($builder);
    }

    /**
     * DataTables using Eloquent Builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder|mixed $builder
     * @return DataTableAbstract|EloquentDataTable
     */
    public function eloquent($builder)
    {
        return EloquentDataTable::create($builder);
    }

    /**
     * DataTables using Collection.
     *
     * @param \Illuminate\Support\Collection|array $collection
     * @return DataTableAbstract|CollectionDataTable
     */
    public function collection($collection)
    {
        return CollectionDataTable::create($collection);
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
}
