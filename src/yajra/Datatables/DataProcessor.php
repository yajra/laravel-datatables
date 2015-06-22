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
        foreach ($this->engine->result_array as $key => &$value) {
            $data  = $this->convertToArray($value, $key);
            $value = $this->addColumns($data, $key, $value);
            $value = $this->editColumns($data, $key, $value);
        }

        if ($this->engine->m_data_support) {
            foreach ($this->engine->result_array as $key => $value) {
                $value          = $this->setupRowVariables($key, $value);
                $this->engine->result_array_r[] = $this->removeExcessColumns($value);
            }
        } else {
            foreach ($this->engine->result_array as $key => $value) {
                $value          = $this->setupRowVariables($key, $value);
                $this->engine->result_array_r[] = Arr::flatten($this->removeExcessColumns($value));
            }
        }

        return $this->engine->result_array_r;
    }

    /**
     * Converts array object values to associative array.
     *
     * @param array $row
     * @param string|int $index
     * @return array
     */
    protected function convertToArray(array $row, $index)
    {
        $data = [];
        foreach ($row as $key => $value) {
            if (is_object($this->engine->result_object[$index])) {
                $data[$key] = $this->engine->result_object[$index]->$key;
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Process edit columns.
     *
     * @param array $data
     * @param string|int $rKey
     * @param array|null $rvalue
     * @return array
     */
    protected function editColumns(array $data, $rKey, $rvalue)
    {
        foreach ($this->engine->edit_columns as $key => $value) {
            $value                  = Helper::compileContent($value, $data, $this->engine->result_object[$rKey]);
            $rvalue[$value['name']] = $value['content'];
        }

        return $rvalue;
    }

    /**
     * Setup additional DT row variables.
     *
     * @param string $key
     * @param array $data
     * @return array
     */
    protected function setupRowVariables($key, array $data)
    {
        $row       = $this->engine->result_object[$key];
        $processor = new RowProcessor($row);
        $data      = $processor->rowValue('DT_RowId', $this->engine->row_id_tmpl, $data);
        $data      = $processor->rowValue('DT_RowClass', $this->engine->row_class_tmpl, $data);
        $data      = $processor->rowData('DT_RowData', $this->engine->row_data_tmpls, $data);
        $data      = $processor->rowData('DT_RowAttr', $this->engine->row_attr_tmpls, $data);

        return $data;
    }

    /**
     * Remove declared hidden columns.
     *
     * @param array $data
     * @return array
     */
    public function removeExcessColumns(array $data)
    {
        foreach ($this->engine->excess_columns as $value) {
            unset($data[$value]);
        }

        return $data;
    }

    /**
     * Process add columns.
     *
     * @param array $data
     * @param string|int $rKey
     * @param array|null $rValue
     * @return array
     */
    protected function addColumns(array $data, $rKey, $rValue)
    {
        foreach ($this->engine->extra_columns as $key => $value) {
            $value  = Helper::compileContent($value, $data, $this->engine->result_object[$rKey]);
            $rValue = Helper::includeInArray($value, $rValue);
        }

        return $rValue;
    }

}
