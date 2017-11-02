<?php

namespace Yajra\DataTables;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;

class CollectionDataTable extends DataTableAbstract
{
    /**
     * Collection object.
     *
     * @var \Illuminate\Support\Collection
     */
    public $collection;

    /**
     * Collection object.
     *
     * @var \Illuminate\Support\Collection
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
        return is_array($source) || $source instanceof Collection;
    }

    /**
     * Factory method, create and return an instance for the DataTable engine.
     *
     * @param array|\Illuminate\Support\Collection $source
     * @return CollectionDataTable|DataTableAbstract
     */
    public static function create($source)
    {
        if (is_array($source)) {
            $source = new Collection($source);
        }

        return parent::create($source);
    }

    /**
     * CollectionEngine constructor.
     *
     * @param \Illuminate\Support\Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->request    = app('datatables.request');
        $this->config     = app('datatables.config');
        $this->collection = $collection;
        $this->original   = $collection;
        $this->columns    = array_keys($this->serialize($collection->first()));
    }

    /**
     * Serialize collection.
     *
     * @param  mixed $collection
     * @return mixed|null
     */
    protected function serialize($collection)
    {
        return $collection instanceof Arrayable ? $collection->toArray() : (array) $collection;
    }

    /**
     * Count results.
     *
     * @return int
     */
    public function count()
    {
        return $this->collection->count() > $this->totalRecords ? $this->totalRecords : $this->collection->count();
    }

    /**
     * Perform column search.
     *
     * @return void
     */
    public function columnSearch()
    {
        $columns = $this->request->get('columns', []);
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($this->request->isColumnSearchable($i)) {
                $this->isFilterApplied = true;

                $regex   = $this->request->isRegex($i);
                $column  = $this->getColumnName($i);
                $keyword = $this->request->columnKeyword($i);

                $this->collection = $this->collection->filter(
                    function ($row) use ($column, $keyword, $regex) {
                        $data = $this->serialize($row);

                        $value = Arr::get($data, $column);

                        if ($this->config->isCaseInsensitive()) {
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
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = true)
    {
        try {
            $this->totalRecords = $this->totalCount();

            if ($this->totalRecords) {
                $results   = $this->results();
                $processed = $this->processResults($results, $mDataSupport);
                $output    = $this->transform($results, $processed);

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
     * Count total items.
     *
     * @return int
     */
    public function totalCount()
    {
        return $this->totalRecords ? $this->totalRecords : $this->collection->count();
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

    /**
     * Perform global search for the given keyword.
     *
     * @param string $keyword
     */
    protected function globalSearch($keyword)
    {
        $columns = $this->request->columns();
        $keyword = $this->config->isCaseInsensitive() ? Str::lower($keyword) : $keyword;

        $this->collection = $this->collection->filter(function ($row) use ($columns, $keyword) {
            $this->isFilterApplied = true;

            $data = $this->serialize($row);
            foreach ($this->request->searchableColumnIndex() as $index) {
                $column = $this->getColumnName($index);
                $value = Arr::get($data, $column);
                if (! $value || is_array($value)) {
                    if (! is_numeric($value)) {
                        continue;
                    } else {
                        $value = (string) $value;
                    }
                }

                $value = $this->config->isCaseInsensitive() ? Str::lower($value) : $value;
                if (Str::contains($value, $keyword)) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Perform default query orderBy clause.
     */
    protected function defaultOrdering()
    {
        $criteria = $this->request->orderableColumns();
        if (! empty($criteria)) {
            $sorter = $this->getSorter($criteria);

            $this->collection = $this->collection
                ->map(function ($data) {
                    return array_dot($data);
                })
                ->sort($sorter)
                ->map(function ($data) {
                    foreach ($data as $key => $value) {
                        unset($data[$key]);
                        array_set($data, $key, $value);
                    }

                    return $data;
                });
        }
    }

    /**
     * Get array sorter closure.
     *
     * @param array $criteria
     * @return \Closure
     */
    protected function getSorter(array $criteria)
    {
        $sorter = function ($a, $b) use ($criteria) {
            foreach ($criteria as $orderable) {
                $column    = $this->getColumnName($orderable['column']);
                $direction = $orderable['direction'];
                if ($direction === 'desc') {
                    $first  = $b;
                    $second = $a;
                } else {
                    $first  = $a;
                    $second = $b;
                }
                if ($this->config->isCaseInsensitive()) {
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

        return $sorter;
    }

    /**
     * Resolve callback parameter instance.
     *
     * @return $this
     */
    protected function resolveCallbackParameter()
    {
        return $this;
    }
}
