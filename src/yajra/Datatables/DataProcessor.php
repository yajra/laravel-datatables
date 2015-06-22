<?php

namespace yajra\Datatables;

use Illuminate\Contracts\Support\Arrayable;
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
     * Processed data
     *
     * @var array
     */
    protected $results;

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
     * Converts result_array number indexed array and consider excess columns.
     *
     * @return array
     */
    public function process()
    {
        $this->output        = [];
        $this->results = $this->engine->results();
        foreach ($this->results as $row) {
            $data     = Helper::convertToArray($row);
            $value    = $this->addColumns($data, $row);
            $value    = $this->editColumns($value, $row);
            $value    = $this->setupRowVariables($value);
            if ( ! $this->engine->m_data_support) {
                $value = Arr::flatten($this->removeExcessColumns($value));
            } else {
                $value    = $this->removeExcessColumns($value);
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
            $data  = Helper::includeInArray($value, $data);
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
     * @param array $row
     * @return array
     */
    protected function setupRowVariables($row)
    {
        $processor = new RowProcessor($row);
        $data      = $processor->rowValue('DT_RowId', $this->engine->row_id_tmpl, $row);
        $data      = $processor->rowValue('DT_RowClass', $this->engine->row_class_tmpl, $row);
        $data      = $processor->rowData('DT_RowData', $this->engine->row_data_tmpls, $row);
        $data      = $processor->rowData('DT_RowAttr', $this->engine->row_attr_tmpls, $row);

        return $data;
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
