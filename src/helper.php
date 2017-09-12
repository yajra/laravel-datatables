<?php

use Yajra\DataTables\DataTables;

if (!function_exists('datatables')) {
    /**
     * Helper to make a new DataTable instance from source.
     * Or return a new factory if source is not set.
     *
     * @param mixed $source
     * @return \Yajra\DataTables\DataTableAbstract|\Yajra\DataTables\DataTables
     */
    function datatables($source = null)
    {
        if ($source) {
            return DataTables::make($source);
        }

        return new DataTables;
    }
}
