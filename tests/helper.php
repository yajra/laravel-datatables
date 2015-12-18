<?php

use Yajra\Datatables\Datatables;
use Yajra\Datatables\Html\Builder;
use Yajra\Datatables\Request;
use Mockery as m;

function app($instance)
{
    switch ($instance) {
        case 'Yajra\Datatables\Html\Builder':
            return new Builder(
                m::mock('Illuminate\Contracts\Config\Repository'),
                m::mock('Illuminate\Contracts\View\Factory'),
                m::mock('Collective\Html\HtmlBuilder'),
                m::mock('Illuminate\Routing\UrlGenerator'),
                m::mock('Collective\Html\FormBuilder')
            );
        case 'view':
            return m::mock('Illuminate\Contracts\View\Factory', function($mock) {
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
