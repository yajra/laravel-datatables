<?php

namespace Yajra\DataTables\Utilities;

use Illuminate\Http\Request as BaseRequest;
use Yajra\DataTables\Exceptions\Exception;

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
    public function columns()
    {
        return (array) $this->request->input('columns');
    }

    /**
     * Check if DataTables is searchable.
     *
     * @return bool
     */
    public function isSearchable()
    {
        return $this->request->input('search.value') != '';
    }

    /**
     * Check if DataTables must uses regular expressions.
     *
     * @param  int  $index
     * @return bool
     */
    public function isRegex($index)
    {
        return $this->request->input("columns.$index.search.regex") === 'true';
    }

    /**
     * Get orderable columns.
     *
     * @return array
     */
    public function orderableColumns()
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
    public function isOrderable()
    {
        return $this->request->input('order') && count((array) $this->request->input('order')) > 0;
    }

    /**
     * Check if a column is orderable.
     *
     * @param  int  $index
     * @return bool
     */
    public function isColumnOrderable($index)
    {
        return $this->request->input("columns.$index.orderable", 'true') == 'true';
    }

    /**
     * Get searchable column indexes.
     *
     * @return array
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
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
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function isColumnSearchable($i, $column_search = true)
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
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function columnKeyword($index): string
    {
        $keyword = $this->request->input("columns.$index.search.value") ?? '';

        return $this->prepareKeyword($keyword);
    }

    /**
     * Prepare keyword string value.
     *
     * @param  mixed  $keyword
     * @return string
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    protected function prepareKeyword($keyword): string
    {
        if (is_array($keyword)) {
            return implode(' ', $keyword);
        }

        if (is_string($keyword)) {
            return $keyword;
        }

        throw new Exception('Invalid keyword value.');
    }

    /**
     * Get global search keyword.
     *
     * @return string
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function keyword(): string
    {
        $keyword = $this->request->input('search.value') ?? '';

        return $this->prepareKeyword($keyword);
    }

    /**
     * Get column identity from input or database.
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

    public function start(): int
    {
        /** @var int $start */
        $start = $this->request->input('start', 0);

        return $start;
    }

    public function length(): int
    {
        /** @var int $length */
        $length = $this->request->input('length', 10);

        return $length;
    }

    public function draw(): int
    {
        /** @var int $draw */
        $draw = $this->request->input('draw', 0);

        return $draw;
    }
}
