<?php

use Mockery as m;
use Yajra\Datatables\Datatables;
use Yajra\Datatables\Html\Builder;
use Yajra\Datatables\Request;

function app($instance)
{
    switch ($instance) {
        case 'datatables.html':
            return new Builder(
                m::mock('Illuminate\Contracts\Config\Repository'),
                m::mock('Illuminate\Contracts\View\Factory'),
                m::mock('Collective\Html\HtmlBuilder'),
                m::mock('Illuminate\Routing\UrlGenerator'),
                m::mock('Collective\Html\FormBuilder')
            );
        case 'config':
            return new Config;
        case 'view':
            return m::mock('Illuminate\Contracts\View\Factory', function ($mock) {
                $mock->shouldReceive('exists')->andReturn(false);
            });
    }

    return new Datatables(Request::capture());
}

function view($view = null, array $data = [])
{
    if (! $view) {
        return new BladeView();
    }

    return (new BladeView())->exists($view);
}

/**
 * Blade View Stub
 */
class BladeView
{
    public function exists($view)
    {
        return false;
    }
}

class Config
{
    public function get($key)
    {
        $keys               = explode('.', $key);
        $config             = require __DIR__ . '/../src/config/config.php';
        $config['builders'] = array_add($config['builders'], 'Mockery_8_Illuminate_Database_Query_Builder', 'query');

        return $config[$keys[1]];
    }
}
