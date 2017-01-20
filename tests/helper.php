<?php

use Mockery as m;
use Yajra\Datatables\Datatables;
use Yajra\Datatables\Html\Builder;
use Yajra\Datatables\Request;

function app($instance)
{
    switch ($instance) {
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
        $config             = require __DIR__ . '/../src/config/datatables.php';
        $config['builders'] = array_add($config['builders'], 'Mockery_3_Illuminate_Database_Query_Builder', 'query');
        $config['builders'] = array_add($config['builders'], 'Mockery_4_Illuminate_Database_Query_Builder', 'query');

        return $config[$keys[1]];
    }
}
