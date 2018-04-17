<?php

namespace Yajra\DataTables;

use Illuminate\Support\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApiResourceDataTable extends CollectionDataTable
{
    /**
     * Collection object.
     *
     * @var Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public $collection;

    /**
     * Collection object.
     *
     * @var Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public $original;

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param mixed $source
     * @return bool
     */
    public static function canCreate($source)
    {
        return is_array($source) || $source instanceof AnonymousResourceCollection;
    }

    /**
     * Factory method, create and return an instance for the DataTable engine.
     *
     * @param array|Illuminate\Http\Resources\Json\AnonymousResourceCollection $source
     * @return ApiResourceDataTable|DataTableAbstract
     */
    public static function create($source)
    {
        if (is_array($source)) {
            $source = new AnonymousResourceCollection($source);
        }

        return parent::create($source);
    }

    /**
     * CollectionEngine constructor.
     *
     * @param Illuminate\Http\Resources\Json\AnonymousResourceCollection $collection
     */
    public function __construct(AnonymousResourceCollection $collection)
    {
        $this->request    = app('datatables.request');
        $this->config     = app('datatables.config');
        $this->collection = collect($collection->toArray($this->request));
        $this->original   = collect($collection->toArray($this->request));
        $this->columns    = array_keys($this->serialize(collect($collection->toArray($this->request))->first()));
    }
}
