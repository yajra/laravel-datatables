<?php

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Yajra\DataTables\DataTableAbstract;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Exceptions\Exception;

if (! function_exists('datatables')) {
    /**
     * Helper to make a new DataTable instance from source.
     * Or return the factory if source is not set.
     *
     * @param  Builder|Illuminate\Contracts\Database\Eloquent\Builder|Collection|array|null  $source
     * @return ($source is null ? DataTables : DataTableAbstract)
     *
     * @throws Exception
     */
    function datatables($source = null)
    {
        /** @var DataTables $dataTable */
        $dataTable = app('datatables');

        if (is_null($source)) {
            return $dataTable;
        }

        return $dataTable->make($source);
    }
}
