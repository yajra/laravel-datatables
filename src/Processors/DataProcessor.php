<?php

namespace Yajra\DataTables\Processors;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Yajra\DataTables\Contracts\Formatter;
use Yajra\DataTables\Utilities\Helper;

class DataProcessor
{
    /**
     * @var int
     */
    protected int $start;
    /**
     * @var array
     */
    protected array $output = [];

    /**
     * @var array<array-key, array{name: string, content: mixed}>
     */
    protected array $appendColumns = [];

    /**
     * @var array<array-key, array{name: string, content: mixed}>
     */
    protected array $editColumns = [];

    /**
     * @var array
     */
    protected array $templates = [];

    /**
     * @var array
     */
    protected array $rawColumns = [];

    /**
     * @var array|string[]
     */
    protected array $exceptions = ['DT_RowId', 'DT_RowClass', 'DT_RowData', 'DT_RowAttr'];

    /**
     * @var array
     */
    protected array $onlyColumns = [];

    /**
     * @var array
     */
    protected array $makeHidden = [];

    /**
     * @var array
     */
    protected array $makeVisible = [];

    /**
     * @var array
     */
    protected array $excessColumns = [];

    /**
     * @var string|array
     */
    protected mixed $escapeColumns = [];

    /**
     * @var iterable
     */
    protected iterable $results;

    /**
     * @var bool
     */
    protected bool $includeIndex = false;

    /**
     * @param  iterable  $results
     * @param  array  $columnDef
     * @param  array  $templates
     * @param  int  $start
     */
    public function __construct($results, array $columnDef, array $templates, int $start = 0)
    {
        $this->results = $results;
        $this->appendColumns = $columnDef['append'] ?? [];
        $this->editColumns = $columnDef['edit'] ?? [];
        $this->excessColumns = $columnDef['excess'] ?? [];
        $this->onlyColumns = $columnDef['only'] ?? [];
        $this->escapeColumns = $columnDef['escape'] ?? [];
        $this->includeIndex = $columnDef['index'] ?? false;
        $this->rawColumns = $columnDef['raw'] ?? [];
        $this->makeHidden = $columnDef['hidden'] ?? [];
        $this->makeVisible = $columnDef['visible'] ?? [];
        $this->templates = $templates;
        $this->start = $start;
    }

    /**
     * Process data to output on browser.
     *
     * @param  bool  $object
     * @return array
     */
    public function process($object = false): array
    {
        $this->output = [];
        $indexColumn = config('datatables.index_column', 'DT_RowIndex');

        foreach ($this->results as $row) {
            $data = Helper::convertToArray($row, ['hidden' => $this->makeHidden, 'visible' => $this->makeVisible]);
            $value = $this->addColumns($data, $row);
            $value = $this->editColumns($value, $row);
            $value = $this->setupRowVariables($value, $row);
            $value = $this->selectOnlyNeededColumns($value);
            $value = $this->removeExcessColumns($value);

            if ($this->includeIndex) {
                $value[$indexColumn] = ++$this->start;
            }

            $this->output[] = $object ? $value : $this->flatten($value);
        }

        return $this->escapeColumns($this->output);
    }

    /**
     * Process add columns.
     *
     * @param  array  $data
     * @param  array|object|\Illuminate\Database\Eloquent\Model  $row
     * @return array
     */
    protected function addColumns(array $data, $row): array
    {
        foreach ($this->appendColumns as $value) {
            $content = $value['content'];
            if ($content instanceof Formatter) {
                $column = str_replace('_formatted', '', $value['name']);

                $value['content'] = $content->format($data[$column], $row);
                if (isset($data[$column])) {
                    $value['content'] = $content->format($data[$column], $row);
                }
            } else {
                $value['content'] = Helper::compileContent($content, $data, $row);
            }

            $data = Helper::includeInArray($value, $data);
        }

        return $data;
    }

    /**
     * Process edit columns.
     *
     * @param  array  $data
     * @param  array|object  $row
     * @return array
     */
    protected function editColumns(array $data, object|array $row): array
    {
        foreach ($this->editColumns as $value) {
            $value['content'] = Helper::compileContent($value['content'], $data, $row);
            Arr::set($data, $value['name'], $value['content']);
        }

        return $data;
    }

    /**
     * Setup additional DT row variables.
     *
     * @param  array  $data
     * @param  array|object  $row
     * @return array
     */
    protected function setupRowVariables(array $data, object|array $row): array
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
     * Get only needed columns.
     *
     * @param  array  $data
     * @return array
     */
    protected function selectOnlyNeededColumns(array $data): array
    {
        if (empty($this->onlyColumns)) {
            return $data;
        } else {
            $results = [];
            foreach ($this->onlyColumns as $onlyColumn) {
                Arr::set($results, $onlyColumn, Arr::get($data, $onlyColumn));
            }
            foreach ($this->exceptions as $exception) {
                if ($column = Arr::get($data, $exception)) {
                    Arr::set($results, $exception, $column);
                }
            }

            return $results;
        }
    }

    /**
     * Remove declared hidden columns.
     *
     * @param  array  $data
     * @return array
     */
    protected function removeExcessColumns(array $data): array
    {
        foreach ($this->excessColumns as $value) {
            Arr::forget($data, $value);
        }

        return $data;
    }

    /**
     * Flatten array with exceptions.
     *
     * @param  array  $array
     * @return array
     */
    public function flatten(array $array): array
    {
        $return = [];
        foreach ($array as $key => $value) {
            if (in_array($key, $this->exceptions)) {
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
     * @param  array  $output
     * @return array
     */
    protected function escapeColumns(array $output): array
    {
        return array_map(function ($row) {
            if ($this->escapeColumns == '*') {
                $row = $this->escapeRow($row);
            } elseif (is_array($this->escapeColumns)) {
                $columns = array_diff($this->escapeColumns, $this->rawColumns);
                foreach ($columns as $key) {
                    /** @var string $content */
                    $content = Arr::get($row, $key);
                    Arr::set($row, $key, e($content));
                }
            }

            return $row;
        }, $output);
    }

    /**
     * Escape all string or Htmlable values of row.
     *
     * @param  array  $row
     * @return array
     */
    protected function escapeRow(array $row): array
    {
        $arrayDot = array_filter(Arr::dot($row));
        foreach ($arrayDot as $key => $value) {
            if (! in_array($key, $this->rawColumns)) {
                $arrayDot[$key] = (is_string($value) || $value instanceof Htmlable) ? e($value) : $value;
            }
        }

        foreach ($arrayDot as $key => $value) {
            Arr::set($row, $key, $value);
        }

        return $row;
    }
}
