<?php

namespace Yajra\DataTables;

use Yajra\DataTables\Utilities\Config;
use Illuminate\Support\ServiceProvider;
use Yajra\DataTables\Utilities\Request;

class DataTablesServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if ($this->isLumen()) {
            require_once 'lumen.php';
        }

        $this->setupAssets();

        $this->app->alias('datatables', DataTables::class);
        $this->app->singleton('datatables', function () {
            return new DataTables;
        });

        $this->app->singleton('datatables.request', function () {
            return new Request;
        });

        $this->app->singleton('datatables.config', Config::class);
    }

    /**
     * Boot the instance, add macros for datatable engines.
     *
     * @return void
     */
    public function boot()
    {
        $engines = config('datatables.engines');
        foreach ($engines as $engine => $class) {
            $engine = camel_case($engine);

            if (! method_exists(DataTables::class, $engine) && ! DataTables::hasMacro($engine)) {
                DataTables::macro($engine, function () use ($class) {
                    if (! call_user_func_array([$class, 'canCreate'], func_get_args())) {
                        throw new \InvalidArgumentException();
                    }

                    return call_user_func_array([$class, 'create'], func_get_args());
                });
            }
        }
    }

    /**
     * Setup package assets.
     *
     * @return void
     */
    protected function setupAssets()
    {
        $this->mergeConfigFrom($config = __DIR__ . '/config/datatables.php', 'datatables');

        if ($this->app->runningInConsole()) {
            $this->publishes([$config => config_path('datatables.php')], 'datatables');
        }
    }

    /**
     * Check if app uses Lumen.
     *
     * @return bool
     */
    protected function isLumen()
    {
        return str_contains($this->app->version(), 'Lumen');
    }
}
