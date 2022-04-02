<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CollectionDataTable extends DataTableAbstract
{
    /**
     * Collection object.
     *
     * @var \Illuminate\Support\Collection<mixed>
     */
    public Collection $collection;

    /**
     * Collection object.
     *
     * @var \Illuminate\Support\Collection|\Illuminate\Http\Resources\Json\JsonResource
     */
    public Collection|JsonResource $original;

    /**
     * The offset of the first record in the full dataset.
     *
     * @var int
     */
    private int $offset = 0;

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param  mixed  $source
     * @return bool
     */
    public static function canCreate($source)
    {
        return is_array($source) || $source instanceof Collection;
    }

    /**
     * Factory method, create and return an instance for the DataTable engine.
     *
     * @param  array|\Illuminate\Support\Collection  $source
     * @return static
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
     * @param  \Illuminate\Support\Collection  $collection
     */
    public function __construct(Collection $collection)
    {
        $this->request = app('datatables.request');
        $this->config = app('datatables.config');
        $this->collection = $collection;
        $this->original = $collection;
        $this->columns = array_keys($this->serialize($collection->first()));
    }

    /**
     * Serialize collection.
     *
     * @param  mixed  $collection
     * @return array
     */
    protected function serialize($collection): array
    {
        return $collection instanceof Arrayable ? $collection->toArray() : (array) $collection;
    }

    /**
     * Count results.
     *
     * @return int
     */
    public function count(): int
    {
        return ($this->collection->count() > $this->totalRecords) ? $this->totalRecords : $this->collection->count();
    }

    /**
     * Perform column search.
     *
     * @return void
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function columnSearch()
    {
        /** @var array $columns */
        $columns = $this->request->get('columns', []);
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            $column = $this->getColumnName($i);

            if (! $this->request->isColumnSearchable($i) || $this->isBlacklisted($column)) {
                continue;
            }

            $this->isFilterApplied = true;

            $regex = $this->request->isRegex($i);
            $keyword = $this->request->columnKeyword($i);

            $this->collection = $this->collection->filter(
                function ($row) use ($column, $keyword, $regex) {
                    $data = $this->serialize($row);

                    /** @var string $value */
                    $value = Arr::get($data, $column);

                    if ($this->config->isCaseInsensitive()) {
                        if ($regex) {
                            return preg_match('/'.$keyword.'/i', $value) == 1;
                        }

                        return str_contains(Str::lower($value), Str::lower($keyword));
                    }

                    if ($regex) {
                        return preg_match('/'.$keyword.'/', $value) == 1;
                    }

                    return str_contains($value, $keyword);
                }
            );
        }
    }

    /**
     * Perform pagination.
     *
     * @return void
     */
    public function paging()
    {
        $offset = $this->request->start() - $this->offset;
        $length = $this->request->length() > 0 ? $this->request->length() : 10;

        $this->collection = $this->collection->slice($offset, $length);
    }

    /**
     * Organizes works.
     *
     * @param  bool  $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function make($mDataSupport = true)
    {
        try {
            $this->totalRecords = $this->totalCount();

            if ($this->totalRecords) {
                $results = $this->results();
                $processed = $this->processResults($results, $mDataSupport);
                $output = $this->transform($results, $processed);

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
     * @return iterable
     */
    public function results()
    {
        return $this->collection->all();
    }

    /**
     * Revert transformed DT_RowIndex back to it's original values.
     *
     * @param  bool  $mDataSupport
     * @return void
     */
    private function revertIndexColumn($mDataSupport): void
    {
        if ($this->columnDef['index']) {
            $indexColumn = config('datatables.index_column', 'DT_RowIndex');
            $index = $mDataSupport ? $indexColumn : 0;
            $start = $this->request->start();

            $this->collection->transform(function ($data) use ($index, &$start) {
                // @phpstan-ignore-next-line
                $data[$index] = ++$start;

                return $data;
            });
        }
    }

    /**
     * Perform global search for the given keyword.
     *
     * @param  string  $keyword
     * @return void
     */
    protected function globalSearch(string $keyword): void
    {
        $keyword = $this->config->isCaseInsensitive() ? Str::lower($keyword) : $keyword;

        $this->collection = $this->collection->filter(function ($row) use ($keyword) {
            $this->isFilterApplied = true;

            $data = $this->serialize($row);
            foreach ($this->request->searchableColumnIndex() as $index) {
                $column = $this->getColumnName($index);
                $value = Arr::get($data, $column);
                if (! is_string($value)) {
                    continue;
                } else {
                    $value = $this->config->isCaseInsensitive() ? Str::lower($value) : $value;
                }

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
    protected function defaultOrdering(): void
    {
        $criteria = $this->request->orderableColumns();
        if (! empty($criteria)) {
            $sorter = $this->getSorter($criteria);

            $this->collection = $this->collection
                ->map(function ($data) {
                    // @phpstan-ignore-next-line
                    return Arr::dot($data);
                })
                ->sort($sorter)
                ->map(function ($data) {
                    foreach ($data as $key => $value) {
                        unset($data[$key]);
                        Arr::set($data, $key, $value);
                    }

                    return $data;
                });
        }
    }

    /**
     * Get array sorter closure.
     *
     * @param  array  $criteria
     * @return \Closure
     */
    protected function getSorter(array $criteria)
    {
        $sorter = function ($a, $b) use ($criteria) {
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
                if (is_numeric($first[$column] ?? null) && is_numeric($second[$column] ?? null)) {
                    if ($first[$column] < $second[$column]) {
                        $cmp = -1;
                    } elseif ($first[$column] > $second[$column]) {
                        $cmp = 1;
                    } else {
                        $cmp = 0;
                    }
                } elseif ($this->config->isCaseInsensitive()) {
                    $cmp = strnatcasecmp($first[$column] ?? null, $second[$column] ?? null);
                } else {
                    $cmp = strnatcmp($first[$column] ?? null, $second[$column] ?? null);
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

    /**
     * Define the offset of the first item of the collection with respect to
     * the FULL dataset the collection was sliced from. It effectively allows the
     * collection to be "pre-sliced".
     *
     * @param  int  $offset
     * @return $this
     */
    public function setOffset(int $offset)
    {
        $this->offset = $offset;

        return $this;
    }
}
