<?php

namespace Yajra\Datatables;

use Exception;
use Illuminate\Http\Request as IlluminateRequest;

/**
 * Class Request.
 *
 * @package Yajra\Datatables
 * @method input($key, $default = null)
 * @method has($key)
 * @method query($key, $default = null)
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
class Request
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Request constructor.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(IlluminateRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Proxy non existing method calls to request class.
     *
     * @param mixed $name
     * @param mixed $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->request, $name)) {
            return call_user_func_array([$this->request, $name], $arguments);
        }

        return null;
    }

    /**
     * Get attributes from request instance.
     *
     * @param string $name
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
     * Check if request uses legacy code
     *
     * @throws Exception
     */
    public function checkLegacyCode()
    {
        if (! $this->request->input('draw') && $this->request->input('sEcho')) {
            throw new Exception('DataTables legacy code is not supported! Please use DataTables 1.10++ coding convention.');
        } elseif (! $this->request->input('draw') && ! $this->request->input('columns')) {
            throw new Exception('Insufficient parameters');
        }
    }

    /**
     * Check if Datatables is searchable.
     *
     * @return bool
     */
    public function isSearchable()
    {
        return $this->request->input('search.value') != '';
    }

    /**
     * Check if Datatables must uses regular expressions
     *
     * @param integer $index
     * @return string
     */
    public function isRegex($index)
    {
        return $this->request->input("columns.$index.search.regex") === 'true';
    }

    /**
     * Get orderable columns
     *
     * @return array
     */
    public function orderableColumns()
    {
        if (! $this->isOrderable()) {
            return [];
        }

        $orderable = [];
        for ($i = 0, $c = count($this->request->input('order')); $i < $c; $i++) {
            $order_col = (int) $this->request->input("order.$i.column");
            $order_dir = $this->request->input("order.$i.dir");
            if ($this->isColumnOrderable($order_col)) {
                $orderable[] = ['column' => $order_col, 'direction' => $order_dir];
            }
        }

        return $orderable;
    }

    /**
     * Check if Datatables ordering is enabled.
     *
     * @return bool
     */
    public function isOrderable()
    {
        return $this->request->input('order') && count($this->request->input('order')) > 0;
    }

    /**
     * Check if a column is orderable.
     *
     * @param  integer $index
     * @return bool
     */
    public function isColumnOrderable($index)
    {
        return $this->request->input("columns.$index.orderable", "true") == 'true';
    }

    /**
     * Get searchable column indexes
     *
     * @return array
     */
    public function searchableColumnIndex()
    {
        $searchable = [];
        for ($i = 0, $c = count($this->request->input('columns')); $i < $c; $i++) {
            if ($this->isColumnSearchable($i, false)) {
                $searchable[] = $i;
            }
        }

        return $searchable;
    }

    /**
     * Check if a column is searchable.
     *
     * @param integer $i
     * @param bool $column_search
     * @return bool
     */
    public function isColumnSearchable($i, $column_search = true)
    {
        if ($column_search) {
            return $this->request->input("columns.$i.searchable", "true") === 'true' && $this->columnKeyword($i) != '';
        }

        return $this->request->input("columns.$i.searchable", "true") === 'true';
    }

    /**
     * Get column's search value.
     *
     * @param integer $index
     * @return string
     */
    public function columnKeyword($index)
    {
        return $this->request->input("columns.$index.search.value");
    }

    /**
     * Get global search keyword
     *
     * @return string
     */
    public function keyword()
    {
        return $this->request->input('search.value');
    }

    /**
     * Get column identity from input or database.
     *
     * @param integer $i
     * @return string
     */
    public function columnName($i)
    {
        $column = $this->request->input("columns.$i");

        return isset($column['name']) && $column['name'] <> '' ? $column['name'] : $column['data'];
    }

    /**
     * Check if Datatables allow pagination.
     *
     * @return bool
     */
    public function isPaginationable()
    {
        return ! is_null($this->request->input('start')) && ! is_null($this->request->input('length')) && $this->request->input('length') != -1;
    }
}
