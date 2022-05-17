<?php

namespace Yajra\DataTables\Utilities;

use Illuminate\Http\Request as BaseRequest;

/**
 * @mixin \Illuminate\Http\Request
 */
class Request
{
    /**
     * @var BaseRequest
     */
    protected BaseRequest $request;

    /**
     * Request constructor.
     */
    public function __construct()
    {
        $this->request = app('request');
    }

    /**
     * Proxy non-existing method calls to base request class.
     *
     * @param  string  $name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        $callback = [$this->request, $name];
        if (is_callable($callback)) {
            return call_user_func_array($callback, $arguments);
        }
    }

    /**
     * Get attributes from request instance.
     *
     * @param  string  $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->request->__get($name);
    }

    /**
     * Get all columns request input.
     *
     * @return array
     */
    public function columns(): array
    {
        return (array) $this->request->input('columns');
    }

    /**
     * Check if DataTables is searchable.
     *
     * @return bool
     */
    public function isSearchable(): bool
    {
        return $this->request->input('search.value') != '';
    }

    /**
     * Check if DataTables must uses regular expressions.
     *
     * @param  int  $index
     * @return bool
     */
    public function isRegex(int $index): bool
    {
        return $this->request->input("columns.$index.search.regex") === 'true';
    }

    /**
     * Get orderable columns.
     *
     * @return array
     */
    public function orderableColumns(): array
    {
        if (! $this->isOrderable()) {
            return [];
        }

        $orderable = [];
        for ($i = 0, $c = count((array) $this->request->input('order')); $i < $c; $i++) {
            /** @var int $order_col */
            $order_col = $this->request->input("order.$i.column");

            /** @var string $direction */
            $direction = $this->request->input("order.$i.dir");

            $order_dir = strtolower($direction) === 'asc' ? 'asc' : 'desc';
            if ($this->isColumnOrderable($order_col)) {
                $orderable[] = ['column' => $order_col, 'direction' => $order_dir];
            }
        }

        return $orderable;
    }

    /**
     * Check if DataTables ordering is enabled.
     *
     * @return bool
     */
    public function isOrderable(): bool
    {
        return $this->request->input('order') && count((array) $this->request->input('order')) > 0;
    }

    /**
     * Check if a column is orderable.
     *
     * @param  int  $index
     * @return bool
     */
    public function isColumnOrderable(int $index): bool
    {
        return $this->request->input("columns.$index.orderable", 'true') == 'true';
    }

    /**
     * Get searchable column indexes.
     *
     * @return array
     */
    public function searchableColumnIndex()
    {
        $searchable = [];
        $columns = (array) $this->request->input('columns');
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($this->isColumnSearchable($i, false)) {
                $searchable[] = $i;
            }
        }

        return $searchable;
    }

    /**
     * Check if a column is searchable.
     *
     * @param  int  $i
     * @param  bool  $column_search
     * @return bool
     */
    public function isColumnSearchable(int $i, bool $column_search = true): bool
    {
        if ($column_search) {
            return
                (
                    $this->request->input("columns.$i.searchable", 'true') === 'true'
                    ||
                    $this->request->input("columns.$i.searchable", 'true') === true
                )
                && $this->columnKeyword($i) != '';
        }

        return
            $this->request->input("columns.$i.searchable", 'true') === 'true'
            ||
            $this->request->input("columns.$i.searchable", 'true') === true;
    }

    /**
     * Get column's search value.
     *
     * @param  int  $index
     * @return string
     */
    public function columnKeyword(int $index): string
    {
        /** @var string $keyword */
        $keyword = $this->request->input("columns.$index.search.value") ?? '';

        return $this->prepareKeyword($keyword);
    }

    /**
     * Prepare keyword string value.
     *
     * @param  float|array|int|string  $keyword
     * @return string
     */
    protected function prepareKeyword(float|array|int|string $keyword): string
    {
        if (is_array($keyword)) {
            return implode(' ', $keyword);
        }

        return (string) $keyword;
    }

    /**
     * Get global search keyword.
     *
     * @return string
     */
    public function keyword(): string
    {
        /** @var string $keyword */
        $keyword = $this->request->input('search.value') ?? '';

        return $this->prepareKeyword($keyword);
    }

    /**
     * Get column name by index.
     *
     * @param  int  $i
     * @return string|null
     */
    public function columnName(int $i): ?string
    {
        /** @var string[] $column */
        $column = $this->request->input("columns.$i");

        return (isset($column['name']) && $column['name'] != '') ? $column['name'] : $column['data'];
    }

    /**
     * Check if DataTables allow pagination.
     *
     * @return bool
     */
    public function isPaginationable(): bool
    {
        return ! is_null($this->request->input('start')) &&
            ! is_null($this->request->input('length')) &&
            $this->request->input('length') != -1;
    }

    /**
     * @return BaseRequest
     */
    public function getBaseRequest(): BaseRequest
    {
        return $this->request;
    }

    /**
     * Get starting record value.
     *
     * @return int
     */
    public function start(): int
    {
        return intval($this->request->input('start', 0));
    }

    /**
     * Get per page length.
     *
     * @return int
     */
    public function length(): int
    {
        return intval($this->request->input('length', 10));
    }

    /**
     * Get draw request.
     *
     * @return int
     */
    public function draw(): int
    {
        return intval($this->request->input('draw', 0));
    }
}
