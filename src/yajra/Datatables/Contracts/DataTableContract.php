<?php

namespace yajra\Datatables\Contracts;

interface DataTableContract
{
    /**
     * Render view.
     *
     * @param $view
     * @param array $data
     * @param array $mergeData
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function render($view, $data = [], $mergeData = []);

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajax();

    /**
     * @return \yajra\Datatables\Html\Builder
     */
    public function html();

    /**
     * @return \yajra\Datatables\Html\Builder
     */
    public function builder();

    /**
     * @return \yajra\Datatables\Request
     */
    public function request();

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query();
}
