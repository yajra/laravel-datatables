<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Traits\Macroable;
use Yajra\DataTables\Exceptions\Exception;

class DataTables
{
    use Macroable;

    /**
     * DataTables request object.
     *
     * @var \Yajra\DataTables\Utilities\Request
     */
    protected Utilities\Request $request;

    /**
     * HTML builder instance.
     *
     * @phpstan-ignore-next-line
     *
     * @var \Yajra\DataTables\Html\Builder|null
     */
    protected $html = null;

    /**
     * Make a DataTable instance from source.
     * Alias of make for backward compatibility.
     *
     * @param  object  $source
     * @return DataTableAbstract
     *
     * @throws \Exception
     */
    public static function of($source)
    {
        return self::make($source);
    }

    /**
     * Make a DataTable instance from source.
     *
     * @param  object  $source
     * @return DataTableAbstract
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public static function make($source)
    {
        $engines = (array) config('datatables.engines');
        $builders = (array) config('datatables.builders');

        $args = func_get_args();
        foreach ($builders as $class => $engine) {
            if ($source instanceof $class) {
                $callback = [$engines[$engine], 'create'];

                if (is_callable($callback)) {
                    // @phpstan-ignore-next-line
                    return call_user_func_array($callback, $args);
                }
            }
        }

        foreach ($engines as $engine) {
            $canCreate = [$engine, 'canCreate'];
            if (is_callable($canCreate) && call_user_func_array($canCreate, $args)) {
                $create = [$engine, 'create'];

                if (is_callable($create)) {
                    // @phpstan-ignore-next-line
                    return call_user_func_array($create, $args);
                }
            }
        }

        throw new Exception('No available engine for '.get_class($source));
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
     * DataTables using Query.
     *
     * @param  QueryBuilder  $builder
     * @return \Yajra\DataTables\QueryDataTable
     */
    public function query(QueryBuilder $builder): QueryDataTable
    {
        return QueryDataTable::create($builder);
    }

    /**
     * DataTables using Eloquent Builder.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $builder
     * @return \Yajra\DataTables\EloquentDataTable
     */
    public function eloquent(EloquentBuilder $builder): EloquentDataTable
    {
        return EloquentDataTable::create($builder);
    }

    /**
     * DataTables using Collection.
     *
     * @param  \Illuminate\Support\Collection<array-key, array>|array  $collection
     * @return \Yajra\DataTables\CollectionDataTable
     */
    public function collection($collection): CollectionDataTable
    {
        return CollectionDataTable::create($collection);
    }

    /**
     * Get html builder instance.
     *
     * @phpstan-ignore-next-line
     *
     * @return \Yajra\DataTables\Html\Builder
     *
     * @throws Exception
     */
    public function getHtmlBuilder()
    {
        if (! class_exists('\Yajra\DataTables\Html\Builder')) {
            throw new Exception('Please install yajra/laravel-datatables-html to be able to use this function.');
        }

        return $this->html ?: $this->html = app('datatables.html');
    }
}
