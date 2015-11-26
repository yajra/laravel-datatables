<?php

namespace Yajra\Datatables;

use Collective\Html\HtmlServiceProvider;
use Illuminate\Support\ServiceProvider;
use Maatwebsite\Excel\ExcelServiceProvider;
use Yajra\Datatables\Generators\DataTablesMakeCommand;
use Yajra\Datatables\Generators\DataTablesScopeCommand;

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
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'datatables');

        $this->publishAssets();

        $this->registerCommands();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(HtmlServiceProvider::class);
        $this->app->register(ExcelServiceProvider::class);

        $this->app['datatables'] = $this->app->share(
            function ($app) {
                $request = $app->make(Request::class);

                return new Datatables($request);
            }
        );
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

    /**
     * Publish datatables assets.
     */
    private function publishAssets()
    {
        $this->publishes([
            __DIR__ . '/config/config.php' => config_path('datatables.php'),
        ], 'datatables');

        $this->publishes([
            __DIR__ . '/resources/assets/buttons.server-side.js' => public_path('vendor/datatables/buttons.server-side.js'),
        ], 'datatables');

        $this->publishes([
            __DIR__ . '/resources/views' => base_path('/resources/views/vendor/datatables'),
        ], 'datatables');
    }

    /**
     * Register datatables commands.
     */
    private function registerCommands()
    {
        $this->commands(DataTablesMakeCommand::class);
        $this->commands(DataTablesScopeCommand::class);
    }
}
