<?php

namespace Yajra\DataTables;

use Illuminate\Pagination\LengthAwarePaginator;

class PaginatorDataTable extends CollectionDataTable
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public function __construct(protected LengthAwarePaginator $paginator)
    {
        parent::__construct(collect($paginator->items()));

        $this->skipPaging();
    }

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param  mixed  $source
     */
    public static function canCreate($source): bool
    {
        return $source instanceof LengthAwarePaginator;
    }

    /**
     * Count total items from paginator.
     */
    public function totalCount(): int
    {
        return $this->totalRecords ??= $this->paginator->total();
    }

    /**
     * Skip datatable-side filtering since the source is pre-paginated.
     */
    protected function filterRecords(): void
    {
        $this->filteredRecords = $this->totalCount();
    }
}
