<?php

namespace Yajra\DataTables\Facades;

use Illuminate\Support\Facades\Facade;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\EloquentDatatable;
use Yajra\DataTables\QueryDataTable;

/**
 * @mixin \Yajra\DataTables\DataTables
 * @method EloquentDatatable eloquent($builder)
 * @method QueryDataTable queryBuilder($builder)
 * @method CollectionDataTable collection($collection)
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
