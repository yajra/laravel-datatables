<?php

namespace Rafaelqm\Datatables\Services;

use Illuminate\Contracts\View\Factory;
use Maatwebsite\Excel\Classes\LaravelExcelWorksheet;
use Maatwebsite\Excel\Writers\LaravelExcelWriter;
use Rafaelqm\Datatables\Contracts\DataTableButtonsContract;
use Rafaelqm\Datatables\Contracts\DataTableContract;
use Rafaelqm\Datatables\Contracts\DataTableScopeContract;
use Rafaelqm\Datatables\Datatables;
use Rafaelqm\Datatables\Transformers\DataTransformer;

abstract class DataTable implements DataTableContract, DataTableButtonsContract
{
    /**
     * @var \Rafaelqm\Datatables\Datatables
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
     * List of columns to be printed.
     *
     * @var string|array
     */
    protected $printColumns = '*';

    /**
     * Query scopes.
     *
     * @var \Rafaelqm\Datatables\Contracts\DataTableScopeContract[]
     */
    protected $scopes = [];

    /**
     * Export filename.
     *
     * @var string
     */
    protected $filename = '';

    /**
     * @param \Rafaelqm\Datatables\Datatables $datatables
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
        if ($this->request()->ajax() && $this->request()->wantsJson()) {
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
     * Get Datatables Request instance.
     *
     * @return \Rafaelqm\Datatables\Request
     */
    public function request()
    {
        return $this->datatables->getRequest();
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
        return app('excel')->create($this->getFilename(), function (LaravelExcelWriter $excel) {
            $excel->sheet('exported-data', function (LaravelExcelWorksheet $sheet) {
                $sheet->fromArray($this->getDataForExport());
            });
        });
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'export_' . time();
    }

    /**
     * Get mapped columns versus final decorated output.
     *
     * @return array
     */
    protected function getDataForExport()
    {
        $columns = $this->exportColumns();

        return $this->mapResponseToColumns($columns, 'exportable');
    }

    /**
     * Get export columns definition.
     *
     * @return array|string
     */
    private function exportColumns()
    {
        return is_array($this->exportColumns) ? $this->exportColumns : $this->getColumnsFromBuilder();
    }

    /**
     * Get columns definition from html builder.
     *
     * @return array
     */
    protected function getColumnsFromBuilder()
    {
        return $this->html()->getColumns();
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Rafaelqm\Datatables\Html\Builder
     */
    public function html()
    {
        return $this->builder();
    }

    /**
     * Get Datatables Html Builder instance.
     *
     * @return \Rafaelqm\Datatables\Html\Builder
     */
    public function builder()
    {
        return $this->datatables->getHtmlBuilder();
    }

    /**
     * Map ajax response to columns definition.
     *
     * @param mixed $columns
     * @param string $type
     * @return array
     */
    protected function mapResponseToColumns($columns, $type)
    {
        return array_map(function ($row) use ($columns, $type) {
            if ($columns) {
                return (new DataTransformer())->transform($row, $columns, $type);
            }

            return $row;
        }, $this->getAjaxResponseData());
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
        $data = $this->getDataForPrint();
        $view = $this->printPreview ?: 'datatables::print';

        return $this->viewFactory->make($view, compact('data'));
    }

    /**
     * Get mapped columns versus final decorated output.
     *
     * @return array
     */
    protected function getDataForPrint()
    {
        $columns = $this->printColumns();

        return $this->mapResponseToColumns($columns, 'printable');
    }

    /**
     * Get printable columns.
     *
     * @return array|string
     */
    protected function printColumns()
    {
        return is_array($this->printColumns) ? $this->printColumns : $this->getColumnsFromBuilder();
    }

    /**
     * Add basic array query scopes.
     *
     * @param \Rafaelqm\Datatables\Contracts\DataTableScopeContract $scope
     * @return $this
     */
    public function addScope(DataTableScopeContract $scope)
    {
        $this->scopes[] = $scope;

        return $this;
    }

    /**
     * Get export filename.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename ?: $this->filename();
    }

    /**
     * Set export filename.
     *
     * @param string $filename
     * @return DataTable
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Apply query scopes.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @return mixed
     */
    protected function applyScopes($query)
    {
        foreach ($this->scopes as $scope) {
            $scope->apply($query);
        }

        return $query;
    }

    /**
     * Get default builder parameters.
     *
     * @return array
     */
    protected function getBuilderParameters()
    {
        return [
            'order'   => [[0, 'desc']],
            'buttons' => [
                'create',
                [
                    'extend'  => 'collection',
                    'text'    => '<i class="fa fa-download"></i> Export',
                    'buttons' => [
                        'csv',
                        'excel',
                        'pdf',
                    ],
                ],
                'print',
                'reset',
                'reload',
            ],
        ];
    }
}
