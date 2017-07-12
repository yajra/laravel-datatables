<?php

namespace Yajra\Datatables\Engines;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Yajra\Datatables\Request;

/**
 * Class CollectionEngine.
 *
 * @package Yajra\Datatables\Engines
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
class CollectionEngine extends BaseEngine
{
    /**
     * Collection object
     *
     * @var \Illuminate\Support\Collection
     */
    public $collection;

    /**
     * Collection object
     *
     * @var \Illuminate\Support\Collection
     */
    public $original_collection;

    /**
     * CollectionEngine constructor.
     *
     * @param \Illuminate\Support\Collection $collection
     * @param \Yajra\Datatables\Request $request
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
     * Set auto filter off and run your own filter.
     * Overrides global search.
     *
     * @param \Closure $callback
     * @param bool $globalSearch
     * @return $this
     */
    public function filter(Closure $callback, $globalSearch = false)
    {
        $this->overrideGlobalSearch($callback, $this, $globalSearch);

        return $this;
    }

    /**
     * Append debug parameters on output.
     *
     * @param  array $output
     * @return array
     */
    public function showDebugger(array $output)
    {
        $output["input"] = $this->request->all();

        return $output;
    }

    /**
     * Count total items.
     *
     * @return integer
     */
    public function totalCount()
    {
        return $this->totalRecords ? $this->totalRecords : $this->collection->count();
    }

    /**
     * Count results.
     *
     * @return integer
     */
    public function count()
    {
        return $this->collection->count() > $this->totalRecords ? $this->totalRecords : $this->collection->count();
    }

    /**
     * Perform sorting of columns.
     *
     * @return void
     */
    public function ordering()
    {
        if ($this->orderCallback) {
            call_user_func($this->orderCallback, $this);

            return;
        }

        $criteria = $this->request->orderableColumns();
        if ($criteria) {
            $comparer = function ($a, $b) use ($criteria) {
                foreach ($criteria as $orderable) {
                    $column = $this->getColumnName($orderable['column']);
                    $direction = $orderable['direction'];
                    if ($direction === 'desc') {
                        $first = $b;
                        $second = $a;
                    } else {
                        $first = $a;
                        $second = $b;
                    }
                    if ($this->isCaseInsensitive()) {
                        $cmp = strnatcasecmp($first[$column], $second[$column]);
                    } else {
                        $cmp = strnatcmp($first[$column], $second[$column]);
                    }
                    if ($cmp != 0) {
                        return $cmp;
                    }
                }
                // all elements were equal
                return 0;
            };
            $this->collection = $this->collection->sort($comparer);
        }
    }

    /**
     * Perform global search for the given keyword.
     *
     * @param string $keyword
     */
    protected function globalSearch($keyword)
    {
        if ($this->isCaseInsensitive()) {
            $keyword = Str::lower($keyword);
        }

        $columns          = $this->request->columns();
        $this->collection = $this->collection->filter(
            function ($row) use ($columns, $keyword) {
                $data                  = $this->serialize($row);
                $this->isFilterApplied = true;

                foreach ($this->request->searchableColumnIndex() as $index) {
                    $column = $this->getColumnName($index);
                    if (! $value = Arr::get($data, $column)) {
                        continue;
                    }

                    if (is_array($value)) {
                        continue;
                    }

                    if ($this->isCaseInsensitive()) {
                        $value = Str::lower($value);
                    }

                    if (Str::contains($value, $keyword)) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /**
     * Perform column search.
     *
     * @return void
     */
    public function columnSearch()
    {
        $columns = $this->request->get('columns');
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($this->request->isColumnSearchable($i)) {
                $this->isFilterApplied = true;
                $regex                 = $this->request->isRegex($i);

                $column  = $this->getColumnName($i);
                $keyword = $this->request->columnKeyword($i);

                $this->collection = $this->collection->filter(
                    function ($row) use ($column, $keyword, $regex) {
                        $data = $this->serialize($row);

                        $value = Arr::get($data, $column);

                        if ($this->isCaseInsensitive()) {
                            if ($regex) {
                                return preg_match('/' . $keyword . '/i', $value) == 1;
                            } else {
                                return strpos(Str::lower($value), Str::lower($keyword)) !== false;
                            }
                        } else {
                            if ($regex) {
                                return preg_match('/' . $keyword . '/', $value) == 1;
                            } else {
                                return strpos($value, $keyword) !== false;
                            }
                        }
                    }
                );
            }
        }
    }

    /**
     * Perform pagination.
     *
     * @return void
     */
    public function paging()
    {
        $this->collection = $this->collection->slice(
            $this->request->input('start'),
            (int) $this->request->input('length') > 0 ? $this->request->input('length') : 10
        );
    }

    /**
     * Get results.
     *
     * @return mixed
     */
    public function results()
    {
        return $this->collection->all();
    }

    /**
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = false)
    {
        try {
            $this->totalRecords = $this->totalCount();

            if ($this->totalRecords) {
                $data   = $this->getProcessedData($mDataSupport);
                $output = $this->transform($data);

                $this->collection = collect($output);
                $this->ordering();
                $this->filterRecords();
                $this->paginate();

                $this->revertIndexColumn($mDataSupport);
            }

            return $this->render($this->collection->values()->all());
        } catch (\Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * Revert transformed DT_Row_Index back to it's original values.
     *
     * @param bool $mDataSupport
     */
    private function revertIndexColumn($mDataSupport)
    {
        if ($this->columnDef['index']) {
            $index = $mDataSupport ? config('datatables.index_column', 'DT_Row_Index') : 0;
            $start = (int) $this->request->input('start');
            $this->collection->transform(function ($data) use ($index, &$start) {
                $data[$index] = ++$start;

                return $data;
            });
        }
    }
}
