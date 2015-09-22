<?php

namespace yajra\Datatables\Services;

use Illuminate\Contracts\View\Factory;
use yajra\Datatables\Contracts\DataTableButtonsContract;
use yajra\Datatables\Contracts\DataTableContract;
use yajra\Datatables\Contracts\DataTableScopeContract;
use yajra\Datatables\Datatables;

abstract class DataTable implements DataTableContract, DataTableButtonsContract
{
    /**
     * @var \yajra\Datatables\Datatables
     */
    protected $datatables;

    /**
     * @var \Illuminate\Contracts\View\Factory
     */
    protected $viewFactory;

    /**
     * Datatables print preview view.
     *
     * @var string
     */
    protected $printPreview;

    /**
     * List of columns to be exported.
     *
     * @var string|array
     */
    protected $exportColumns = '*';

    /**
     * Query scopes.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * @param \yajra\Datatables\Datatables $datatables
     * @param \Illuminate\Contracts\View\Factory $viewFactory
     */
    public function __construct(Datatables $datatables, Factory $viewFactory)
    {
        $this->datatables  = $datatables;
        $this->viewFactory = $viewFactory;
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
        if ($this->datatables->getRequest()->ajax()) {
            return $this->ajax();
        }

        switch ($this->datatables->getRequest()->get('action')) {
            case 'excel':
                return $this->excel();

            case 'csv':
                return $this->csv();

            case 'pdf':
                return $this->pdf();

            case 'print':
                return $this->printPreview();

            default:
                return $this->viewFactory->make($view, $data, $mergeData)->with('dataTable', $this->html());
        }
    }

    /**
     * Export results to Excel file.
     *
     * @return mixed
     */
    public function excel()
    {
        return $this->buildExcelFile()->download('xls');
    }

    /**
     * Build excel file and prepare for export.
     *
     * @return mixed
     */
    protected function buildExcelFile()
    {
        return app('excel')->create('export', function ($excel) {
            $excel->sheet('exported-data', function ($sheet) {
                $sheet->fromArray($this->getDataForExport());
            });
        });
    }

    /**
     * Get mapped columns versus final decorated output.
     *
     * @return array
     */
    protected function getDataForExport()
    {
        $decoratedData = $this->getAjaxResponseData();

        return array_map(function ($row) {
            if (is_array($this->exportColumns)) {
                return array_only($row, $this->exportColumns);
            }

            return $row;
        }, $decoratedData);
    }

    /**
     * Get decorated data as defined in datatables ajax response.
     *
     * @return mixed
     */
    protected function getAjaxResponseData()
    {
        $this->datatables->getRequest()->merge(['length' => -1]);

        $response = $this->ajax();
        $data     = $response->getData(true);

        return $data['data'];
    }

    /**
     * Export results to CSV file.
     *
     * @return mixed
     */
    public function csv()
    {
        return $this->buildExcelFile()->download('csv');
    }

    /**
     * Export results to PDF file.
     *
     * @return mixed
     */
    public function pdf()
    {
        return $this->buildExcelFile()->download('pdf');
    }

    /**
     * Display printable view of datatables.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function printPreview()
    {
        $data = $this->getAjaxResponseData();
        $view = $this->printPreview ?: 'datatables::print';

        return $this->viewFactory->make($view, compact('data'));
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return mixed
     */
    public function html()
    {
        return $this->builder();
    }

    /**
     * Get Datatables Html Builder instance.
     *
     * @return \yajra\Datatables\Html\Builder
     */
    public function builder()
    {
        return $this->datatables->getHtmlBuilder();
    }

    /**
     * Get Datatables Request instance.
     *
     * @return \yajra\Datatables\Request
     */
    public function request()
    {
        return $this->datatables->getRequest();
    }

    /**
     * Add basic array query scopes.
     *
     * @param \yajra\Datatables\Contracts\DataTableScopeContract $scope
     * @return $this
     */
    public function addScope(DataTableScopeContract $scope)
    {
        $this->scopes[] = $scope;

        return $this;
    }

    /**
     * Apply query scopes.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @return mixed
     */
    public function applyScopes($query)
    {
        foreach ($this->scopes as $scope) {
            $scope->apply($query);
        }

        return $query;
    }
}
