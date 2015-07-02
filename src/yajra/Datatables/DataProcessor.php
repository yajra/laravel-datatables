<?php

namespace yajra\Datatables;

use Illuminate\Support\Arr;

/**
 * Class DataProcessor
 *
 * @package yajra\Datatables
 */
class DataProcessor
{

    /**
     * @var \yajra\Datatables\Engines\BaseEngine
     */
    protected $engine;

    /**
     * Processed data output
     *
     * @var array
     */
    private $output = [];

    /**
     * @var array
     */
    private $appendColumns;

    /**
     * @var array
     */
    private $editColumns;

    /**
     * @var array
     */
    private $excessColumns;

    /**
     * @var mixed
     */
    private $results;

    /**
     * @var array
     */
    private $templates;

    /**
     * @param mixed $results
     * @param array $append
     * @param array $edit
     * @param array $excess
     * @param array $templates
     */
    public function __construct($results, array $append, array $edit, array $excess, array $templates)
    {
        $this->results       = $results;
        $this->appendColumns = $append;
        $this->editColumns   = $edit;
        $this->excessColumns = $excess;
        $this->templates     = $templates;
    }

    /**
     * Process data to output on browser
     *
     * @param bool $object
     * @return array
     */
    public function process($object = false)
    {
        $this->output = [];
        foreach ($this->results as $row) {
            $data  = Helper::convertToArray($row);
            $value = $this->addColumns($data, $row);
            $value = $this->editColumns($value, $row);
            $value = $this->setupRowVariables($value, $row);
            if ( ! $object) {
                $value = Arr::flatten($this->removeExcessColumns($value));
            } else {
                $value = $this->removeExcessColumns($value);
            }
            $this->output[] = $value;
        }

        return $this->output;
    }

    /**
     * Process add columns.
     *
     * @param array $data
     * @param mixed $row
     * @return array
     */
    protected function addColumns(array $data, $row)
    {
        foreach ($this->appendColumns as $key => $value) {
            $value['content'] = Helper::compileContent($value['content'], $data, $row);
            $data             = Helper::includeInArray($value, $data);
        }

        return $data;
    }

    /**
     * Process edit columns.
     *
     * @param array $data
     * @param mixed $row
     * @return array
     */
    protected function editColumns(array $data, $row)
    {
        foreach ($this->editColumns as $key => $value) {
            $value['content']     = Helper::compileContent($value['content'], $data, $row);
            $data[$value['name']] = $value['content'];
        }

        return $data;
    }

    /**
     * Setup additional DT row variables.
     *
     * @param mixed $data
     * @param mixed $row
     * @return array
     */
    protected function setupRowVariables($data, $row)
    {
        $processor = new RowProcessor($data, $row);

        return $processor
            ->rowValue('DT_RowId', $this->templates['DT_RowId'])
            ->rowValue('DT_RowClass', $this->templates['DT_RowClass'])
            ->rowData('DT_RowData', $this->templates['DT_RowData'])
            ->rowData('DT_RowAttr', $this->templates['DT_RowAttr'])
            ->getData();
    }

    /**
     * Remove declared hidden columns.
     *
     * @param array $data
     * @return array
     */
    protected function removeExcessColumns(array $data)
    {
        foreach ($this->excessColumns as $value) {
            unset($data[$value]);
        }

        return $data;
    }

}
