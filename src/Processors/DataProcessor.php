<?php

namespace yajra\Datatables\Processors;

use yajra\Datatables\Helper;

/**
 * Class DataProcessor
 *
 * @package yajra\Datatables
 */
class DataProcessor
{

    /**
     * Columns to escape value.
     *
     * @var array
     */
    private $escapeColumns = [];

    /**
     * Processed data output
     *
     * @var array
     */
    private $output = [];

    /**
     * @var array
     */
    private $appendColumns = [];

    /**
     * @var array
     */
    private $editColumns = [];

    /**
     * @var array
     */
    private $excessColumns = [];

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
     * @param array $columnDef
     * @param array $templates
     */
    public function __construct($results, array $columnDef, array $templates)
    {
        $this->results       = $results;
        $this->appendColumns = $columnDef['append'];
        $this->editColumns   = $columnDef['edit'];
        $this->excessColumns = $columnDef['excess'];
        $this->escapeColumns = $columnDef['escape'];
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
            $value = $this->removeExcessColumns($value);

            $this->output[] = $object ? $value : $this->flatten($value);
        }

        return $this->escapeColumns($this->output);
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

    /**
     * Flatten array with exceptions.
     *
     * @param array $array
     * @return array
     */
    public function flatten(array $array)
    {
        $return     = [];
        $exceptions = ['DT_RowId', 'DT_RowClass', 'DT_RowData', 'DT_RowAttr'];

        foreach ($array as $key => $value) {
            if (in_array($key, $exceptions)) {
                $return[$key] = $value;
            } else {
                $return[] = $value;
            }
        }

        return $return;
    }

    /**
     * Escape column values as declared.
     *
     * @param array $output
     * @return array
     */
    protected function escapeColumns(array $output)
    {
        return array_map(function ($row) {
            if ($this->escapeColumns == '*') {
                $row = $this->escapeRow($row, $this->escapeColumns);
            } else {
                foreach ($this->escapeColumns as $key) {
                    if (array_get($row, $key)) {
                        array_set($row, $key, e(array_get($row, $key)));
                    }
                }
            }

            return $row;
        }, $output);
    }

    /**
     * Escape all values of row.
     *
     * @param array $row
     * @param string|array $escapeColumns
     * @return array
     */
    protected function escapeRow(array $row, $escapeColumns)
    {
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                $row[$key] = $this->escapeRow($value, $escapeColumns);
            } else {
                $row[$key] = e($value);
            }
        }

        return $row;
    }
}
