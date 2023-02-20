<?php

namespace Yajra\DataTables;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
     * Factory method, create and return an instance for the DataTable engine.
     *
     * @param  \Illuminate\Http\Resources\Json\AnonymousResourceCollection<array-key, array>|array  $source
     * @return ApiResourceDataTable|DataTableAbstract
     */
    public static function create($source)
    {
        return parent::create($source);
    }

    /**
     * CollectionEngine constructor.
     *
     * @param  \Illuminate\Http\Resources\Json\AnonymousResourceCollection<array-key, array>  $resourceCollection
     */
    public function __construct(AnonymousResourceCollection $resourceCollection)
    {
        /** @var \Illuminate\Support\Collection<(int|string), array> $collection */
        $collection = collect($resourceCollection)->pluck('resource');
        $this->request = app('datatables.request');
        $this->config = app('datatables.config');
        $this->collection = $collection;
        $this->original = $collection;
        $this->columns = array_keys($this->serialize($collection->first()));
    }
}
