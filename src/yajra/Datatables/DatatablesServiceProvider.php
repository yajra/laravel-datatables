<?php

namespace yajra\Datatables;

use Illuminate\Support\ServiceProvider;
use yajra\Datatables\Generators\DataTablesMakeCommand;
use yajra\Datatables\Generators\DataTablesScopeCommand;

class DatatablesServiceProvider extends ServiceProvider
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
        $this->publishes([
            __DIR__ . '/config/config.php' => config_path('datatables.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/resources/assets/buttons.server-side.js' => public_path('vendor/datatables/buttons.server-side.js'),
        ], 'assets');

        $this->publishes([
            __DIR__ . '/resources/views' => base_path('/resources/views/vendor/datatables'),
        ], 'views');

        $this->loadViewsFrom(__DIR__ . '/resources/views', 'datatables');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['datatables'] = $this->app->share(
            function ($app) {
                $request = $app->make(Request::class);

                return new Datatables($request);
            }
        );

        $this->commands(DataTablesMakeCommand::class);
        $this->commands(DataTablesScopeCommand::class);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return ['datatables'];
    }
}
