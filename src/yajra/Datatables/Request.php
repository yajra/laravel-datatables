<?php

namespace yajra\Datatables;

use Exception;
use Illuminate\Http\Request as IlluminateRequest;

/**
 * @property array columns
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
        if ( ! $this->get('draw') && $this->get('sEcho')) {
            throw new Exception('DataTables legacy code is not supported! Please use DataTables 1.10++ coding convention.');
        } elseif ( ! $this->get('draw') && ! $this->get('columns')) {
            throw new Exception('Insufficient parameters');
        }
    }

    /**
     * Check if Datatables ordering is enabled.
     *
     * @return bool
     */
    public function isOrderable()
    {
        return $this->get('order') && count($this->get('order')) > 0;
    }

    /**
     * Check if a column is orderable.
     *
     * @param  integer $index
     * @return bool
     */
    public function isColumnOrderable($index)
    {
        return $this->get('columns')[$index]['orderable'] == 'true';
    }

    /**
     * Get column name by order column index.
     *
     * @param integer $order_col
     * @return mixed
     */
    public function orderColumnName($order_col)
    {
        $column = $this->get('columns')[$order_col];
        if (isset($column['name']) && $column['name'] != '') {
            return $column['name'];
        }

        return false;
    }

    /**
     * Check if Datatables is searchable.
     *
     * @return bool
     */
    public function isSearchable()
    {
        return ! empty($this->get('search')['value']);
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
        $columns = $this->get('columns');
        if ($column_search) {
            return $columns[$i]['searchable'] == 'true' && $columns[$i]['search']['value'] != '' && ! empty($columns[$i]['name']);
        }

        return $columns[$i]['searchable'] == 'true';
    }

    /**
     * Get column's search value.
     *
     * @param integer $index
     * @return string
     */
    public function columnKeyword($index)
    {
        return $this->columns[$index]['search']['value'];
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
        for ($i = 0, $c = count($this->get('order')); $i < $c; $i++) {
            $order_col = (int) $this->get('order')[$i]['column'];
            $order_dir = $this->get('order')[$i]['dir'];
            if ($this->isColumnOrderable($order_col)) {
                $orderable[] = ['column' => $order_col, 'direction' => $order_dir];
            }
        }

        return $orderable;
    }

    /**
     * Get searchable column indexes
     *
     * @return array
     */
    public function searchableColumnIndex()
    {
        $searchable = [];
        for ($i = 0, $c = count($this->get('columns')); $i < $c; $i++) {
            if ($this->isColumnSearchable($i, false)) {
                $searchable[] = $i;
            }
        }

        return $searchable;
    }

    /**
     * Get global search keyword
     *
     * @return string
     */
    public function keyword()
    {
        return $this->get('search')['value'];
    }

    /**
     * Get column identity from input or database.
     *
     * @param integer $i
     * @return string
     */
    public function columnName($i)
    {
        return $this->get('columns')[$i]['name'];
    }

}
