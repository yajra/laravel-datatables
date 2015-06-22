<?php

namespace yajra\Datatables\Engines;

/*
 * Laravel Datatables Base Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\TransformerAbstract;
use yajra\Datatables\Contracts\DataTableEngine;
use yajra\Datatables\Helper;
use yajra\Datatables\RowProcessor;

abstract class BaseEngine implements DataTableEngine
{

    /**
     * Database connection used.
     *
     * @var \Illuminate\Database\Connection
     */
    public $connection;

    /**
     * Builder object.
     *
     * @var mixed
     */
    public $query;

    /**
     * Query builder object.
     *
     * @var \Illuminate\Database\Query\Builder
     */
    public $builder;

    /**
     * Input variables.
     *
     * @var \yajra\Datatables\Request
     */
    public $request;

    /**
     * Array of result columns/fields.
     *
     * @var array
     */
    public $columns = [];

    /**
     * Array of last columns.
     *
     * @var array
     */
    public $last_columns = [];

    /**
     * Query type.
     *
     * @var string
     */
    public $query_type;

    /**
     * Array of columns to be added on result.
     *
     * @var array
     */
    public $extra_columns = [];

    /**
     * Array of columns to be removed on output.
     *
     * @var array
     */
    public $excess_columns = ['rn', 'row_num'];

    /**
     * Array of columns to be edited.
     *
     * @var array
     */
    public $edit_columns = [];

    /**
     * sColumns to output.
     *
     * @var array
     */
    public $sColumns = [];

    /**
     * Total records.
     *
     * @var int
     */
    public $totalRecords = 0;

    /**
     * Total filtered records.
     *
     * @var int
     */
    public $filteredRecords = 0;

    /**
     * Eloquent/Builder result object.
     *
     * @var mixed
     */
    public $result_object;

    /**
     * Result array.
     *
     * @var array
     */
    public $result_array = [];

    /**
     * Regulated result array.
     *
     * @var array
     */
    public $result_array_r = [];

    /**
     * Flag for DT support for mData.
     *
     * @var bool
     */
    public $m_data_support = false;

    /**
     * Auto-filter flag.
     *
     * @var bool
     */
    public $autoFilter = true;

    /**
     * DT_RowID template.
     *
     * @var string|callable
     */
    public $row_id_tmpl;

    /**
     * DT_RowClass template.
     *
     * @var string|callable
     */
    public $row_class_tmpl;

    /**
     * DT_RowData template.
     *
     * @var array
     */
    public $row_data_tmpls = [];

    /**
     * DT_RowAttr template.
     *
     * @var array
     */
    public $row_attr_tmpls = [];

    /**
     * Override column search query type.
     *
     * @var array
     */
    public $filter_columns = [];

    /**
     * Output transformer.
     *
     * @var TransformerAbstract
     */
    public $transformer = null;

    /**
     * Database prefix
     *
     * @var string
     */
    protected $prefix;

    /**
     * Database driver used
     *
     * @var string
     */
    protected $database;

    /**
     * Setup search keyword.
     *
     * @param  string $value
     * @return string
     */
    public function setupKeyword($value)
    {
        $keyword = '%' . $value . '%';
        if ($this->isWildcard()) {
            $keyword = $this->wildcardLikeString($value);
        }
        // remove escaping slash added on js script request
        $keyword = str_replace('\\', '%', $keyword);

        return $keyword;
    }

    /**
     * Get config use wild card status.
     *
     * @return bool
     */
    public function isWildcard()
    {
        return Config::get('datatables.search.use_wildcards', false);
    }

    /**
     * Adds % wildcards to the given string.
     *
     * @param string $str
     * @param bool $lowercase
     * @return string
     */
    public function wildcardLikeString($str, $lowercase = true)
    {
        $wild   = '%';
        $length = strlen($str);
        if ($length) {
            for ($i = 0; $i < $length; $i++) {
                $wild .= $str[$i] . '%';
            }
        }
        if ($lowercase) {
            $wild = Str::lower($wild);
        }

        return $wild;
    }

    /**
     * Setup column name to be use for filtering.
     *
     * @param integer $i
     * @return string
     */
    public function setupColumnName($i)
    {
        $column = $this->getColumnName($i);

        if (Str::contains(Str::upper($column), ' AS ')) {
            $column = $this->extractColumnName($column);
        }

        // there's no need to put the prefix unless the column name is prefixed with the table name.
        $column = $this->prefixColumn($column);

        return $column;
    }

    /**
     * Get column name by order column index.
     *
     * @param int $column
     * @return mixed
     */
    protected function getColumnName($column)
    {
        return $this->request->columnName($column) ?: $this->columns[$column];
    }

    /**
     * Get column name from string.
     *
     * @param string $str
     * @return string
     */
    public function extractColumnName($str)
    {
        preg_match('#^(\S*?)\s+as\s+(\S*?)$#si', $str, $matches);

        if ( ! empty($matches)) {
            return $matches[2];
        } elseif (strpos($str, '.')) {
            $array = explode('.', $str);

            return array_pop($array);
        }

        return $str;
    }

    /**
     * Will prefix column if needed.
     *
     * @param string $column
     * @return string
     */
    public function prefixColumn($column)
    {
        $table_names = $this->tableNames();
        if (count(
            array_filter($table_names, function ($value) use (&$column) {
                return strpos($column, $value . '.') === 0;
            })
        )) {
            // the column starts with one of the table names
            $column = $this->prefix . $column;
        }

        return $column;
    }

    /**
     * Will look through the query and all it's joins to determine the table names.
     *
     * @return array
     */
    public function tableNames()
    {
        $names          = [];
        $query          = $this->getQueryBuilder();
        $names[]        = $query->from;
        $joins          = $query->joins ?: [];
        $databasePrefix = $this->prefix;
        foreach ($joins as $join) {
            $table   = preg_split('/ as /i', $join->table);
            $names[] = $table[0];
            if (isset($table[1]) && ! empty($databasePrefix) && strpos($table[1], $databasePrefix) == 0) {
                $names[] = preg_replace('/^' . $databasePrefix . '/', '', $table[1]);
            }
        }

        return $names;
    }

    /**
     * Get Query Builder object.
     *
     * @return mixed
     */
    public function getQueryBuilder()
    {
        if ($this->isQueryBuilder()) {
            return $this->query;
        }

        return $this->query->getQuery();
    }

    /**
     * Check query type is a builder.
     *
     * @return bool
     */
    public function isQueryBuilder()
    {
        return $this->query_type == 'builder';
    }

    /**
     * Add column in collection.
     *
     * @param string $name
     * @param string $content
     * @param bool|int $order
     * @return $this
     */
    public function addColumn($name, $content, $order = false)
    {
        $this->sColumns[] = $name;

        $this->extra_columns[] = ['name' => $name, 'content' => $content, 'order' => $order];

        return $this;
    }

    /**
     * Edit column's content.
     *
     * @param string $name
     * @param string $content
     * @return $this
     */
    public function editColumn($name, $content)
    {
        $this->edit_columns[] = ['name' => $name, 'content' => $content];

        return $this;
    }

    /**
     * Remove column from collection.
     *
     * @return $this
     */
    public function removeColumn()
    {
        $names                = func_get_args();
        $this->excess_columns = array_merge($this->excess_columns, $names);

        return $this;
    }

    /**
     * Allows previous API calls where the methods were snake_case.
     * Will convert a camelCase API call to a snake_case call.
     *
     * @param  $name
     * @param  $arguments
     * @return $this|mixed
     */
    public function __call($name, $arguments)
    {
        $name = Str::camel(Str::lower($name));
        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $arguments);
        } elseif (method_exists($this->getQueryBuilder(), $name)) {
            call_user_func_array([$this->getQueryBuilder(), $name], $arguments);
        } else {
            trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
        }

        return $this;
    }

    /**
     * Sets DT_RowClass template
     * result: <tr class="output_from_your_template">.
     *
     * @param string|callable $content
     * @return $this
     */
    public function setRowClass($content)
    {
        $this->row_class_tmpl = $content;

        return $this;
    }

    /**
     * Sets DT_RowId template
     * result: <tr id="output_from_your_template">.
     *
     * @param string|callable $content
     * @return $this
     */
    public function setRowId($content)
    {
        $this->row_id_tmpl = $content;

        return $this;
    }

    /**
     * Set DT_RowData templates.
     *
     * @param array $data
     * @return $this
     */
    public function setRowData(array $data)
    {
        $this->row_data_tmpls = $data;

        return $this;
    }

    /**
     * Add DT_RowData template.
     *
     * @param string $key
     * @param string|callable $value
     * @return $this
     */
    public function addRowData($key, $value)
    {
        $this->row_data_tmpls[$key] = $value;

        return $this;
    }

    /**
     * Set DT_RowAttr templates
     * result: <tr attr1="attr1" attr2="attr2">.
     *
     * @param array $data
     * @return $this
     */
    public function setRowAttr(array $data)
    {
        $this->row_attr_tmpls = $data;

        return $this;
    }

    /**
     * Add DT_RowAttr template.
     *
     * @param string $key
     * @param string|callable $value
     * @return $this
     */
    public function addRowAttr($key, $value)
    {
        $this->row_attr_tmpls[$key] = $value;

        return $this;
    }

    /**
     * Override default column filter search.
     *
     * @param string $column
     * @param string $method
     * @return $this
     * @internal param $mixed ...,... All the individual parameters required for specified $method
     */
    public function filterColumn($column, $method)
    {
        $params                        = func_get_args();
        $this->filter_columns[$column] = ['method' => $method, 'parameters' => array_splice($params, 2)];

        return $this;
    }

    /**
     * Set data output transformer.
     *
     * @param \League\Fractal\TransformerAbstract $transformer
     * @return $this
     */
    public function setTransformer($transformer)
    {
        $this->transformer = $transformer;

        return $this;
    }

    /**
     * Converts all results data to array
     *
     * @param array $data
     * @return array
     */
    public function resultsToArray(array $data)
    {
        return $this->result_array = array_map(
            function ($object) {
                return $object instanceof Arrayable ? $object->toArray() : (array) $object;
            }, $data
        );
    }

    /**
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @param bool $orderFirst
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = false, $orderFirst = false)
    {
        $this->m_data_support = $mDataSupport;
        $this->totalRecords   = $this->count();

        if ($orderFirst) {
            $this->ordering();
        }

        if ($this->autoFilter && $this->request->isSearchable()) {
            $this->filtering();
        }
        $this->columnSearch();
        $this->filteredRecords = $this->count();

        if ( ! $orderFirst) {
            $this->ordering();
        }
        $this->paginate();
        $this->setResults();
        $this->initColumns();
        $this->regulateArray();

        return $this->output();

    }

    /**
     * Count results
     *
     * @return integer
     */
    public function count()
    {
    }

    /**
     * Perform sorting of columns
     *
     * @return void
     */
    public function ordering()
    {
    }

    /**
     * Perform global search
     *
     * @return void
     */
    public function filtering()
    {
    }

    /**
     * Perform column search
     *
     * @return void
     */
    public function columnSearch()
    {
    }

    public function paginate()
    {
        if ($this->request->isPaginationable()) {
            $this->paging();
        }
    }

    /**
     * Perform pagination
     *
     * @return void
     */
    public function paging()
    {
    }

    /**
     * Get engine array results
     *
     * @return array
     */
    public function setResults()
    {
    }

    /**
     * Places extra columns.
     */
    public function initColumns()
    {
        foreach ($this->result_array as $key => &$value) {
            $data  = $this->convertToArray($value, $key);
            $value = $this->processAddColumns($data, $key, $value);
            $value = $this->processEditColumns($data, $key, $value);
        }
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
            if (is_object($this->result_object[$index])) {
                $data[$key] = $this->result_object[$index]->$key;
            } else {
                $data[$key] = $value;
            }
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
    protected function processAddColumns(array $data, $rKey, $rValue)
    {
        foreach ($this->extra_columns as $key => $value) {
            $value  = Helper::compileContent($value, $data, $this->result_object[$rKey]);
            $rValue = Helper::includeInArray($value, $rValue);
        }

        return $rValue;
    }

    /**
     * Process edit columns.
     *
     * @param array $data
     * @param string|int $rKey
     * @param array|null $rvalue
     * @return array
     */
    protected function processEditColumns(array $data, $rKey, $rvalue)
    {
        foreach ($this->edit_columns as $key => $value) {
            $value                  = Helper::compileContent($value, $data, $this->result_object[$rKey]);
            $rvalue[$value['name']] = $value['content'];
        }

        return $rvalue;
    }

    /**
     * Converts result_array number indexed array and consider excess columns.
     */
    public function regulateArray()
    {
        if ($this->m_data_support) {
            foreach ($this->result_array as $key => $value) {
                $value                  = $this->setupDTRowVariables($key, $value);
                $this->result_array_r[] = $this->removeExcessColumns($value);
            }
        } else {
            foreach ($this->result_array as $key => $value) {
                $value                  = $this->setupDTRowVariables($key, $value);
                $this->result_array_r[] = Arr::flatten($this->removeExcessColumns($value));
            }
        }
    }

    /**
     * Setup additional DT row variables.
     *
     * @param string $key
     * @param array &$data
     * @return array
     */
    protected function setupDTRowVariables($key, array $data)
    {
        $row       = $this->result_object[$key];
        $processor = new RowProcessor($row);
        $data      = $processor->rowValue('DT_RowId', $this->row_id_tmpl, $data);
        $data      = $processor->rowValue('DT_RowClass', $this->row_class_tmpl, $data);
        $data      = $processor->rowData('DT_RowData', $this->row_data_tmpls, $data);
        $data      = $processor->rowData('DT_RowAttr', $this->row_attr_tmpls, $data);

        return $data;
    }

    /**
     * Remove declared excess columns.
     *
     * @param array $data
     * @return array
     */
    public function removeExcessColumns(array $data)
    {
        foreach ($this->excess_columns as $value) {
            unset($data[$value]);
        }

        return $data;
    }

    /**
     * Render json response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function output()
    {
        $output = [
            'draw'            => (int) $this->request['draw'],
            'recordsTotal'    => $this->totalRecords,
            'recordsFiltered' => $this->filteredRecords,
        ];

        if (isset($this->transformer)) {
            $fractal        = new Manager();
            $resource       = new Collection($this->result_array_r, new $this->transformer());
            $collection     = $fractal->createData($resource)->toArray();
            $output['data'] = $collection['data'];
        } else {
            $output['data'] = $this->result_array_r;
        }

        if ($this->isDebugging()) {
            $output = $this->showDebugger($output);
        }

        return new JsonResponse($output);
    }

    /**
     * Check if app is in debug mode.
     *
     * @return bool
     */
    public function isDebugging()
    {
        return Config::get('app.debug', false);
    }

    /**
     * Append debug parameters on output.
     *
     * @param  array $output
     * @return array
     */
    public function showDebugger(array $output)
    {
        $output['queries'] = $this->connection->getQueryLog();
        $output['input']   = $this->request->all();

        return $output;
    }

    /**
     * Set auto filter off and run your own filter.
     * Overrides global search
     *
     * @param \Closure $callback
     * @return $this
     */
    public function filter(\Closure $callback)
    {
    }

    /**
     * Build Query Builder Parameters.
     *
     * @return array
     */
    public function parameterize()
    {
        $args       = func_get_args();
        $parameters = [];

        if (count($args) > 1) {
            $parameters[] = $args[0];
            foreach ($args[1] as $param) {
                $parameters[] = $param;
            }
        } else {
            foreach ($args[0] as $param) {
                $parameters[] = $param;
            }
        }

        return $parameters;
    }

    /**
     * Wrap a column and cast in pgsql
     *
     * @param  string $column
     * @return string
     */
    public function castColumn($column)
    {
        $column = Helper::wrapDatabaseValue($this->database, $column);
        if ($this->database === 'pgsql') {
            $column = 'CAST(' . $column . ' as TEXT)';
        }

        return $column;
    }

    /**
     * Get config is case insensitive status.
     *
     * @return bool
     */
    public function isCaseInsensitive()
    {
        return Config::get('datatables.search.case_insensitive', false);
    }
}
