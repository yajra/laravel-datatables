<?php

namespace Yajra\DataTables\Facades;

use Yajra\DataTables\QueryDataTable;
use Illuminate\Support\Facades\Facade;
use Yajra\DataTables\EloquentDatatable;
use Yajra\DataTables\CollectionDataTable;

/**
 * @mixin \Yajra\DataTables\DataTables
 * @method static EloquentDatatable eloquent($builder)
 * @method static QueryDataTable queryBuilder($builder)
 * @method static CollectionDataTable collection($collection)
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
