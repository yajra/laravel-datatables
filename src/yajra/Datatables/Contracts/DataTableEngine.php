<?php

namespace yajra\Datatables\Contracts;

interface DataTableEngine
{
    /**
     * Get results
     *
     * @return mixed
     */
    public function results();

    /**
     * Count results
     *
     * @return integer
     */
    public function count();

    /**
     * Set auto filter off and run your own filter.
     * Overrides global search
     *
     * @param \Closure $callback
     * @return $this
     */
    public function filter(\Closure $callback);

    /**
     * Perform global search
     *
     * @return void
     */
    public function filtering();

    /**
     * Perform column search
     *
     * @return void
     */
    public function columnSearch();

    /**
     * Perform pagination
     *
     * @return void
     */
    public function paging();

    /**
     * Perform sorting of columns
     *
     * @return void
     */
    public function ordering();


    /**
     * Organizes works
     *
     * @param bool $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = false);

}
