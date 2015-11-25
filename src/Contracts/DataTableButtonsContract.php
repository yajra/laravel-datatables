<?php

namespace Yajra\Datatables\Contracts;

interface DataTableButtonsContract
{
    /**
     * Export to excel file.
     *
     * @return mixed
     */
    public function excel();

    /**
     * Export to CSV file.
     *
     * @return mixed
     */
    public function csv();

    /**
     * Export to PDF file.
     *
     * @return mixed
     */
    public function pdf();

    /**
     * Display printer friendly view.
     *
     * @return mixed
     */
    public function printPreview();
}
