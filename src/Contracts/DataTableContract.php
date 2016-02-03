<?php

namespace Rafaelqm\Datatables\Contracts;

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
     * @return \Rafaelqm\Datatables\Html\Builder
     */
    public function html();

    /**
     * @return \Rafaelqm\Datatables\Html\Builder
     */
    public function builder();

    /**
     * @return \Rafaelqm\Datatables\Request
     */
    public function request();

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query();
}
