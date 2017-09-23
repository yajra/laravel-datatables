<?php

namespace Yajra\Datatables;

use Exception;
use Illuminate\Http\Request as IlluminateRequest;

/**
 * Class Request.
 *
 * @package Yajra\Datatables
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
class Request extends IlluminateRequest
{
    /**
     * Check if request uses legacy code
     *
     * @throws Exception
     */
    public function checkLegacyCode()
    {
        if (! $this->input('draw') && $this->input('sEcho')) {
            throw new Exception('DataTables legacy code is not supported! Please use DataTables 1.10++ coding convention.');
        } elseif (! $this->input('draw') && ! $this->input('columns')) {
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
        return $this->input('search.value') != '';
    }

    /**
     * Check if Datatables must uses regular expressions
     *
     * @param integer $index
     * @return string
     */
    public function isRegex($index)
    {
        return $this->input("columns.$index.search.regex") === 'true';
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
        for ($i = 0, $c = count($this->input('order')); $i < $c; $i++) {
            $order_col = (int) $this->input("order.$i.column");
            $order_dir = $this->input("order.$i.dir");
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
        return $this->input('order') && count($this->input('order')) > 0;
    }

    /**
     * Check if a column is orderable.
     *
     * @param  integer $index
     * @return bool
     */
    public function isColumnOrderable($index)
    {
        return $this->input("columns.$index.orderable") == 'true';
    }

    /**
     * Get searchable column indexes
     *
     * @return array
     */
    public function searchableColumnIndex()
    {
        $searchable = [];
        for ($i = 0, $c = count($this->input('columns')); $i < $c; $i++) {
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
            return filter_var($this->input("columns.$i.searchable"), FILTER_VALIDATE_BOOLEAN) && $this->columnKeyword($i) != '';
        }

        return filter_var($this->input("columns.$i.searchable"), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get column's search value.
     *
     * @param integer $index
     * @return string
     */
    public function columnKeyword($index)
    {
        return $this->input("columns.$index.search.value");
    }

    /**
     * Get global search keyword
     *
     * @return string
     */
    public function keyword()
    {
        return $this->input('search.value');
    }

    /**
     * Get column identity from input or database.
     *
     * @param integer $i
     * @return string
     */
    public function columnName($i)
    {
        $column = $this->input("columns.$i");

        return isset($column['name']) && $column['name'] <> '' ? $column['name'] : $column['data'];
    }

    /**
     * Check if Datatables allow pagination.
     *
     * @return bool
     */
    public function isPaginationable()
    {
        return ! is_null($this->input('start')) && ! is_null($this->input('length')) && $this->input('length') != -1;
    }
}
