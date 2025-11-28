<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Traits\Macroable;
use Yajra\DataTables\Exceptions\Exception;
use Yajra\DataTables\Utilities\Config;
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
        $args = func_get_args();
        $engines = (array) config('datatables.engines');
        $builders = (array) config('datatables.builders');

        $instance = self::tryCreateFromBuilders($source, $builders, $engines, $args);
        if ($instance !== null) {
            return $instance;
        }

        $instance = self::tryCreateFromEngines($source, $engines, $args);
        if ($instance !== null) {
            return $instance;
        }

        throw new Exception('No available engine for '.$source::class);
    }

    /**
     * Try to create a DataTable instance from builders configuration.
     *
     * @param  object  $source
     */
    private static function tryCreateFromBuilders($source, array $builders, array $engines, array $args): ?DataTableAbstract
    {
        foreach ($builders as $class => $engine) {
            if (! self::isValidBuilderClass($source, $class)) {
                continue;
            }

            $engineClass = self::getEngineClass($engine, $engines);
            if ($engineClass === null) {
                continue;
            }

            $instance = self::createInstance($engineClass, $args);
            if ($instance !== null) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * Try to create a DataTable instance from engines configuration.
     *
     * @param  object  $source
     */
    private static function tryCreateFromEngines($source, array $engines, array $args): ?DataTableAbstract
    {
        foreach ($engines as $engine) {
            if (! self::canCreateInstance($engine, $args)) {
                continue;
            }

            $instance = self::createInstance($engine, $args);
            if ($instance !== null) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * Check if the source is a valid instance of the builder class.
     *
     * @param  object  $source
     * @param  string  $class
     */
    private static function isValidBuilderClass($source, $class): bool
    {
        return is_string($class) && class_exists($class) && $source instanceof $class;
    }

    /**
     * Get the engine class from the engine name.
     *
     * @param  mixed  $engine
     */
    private static function getEngineClass($engine, array $engines): ?string
    {
        if (! is_string($engine) || ! isset($engines[$engine])) {
            return null;
        }

        return $engines[$engine];
    }

    /**
     * Check if an engine can create an instance with the given arguments.
     *
     * @param  string  $engine
     */
    private static function canCreateInstance($engine, array $args): bool
    {
        $canCreate = [$engine, 'canCreate'];

        return is_callable($canCreate) && call_user_func_array($canCreate, $args);
    }

    /**
     * Create a DataTable instance from the engine class.
     *
     * @param  string  $engineClass
     */
    private static function createInstance($engineClass, array $args): ?DataTableAbstract
    {
        $callback = [$engineClass, 'create'];
        if (! is_callable($callback)) {
            return null;
        }

        /** @var \Yajra\DataTables\DataTableAbstract $instance */
        $instance = call_user_func_array($callback, $args);

        return $instance;
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
    public function getConfig(): Config
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
        /** @var string $dataTable */
        $dataTable = config('datatables.engines.query');

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
        /** @var string $dataTable */
        $dataTable = config('datatables.engines.eloquent');

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
        /** @var string $dataTable */
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
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function validateDataTable(string $engine, string $parent): void
    {
        if (! ($engine == $parent || is_subclass_of($engine, $parent))) {
            throw new Exception("The given datatable engine `$engine` is not compatible with `$parent`.");
        }
    }
}
