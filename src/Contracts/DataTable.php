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
     */
    public function count(): int;

    /**
     * Count total items.
     */
    public function totalCount(): int;

    /**
     * Set auto filter off and run your own filter.
     * Overrides global search.
     *
     * @param  bool  $globalSearch
     * @return static
     */
    public function filter(callable $callback, $globalSearch = false): self;

    /**
     * Perform global search.
     */
    public function filtering(): void;

    /**
     * Perform column search.
     */
    public function columnSearch(): void;

    /**
     * Perform pagination.
     */
    public function paging(): void;

    /**
     * Perform sorting of columns.
     */
    public function ordering(): void;

    /**
     * Organizes works.
     *
     * @param  bool  $mDataSupport
     */
    public function make($mDataSupport = true): JsonResponse;
}
