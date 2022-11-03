<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Traits\Macroable;
use Yajra\DataTables\Exceptions\Exception;
use Yajra\DataTables\Html\Builder;

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
     * @var \Yajra\DataTables\Html\Builder|null
     */
    protected ?Builder $html = null;

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
                    /** @var \Yajra\DataTables\DataTableAbstract $instance */
                    $instance = call_user_func_array($callback, $args);

                    return $instance;
                }
            }
        }

        foreach ($engines as $engine) {
            $canCreate = [$engine, 'canCreate'];
            if (is_callable($canCreate) && call_user_func_array($canCreate, $args)) {
                $create = [$engine, 'create'];

                if (is_callable($create)) {
                    /** @var \Yajra\DataTables\DataTableAbstract $instance */
                    $instance = call_user_func_array($create, $args);

                    return $instance;
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
        /** @var string */
        $dataTable = config('datatables.engines.query');

        $this->validateDataTable($dataTable, QueryDataTable::class);

        return $dataTable::create($builder);
    }

    /**
     * DataTables using Eloquent Builder.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder  $builder
     * @return \Yajra\DataTables\EloquentDataTable
     */
    public function eloquent(EloquentBuilder $builder): EloquentDataTable
    {
        /** @var string */
        $dataTable = config('datatables.engines.eloquent');

        $this->validateDataTable($dataTable, EloquentDataTable::class);

        return $dataTable::create($builder);
    }

    /**
     * DataTables using Collection.
     *
     * @param  \Illuminate\Support\Collection<array-key, array>|array  $collection
     * @return \Yajra\DataTables\CollectionDataTable
     */
    public function collection($collection): CollectionDataTable
    {
        /** @var string */
        $dataTable = config('datatables.engines.collection');

        $this->validateDataTable($dataTable, CollectionDataTable::class);

        return $dataTable::create($collection);
    }

    /**
     * DataTables using Collection.
     *
     * @param  \Illuminate\Http\Resources\Json\AnonymousResourceCollection<array-key, array>|array  $resource
     * @return ApiResourceDataTable|DataTableAbstract
     */
    public function resource($resource)
    {
        return ApiResourceDataTable::create($resource);
    }

    /**
     * Get html builder instance.
     *
     * @return \Yajra\DataTables\Html\Builder
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function getHtmlBuilder()
    {
        if (! class_exists(Builder::class)) {
            throw new Exception('Please install yajra/laravel-datatables-html to be able to use this function.');
        }

        return $this->html ?: $this->html = app('datatables.html');
    }

    /**
     * @param  string  $engine
     * @param  string  $parent
     * @return void
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function validateDataTable(string $engine, string $parent): void
    {
        if (! ($engine == $parent || is_subclass_of($engine, $parent))) {
            $this->throwInvalidEngineException($engine, $parent);
        }
    }

    /**
     * @param  string  $engine
     * @param  string  $parent
     * @return void
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function throwInvalidEngineException(string $engine, string $parent): void
    {
        throw new Exception("The given datatable engine `{$engine}` is not compatible with `{$parent}`.");
    }
}
