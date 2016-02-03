<?php

namespace Rafaelqm\Datatables\Transformers;

use Illuminate\Support\Collection;

/**
 * Class DataTransformer
 *
 * @package Rafaelqm\Datatables\Transformers
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
                $title = $column['title'];
                $data  = array_get($row, $column['data']);
                if ($type == 'exportable') {
                    $data  = $this->decodeContent($data);
                    $title = $this->decodeContent($title);
                }

                $results[$title] = $data;
            }
        }

        return $results;
    }

    /**
     * Decode content to a readable text value.
     *
     * @param string $data
     * @return string
     */
    protected function decodeContent($data)
    {
        $decoded = html_entity_decode(strip_tags($data), ENT_QUOTES, 'UTF-8');

        return str_replace("\xc2\xa0", ' ', $decoded);
    }
}
