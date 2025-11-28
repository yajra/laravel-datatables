<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Traits\Macroable;
use Yajra\DataTables\Exceptions\Exception;
use Yajra\DataTables\Utilities\Config as DataTablesConfig;
use Yajra\DataTables\Utilities\Request;

class DataTables
{
    use Macroable;

    /**
     * DataTables request object.
     */
    protected Utilities\Request $request;

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
        $engines = Config::array('datatables.engines', []);
        $builders = Config::array('datatables.builders', []);

        $args = func_get_args();
        foreach ($builders as $class => $engine) {
            if (is_string($class) && $source instanceof $class) {
                /** @var int|string $engineKey */
                $engineKey = is_int($engine) || is_string($engine) ? $engine : (string) $engine;
                $callback = [$engines[$engineKey], 'create'];

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

        throw new Exception('No available engine for '.$source::class);
    }

    /**
     * Get request object.
     */
    public function getRequest(): Request
    {
        return app('datatables.request');
    }

    /**
     * Get config instance.
     */
    public function getConfig(): DataTablesConfig
    {
        return app('datatables.config');
    }

    /**
     * DataTables using query builder.
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function query(QueryBuilder $builder): QueryDataTable
    {
        $dataTable = Config::string('datatables.engines.query');

        $this->validateDataTable($dataTable, QueryDataTable::class);

        return $dataTable::create($builder);
    }

    /**
     * DataTables using Eloquent Builder.
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function eloquent(EloquentBuilder $builder): EloquentDataTable
    {
        $dataTable = Config::string('datatables.engines.eloquent');

        $this->validateDataTable($dataTable, EloquentDataTable::class);

        return $dataTable::create($builder);
    }

    /**
     * DataTables using Collection.
     *
     * @param  \Illuminate\Support\Collection<array-key, array>|array  $collection
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function collection($collection): CollectionDataTable
    {
        $dataTable = Config::string('datatables.engines.collection');

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
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function validateDataTable(string $engine, string $parent): void
    {
        if (! ($engine == $parent || is_subclass_of($engine, $parent))) {
            throw new Exception("The given datatable engine `$engine` is not compatible with `$parent`.");
        }
    }
}
