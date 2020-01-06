<?php

namespace Yajra\DataTables\Contracts;

interface DataTable
{
    /**
     * Get results.
     *
     * @return mixed
     */
    public function results();

    /**
     * Count results.
     *
     * @return int
     */
    public function count();

    /**
     * Count total items.
     *
     * @return int
     */
    public function totalCount();

    /**
     * Set auto filter off and run your own filter.
     * Overrides global search.
     *
     * @param callable $callback
     * @param bool     $globalSearch
     * @return $this
     */
    public function filter(callable $callback, $globalSearch = false);

    /**
     * Perform global search.
     *
     * @return void
     */
    public function filtering();

    /**
     * Perform column search.
     *
     * @return void
     */
    public function columnSearch();

    /**
     * Perform pagination.
     *
     * @return void
     */
    public function paging();

    /**
     * Perform sorting of columns.
     *
     * @return void
     */
    public function ordering();

    /**
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @param bool $useFiltering Choose whether to use built in filter functions
     * @param bool $useOrdering Choose whether to use built in order functions
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = true, $useFiltering = true, $useOrdering = true);
}
