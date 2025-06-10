<?php

namespace Yajra\DataTables;

use Closure;
use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CollectionDataTable extends DataTableAbstract
{
    /**
     * Collection object.
     *
     * @var \Illuminate\Support\Collection<array-key, array>
     */
    public Collection $original;

    /**
     * The offset of the first record in the full dataset.
     */
    private int $offset = 0;

    /**
     * CollectionEngine constructor.
     *
     * @param  \Illuminate\Support\Collection<array-key, array>  $collection
     */
    public function __construct(public Collection $collection)
    {
        $this->request = app('datatables.request');
        $this->config = app('datatables.config');
        $this->original = $this->collection;
        $this->columns = array_keys($this->serialize($this->collection->first()));
    }

    /**
     * Serialize collection.
     */
    protected function serialize(mixed $collection): array
    {
        return $collection instanceof Arrayable ? $collection->toArray() : (array) $collection;
    }

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
     * @param  AnonymousResourceCollection|array|\Illuminate\Support\Collection<array-key, array>  $source
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
     * Count results.
     */
    public function count(): int
    {
        return $this->collection->count();
    }

    /**
     * Perform column search.
     */
    public function columnSearch(): void
    {
        for ($i = 0, $c = count($this->request->columns()); $i < $c; $i++) {
            $column = $this->getColumnName($i);

            if (is_null($column)) {
                continue;
            }

            if (! $this->request->isColumnSearchable($i) || $this->isBlacklisted($column)) {
                continue;
            }

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
     */
    public function paging(): void
    {
        $offset = $this->request->start() - $this->offset;
        $length = $this->request->length() > 0 ? $this->request->length() : 10;

        $this->collection = $this->collection->slice($offset, $length);
    }

    /**
     * Organizes works.
     *
     * @throws \Exception
     */
    public function make(bool $mDataSupport = true): JsonResponse
    {
        try {
            $this->validateMinLengthSearch();

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
        } catch (Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * Get results.
     *
     * @return \Illuminate\Support\Collection<array-key, array>
     */
    public function results(): Collection
    {
        return $this->collection;
    }

    /**
     * Revert transformed DT_RowIndex back to its original values.
     *
     * @param  bool  $mDataSupport
     */
    private function revertIndexColumn($mDataSupport): void
    {
        if ($this->columnDef['index']) {
            $indexColumn = config('datatables.index_column', 'DT_RowIndex');
            $index = $mDataSupport ? $indexColumn : 0;
            $start = $this->request->start();

            $this->collection->transform(function ($data) use ($index, &$start) {
                $data[$index] = ++$start;

                return $data;
            });
        }
    }

    /**
     * Define the offset of the first item of the collection with respect to
     * the FULL dataset the collection was sliced from. It effectively allows the
     * collection to be "pre-sliced".
     *
     * @return static
     */
    public function setOffset(int $offset): self
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * Perform global search for the given keyword.
     */
    protected function globalSearch(string $keyword): void
    {
        $keyword = $this->config->isCaseInsensitive() ? Str::lower($keyword) : $keyword;

        $this->collection = $this->collection->filter(function ($row) use ($keyword) {
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
                ->map(fn ($data) => Arr::dot($data))
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
     */
    protected function getSorter(array $criteria): Closure
    {
        return function ($a, $b) use ($criteria) {
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
                    $cmp = strnatcasecmp($first[$column] ?? '', $second[$column] ?? '');
                } else {
                    $cmp = strnatcmp($first[$column] ?? '', $second[$column] ?? '');
                }
                if ($cmp != 0) {
                    return $cmp;
                }
            }

            // all elements were equal
            return 0;
        };
    }

    /**
     * Resolve callback parameter instance.
     *
     * @return array<int|string, mixed>
     */
    protected function resolveCallbackParameter(): array
    {
        return [$this, false];
    }
}
