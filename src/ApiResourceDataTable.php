<?php

namespace Yajra\DataTables;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ApiResourceDataTable extends CollectionDataTable
{
    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param  mixed  $source
     * @return bool
     */
    public static function canCreate($source)
    {
        return $source instanceof AnonymousResourceCollection;
    }

    /**
     * CollectionEngine constructor.
     *
     * @param  \Illuminate\Http\Resources\Json\AnonymousResourceCollection  $collection
     */
    public function __construct(AnonymousResourceCollection $collection)
    {
        $this->request    = app('datatables.request');
        $this->config     = app('datatables.config');
        $this->collection = collect($collection->toArray($this->request->getBaseRequest()));
        $this->original   = $collection;
        $this->columns    = array_keys($this->serialize(collect($collection->toArray($this->request->getBaseRequest()))->first()));
        if ($collection->resource instanceof LengthAwarePaginator) {
            $this->isFilterApplied = true;
        }
    }
}
