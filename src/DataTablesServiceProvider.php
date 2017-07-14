<?php

namespace Yajra\DataTables;

use Illuminate\Support\ServiceProvider;
use Yajra\DataTables\Utilities\Config;
use Yajra\DataTables\Utilities\Request;

class DataTablesServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/datatables.php', 'datatables');

        $this->publishes([
            __DIR__ . '/config/datatables.php' => config_path('datatables.php'),
        ], 'datatables');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
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
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return [
            'datatables',
            'datatables.config',
            'datatables.request',
        ];
    }
}
