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
        $output        = [];
        $this->results = $this->engine->results();
        foreach ($this->results as $row) {
            $data     = $row instanceof Arrayable ? $row->toArray() : (array) $row;
//            $data     = $this->convertToArray($data, $row);
            $value    = $this->addColumns($data, $row);
            $value    = $this->editColumns($value, $row);
            $output[] = $value;
        }

        if ($this->engine->m_data_support) {
            foreach ($output as $key => $value) {
                $value          = $this->setupRowVariables($key, $value);
                $this->output[] = $this->removeExcessColumns($value);
            }
        } else {
            foreach ($output as $key => $value) {
                $value          = $this->setupRowVariables($key, $value);
                $this->output[] = Arr::flatten($this->removeExcessColumns($value));
            }
        }

        return $this->output;
    }

    /**
     * Converts array object values to associative array.
     *
     * @param array $data
     * @param mixed $row
     * @return array
     */
    protected function convertToArray(array $data, $row)
    {
        $convert = [];
        foreach ($data as $key => $value) {
            if (is_object($value)) {
                $convert[$key] = $row->$key;
            } else {
                $convert[$key] = $value;
            }
        }

        return $convert;
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
            $value = Helper::compileContent($value, $data, $row);
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
            $value                = Helper::compileContent($value, $data, $row);
            $data[$value['name']] = $value['content'];
        }

        return $data;
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
        $row       = $this->results[$key];
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
    protected function removeExcessColumns(array $data)
    {
        foreach ($this->engine->excess_columns as $value) {
            unset($data[$value]);
        }

        return $data;
    }

}
