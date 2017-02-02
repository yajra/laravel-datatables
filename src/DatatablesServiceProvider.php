<?php

namespace Yajra\Datatables;

use Collective\Html\HtmlServiceProvider;
use Illuminate\Support\ServiceProvider;
use League\Fractal\Manager;
use League\Fractal\Serializer\DataArraySerializer;

/**
 * Class DatatablesServiceProvider.
 *
 * @package Yajra\Datatables
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
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
        if ($this->isLumen()) {
            require_once 'fallback.php';
        }

        $this->app->singleton('datatables.fractal', function () {
            $fractal = new Manager;
            $config  = $this->app['config'];
            $request = $this->app['request'];

            $includesKey = $config->get('datatables.fractal.includes', 'include');
            if ($request->get($includesKey)) {
                $fractal->parseIncludes($request->get($includesKey));
            }

            $serializer = $config->get('datatables.fractal.serializer', DataArraySerializer::class);
            $fractal->setSerializer(new $serializer);

            return $fractal;
        });

        $this->app->alias('datatables', Datatables::class);
        $this->app->singleton('datatables', function () {
            return new Datatables(new Request(app('request')));
        });

        $this->registerAliases();
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

    /**
     * Create aliases for the dependency.
     */
    protected function registerAliases()
    {
        if (class_exists('Illuminate\Foundation\AliasLoader')) {
            $loader = \Illuminate\Foundation\AliasLoader::getInstance();
            $loader->alias('Datatables', \Yajra\Datatables\Facades\Datatables::class);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return string[]
     */
    public function provides()
    {
        return ['datatables', 'datatables.fractal'];
    }
}
