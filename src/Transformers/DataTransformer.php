<?php

namespace Yajra\Datatables\Transformers;

use Illuminate\Support\Collection;

/**
 * Class DataTransformer
 *
 * @package Yajra\Datatables\Transformers
 */
class DataTransformer
{
    /**
     * Transform row data by columns definition.
     *
     * @param array $row
     * @param mixed $columns
     * @param string $type
     * @return array
     */
    public function transform(array $row, $columns, $type = 'printable')
    {
        if ($columns instanceof Collection) {
            return $this->buildColumnByCollection($row, $columns, $type);
        }

        return array_only($row, $columns);
    }

    /**
     * Transform row column by collection.
     *
     * @param array $row
     * @param \Illuminate\Support\Collection $columns
     * @param string $type
     * @return array
     */
    protected function buildColumnByCollection(array  $row, Collection $columns, $type = 'printable')
    {
        $results = [];
        foreach ($columns->all() as $column) {
            if ($column[$type]) {
                $data = array_get($row, $column['data']);

                $results[$column['title']] = $type == 'exportable' ? strip_tags($data) : $data;
            }
        }

        return $results;
    }
}
