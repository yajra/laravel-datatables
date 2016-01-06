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

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        if ($this->isLumen()) {
            require_once 'fallback.php';
        }

        $this->registerRequiredProviders();

        $this->app->singleton('datatables', function ($app) {
            return new Datatables($app->make(Request::class));
        });

        $this->registerAliases();
    }

    /**
     * Check if app uses Lumen.
     *
     * @return bool
     */
    private function isLumen()
    {
        return str_contains($this->app->version(), 'Lumen');
    }

    /**
     * Register 3rd party providers.
     */
    private function registerRequiredProviders()
    {
        $this->app->register(HtmlServiceProvider::class);
        $this->app->register(ExcelServiceProvider::class);
    }

    /**
     * Create aliases for the dependency.
     */
    private function registerAliases()
    {
        if (class_exists('Illuminate\Foundation\AliasLoader')) {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('Datatables', \Yajra\Datatables\Datatables::class);
        }
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
