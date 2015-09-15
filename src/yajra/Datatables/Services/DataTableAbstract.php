<?php

namespace yajra\Datatables\Services;

use yajra\Datatables\Datatables;

abstract class DataTableAbstract implements DataTableInterface, DataTableButtonsInterface
{
    /**
     * @var \yajra\Datatables\Datatables
     */
    protected $datatables;

    /**
     * @param \yajra\Datatables\Datatables $datatables
     */
    public function __construct(Datatables $datatables)
    {
        $this->datatables = $datatables;
    }

    /**
     * Render view.
     *
     * @param $view
     * @param array $data
     * @param array $mergeData
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function render($view, $data = [], $mergeData = [])
    {
        if ($this->datatables->request->ajax()) {
            return $this->ajax();
        }

        switch ($this->datatables->request->get('action')) {
            case 'excel':
                return $this->excel();

            case 'csv':
                return $this->csv();

            case 'pdf':
                return $this->pdf();

            case 'print':
                return $this->printPreview();

            default:
                return view($view, $data, $mergeData)->with('dataTable', $this->html());
        }
    }

    /**
     * @return \yajra\Datatables\Html\Builder
     */
    public function builder()
    {
        return $this->datatables->getHtmlBuilder();
    }

    /**
     * @return \yajra\Datatables\Request
     */
    public function request()
    {
        return $this->datatables->getRequest();
    }

    /**
     * @return string
     */
    public function excel()
    {
        return 'excel file';
    }

    /**
     * @return string
     */
    public function csv()
    {
        return 'csv file';
    }

    /**
     * @return string
     */
    public function pdf()
    {
        return 'pdf file';
    }

    /**
     * @return string
     */
    public function printPreview()
    {
        return 'print file';
    }
}
