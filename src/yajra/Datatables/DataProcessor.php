<?php

namespace yajra\Datatables;

use Illuminate\Support\Arr;
use yajra\Datatables\Engines\BaseEngine;

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
     * @param \yajra\Datatables\Engines\BaseEngine $engine
     */
    public function __construct(BaseEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * Process data to output on browser
     *
     * @return array
     */
    public function process()
    {
        $this->output = [];
        foreach ((array) $this->engine->results() as $row) {
            $data  = Helper::convertToArray($row);
            $value = $this->addColumns($data, $row);
            $value = $this->editColumns($value, $row);
            $value = $this->setupRowVariables($value, $row);
            if ( ! $this->engine->m_data_support) {
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
        foreach ($this->engine->extra_columns as $key => $value) {
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
        foreach ($this->engine->edit_columns as $key => $value) {
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
            ->rowValue('DT_RowId', $this->engine->row_id_tmpl)
            ->rowValue('DT_RowClass', $this->engine->row_class_tmpl)
            ->rowData('DT_RowData', $this->engine->row_data_tmpls)
            ->rowData('DT_RowAttr', $this->engine->row_attr_tmpls)
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
        foreach ($this->engine->excess_columns as $value) {
            unset($data[$value]);
        }

        return $data;
    }

}
