<?php

namespace yajra\Datatables;

use Illuminate\Support\Arr;

/**
 * Class RowProcessor
 *
 * @package yajra\Datatables
 */
class RowProcessor
{

    /**
     * @var
     */
    private $data;

    /**
     * @param $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Process DT RowId and Class value.
     *
     * @param string $attribute
     * @param string|callable $template
     * @return array
     */
    public function rowValue($attribute, $template)
    {
        if ( ! empty($template)) {
            if ( ! is_callable($template) && Arr::get($this->data, $template)) {
                $this->data[$attribute] = Arr::get($this->data, $template);
            } else {
                $this->data[$attribute] = Helper::compileContent($template, $this->data);
            }
        }

        return $this;
    }

    /**
     * Process DT Row Data and Attr.
     *
     * @param string $attribute
     * @param array $template
     * @param array $data
     * @return array
     */
    public function rowData($attribute, array $template, array $data)
    {
        if (count($template)) {
            $data[$attribute] = [];
            foreach ($template as $key => $value) {
                $data[$attribute][$key] = Helper::compileContent($value, $data, $this->data);
            }

            return $data;
        }

        return $data;
    }
}
