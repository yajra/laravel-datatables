<?php

namespace Yajra\DataTables\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @mixin \Yajra\DataTables\DataTables
 * @method eloquent($builder)
 * @method queryBuilder($builder)
 * @method collection($collection)
 */
class DataTables extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'datatables';
    }
}
