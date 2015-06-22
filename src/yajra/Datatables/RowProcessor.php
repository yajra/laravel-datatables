<?php

namespace yajra\Datatables;

use Illuminate\Support\Arr;

class RowProcessor
{

    private $data;

    function __construct($data)
    {
        $this->data = $data;
    }


    /**
     * Process DT RowId and Class value.
     *
     * @param string $attribute
     * @param string|callable $template
     * @param array $data
     * @return array
     */
    public function rowValue($attribute, $template, array $data)
    {
        if ( ! empty($template)) {
            if ( ! is_callable($template) && Arr::get($data, $template)) {
                $data[$attribute] = Arr::get($data, $template);
            } else {
                $data[$attribute] = Helper::getContent($template, $data, $this->data);
            }
        }

        return $data;
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
                $data[$attribute][$key] = Helper::getContent($value, $data, $this->data);
            }

            return $data;
        }

        return $data;
    }
}
