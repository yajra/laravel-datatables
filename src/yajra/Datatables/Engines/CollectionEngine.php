<?php

namespace yajra\Datatables\Engines;

/**
 * Laravel Datatables Collection Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use yajra\Datatables\Contracts\DataTableEngine;
use yajra\Datatables\Request;
use yajra\Datatables\Helper;

class CollectionEngine extends BaseEngine implements DataTableEngine
{

    /**
     * Collection object
     *
     * @var Collection
     */
    public $collection;

    /**
     * Collection object
     *
     * @var Collection
     */
    public $original_collection;

    /**
     * @param Collection $collection
     * @param \yajra\Datatables\Request $request
     */
    public function __construct(Collection $collection, Request $request)
    {
        $this->request             = $request;
        $this->collection          = $collection;
        $this->original_collection = $collection;
        $this->columns             = array_keys($this->serialize($collection->first()));
    }

    /**
     * Serialize collection
     *
     * @param  mixed $collection
     * @return mixed|null
     */
    protected function serialize($collection)
    {
        return $collection instanceof Arrayable ? $collection->toArray() : (array) $collection;
    }

    /**
     * @inheritdoc
     */
    public function filter(Closure $callback)
    {
        $this->overrideGlobalSearch($callback, $this);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function showDebugger(array $output)
    {
        $output["input"] = $this->request->all();

        return $output;
    }


    /**
     * @inheritdoc
     */
    public function count()
    {
        return $this->collection->count();
    }

    /**
     * @inheritdoc
     */
    public function ordering()
    {
        foreach ($this->request->orderableColumns() as $orderable) {
            $column           = $this->getColumnName($orderable['column']);
            $this->collection = $this->collection->sortBy(
                function ($row) use ($column) {
                    $data = $this->serialize($row);

                    return Arr::get($data, $column);
                }
            );

            if ($orderable['direction'] == 'desc') {
                $this->collection = $this->collection->reverse();
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function filtering()
    {
        $columns          = $this->request['columns'];
        $this->collection = $this->collection->filter(
            function ($row) use ($columns) {
                $data  = $this->serialize($row);
                $this->isFilterApplied = true;
                $found = [];

                $keyword = $this->request->keyword();
                foreach ($this->request->searchableColumnIndex() as $index) {
                    $column = $this->getColumnName($index);
                    if ( ! $value = Arr::get($data, $column)) {
                        continue;
                    }

                    if ($this->isCaseInsensitive()) {
                        $found[] = Str::contains(Str::lower($value), Str::lower($keyword));
                    } else {
                        $found[] = Str::contains($value, $keyword);
                    }
                }

                return in_array(true, $found);
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function columnSearch()
    {
        $columns = $this->request->get('columns');
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($this->request->isColumnSearchable($i)) {
                $this->isFilterApplied = true;

                $column  = $this->getColumnName($i);
                $keyword = $this->request->columnKeyword($i);

                $this->collection = $this->collection->filter(
                    function ($row) use ($column, $keyword) {
                        $data = $this->serialize($row);
                        if ($this->isCaseInsensitive()) {
                            return strpos(Str::lower($data[$column]), Str::lower($keyword)) !== false;
                        } else {
                            return strpos($data[$column], $keyword) !== false;
                        }
                    }
                );
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function paging()
    {
        $this->collection = $this->collection->slice(
            $this->request['start'],
            (int) $this->request['length'] > 0 ? $this->request['length'] : 10
        );
    }

    /**
     * @inheritdoc
     */
    public function results()
    {
        return $this->collection->all();
    }

    /**
     * @inheritdoc
     */
    public function make($mDataSupport = false, $orderFirst = true)
    {
        return parent::make($mDataSupport, $orderFirst);
    }
}
