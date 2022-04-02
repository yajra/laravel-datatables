<?php

namespace Yajra\DataTables\Contracts;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

interface DataTable
{
    /**
     * Get results.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    public function results(): Collection;

    /**
     * Count results.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Count total items.
     *
     * @return int
     */
    public function totalCount(): int;

    /**
     * Set auto filter off and run your own filter.
     * Overrides global search.
     *
     * @param  callable  $callback
     * @param  bool  $globalSearch
     * @return static
     */
    public function filter(callable $callback, $globalSearch = false): self;

    /**
     * Perform global search.
     *
     * @return void
     */
    public function filtering(): void;

    /**
     * Perform column search.
     *
     * @return void
     */
    public function columnSearch(): void;

    /**
     * Perform pagination.
     *
     * @return void
     */
    public function paging(): void;

    /**
     * Perform sorting of columns.
     *
     * @return void
     */
    public function ordering(): void;

    /**
     * Organizes works.
     *
     * @param  bool  $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = true): JsonResponse;
}
