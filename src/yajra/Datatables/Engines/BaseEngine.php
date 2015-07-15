<?php

namespace yajra\Datatables\Engines;

/*
 * Laravel Datatables Base Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\TransformerAbstract;
use yajra\Datatables\Contracts\DataTableEngine;
use yajra\Datatables\Helper;
use yajra\Datatables\Processors\DataProcessor;

abstract class BaseEngine implements DataTableEngine
{

    /**
     * Datatables Request object.
     *
     * @var \yajra\Datatables\Request
     */
    public $request;

    /**
     * Database connection used.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $connection;

    /**
     * Builder object.
     *
     * @var mixed
     */
    protected $query;

    /**
     * Query builder object.
     *
     * @var \Illuminate\Database\Query\Builder
     */
    protected $builder;

    /**
     * Array of result columns/fields.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * DT columns definitions container (add/edit/remove/filter/order).
     *
     * @var array
     */
    protected $columnDef = [
        'append' => [],
        'edit'   => [],
        'excess' => ['rn', 'row_num'],
        'filter' => [],
        'order'  => [],
    ];

    /**
     * Query type.
     *
     * @var string
     */
    protected $query_type;


    /**
     * sColumns to output.
     *
     * @var array
     */
    protected $sColumns = [];

    /**
     * Total records.
     *
     * @var int
     */
    protected $totalRecords = 0;

    /**
     * Total filtered records.
     *
     * @var int
     */
    protected $filteredRecords = 0;

    /**
     * Auto-filter flag.
     *
     * @var bool
     */
    protected $autoFilter = true;

    /**
     * Callback to override global search
     *
     * @var \Closure
     */
    protected $filterCallback;

    /**
     * Parameters to passed on filterCallback
     *
     * @var mixed
     */
    protected $filterCallbackParameters;

    /**
     * DT row templates container
     *
     * @var array
     */
    protected $templates = [
        'DT_RowId'    => '',
        'DT_RowClass' => '',
        'DT_RowData'  => [],
        'DT_RowAttr'  => [],
    ];

    /**
     * Output transformer.
     *
     * @var TransformerAbstract
     */
    protected $transformer = null;

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
     * [internal] Track if any filter was applied for at least one column
     *
     * @var boolean
     */
    protected $isFilterApplied = false;

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
     * @param mixed $instance
     * @return mixed
     */
    public function getQueryBuilder($instance = null)
    {
        if ( ! $instance) {
            $instance = $this->query;
        }

        if ($this->isQueryBuilder()) {
            return $instance;
        }

        return $instance->getQuery();
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

        $this->columnDef['append'][] = ['name' => $name, 'content' => $content, 'order' => $order];

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
        $this->columnDef['edit'][] = ['name' => $name, 'content' => $content];

        return $this;
    }

    /**
     * Remove column from collection.
     *
     * @return $this
     */
    public function removeColumn()
    {
        $names                     = func_get_args();
        $this->columnDef['excess'] = array_merge($this->columnDef['excess'], $names);

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
        $this->templates['DT_RowClass'] = $content;

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
        $this->templates['DT_RowId'] = $content;

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
        $this->templates['DT_RowData'] = $data;

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
        $this->templates['DT_RowData'][$key] = $value;

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
        $this->templates['DT_RowAttr'] = $data;

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
        $this->templates['DT_RowAttr'][$key] = $value;

        return $this;
    }

    /**
     * Override default column filter search.
     *
     * @param string $column
     * @param string $method
     * @return $this
     * @internal param $mixed ...,... All the individual parameters required for specified $method
     * @internal string $1 Special variable that returns the requested search keyword.
     */
    public function filterColumn($column, $method)
    {
        $params                             = func_get_args();
        $this->columnDef['filter'][$column] = ['method' => $method, 'parameters' => array_splice($params, 2)];

        return $this;
    }

    /**
     * Override default column ordering.
     *
     * @param string $column
     * @param string $sql
     * @param array $bindings
     * @return $this
     * @internal string $1 Special variable that returns the requested order direction of the column.
     */
    public function orderColumn($column, $sql, $bindings = [])
    {
        $this->columnDef['order'][$column] = ['method' => 'orderByRaw', 'parameters' => [$sql, $bindings]];

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
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @param bool $orderFirst
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = false, $orderFirst = false)
    {
        $this->totalRecords = $this->count();

        $this->orderRecords(! $orderFirst);
        $this->filterRecords();
        $this->orderRecords($orderFirst);
        $this->paginate();

        return $this->render($mDataSupport);
    }

    /**
     * Count results.
     *
     * @return integer
     */
    abstract public function count();

    /**
     * Sort records.
     *
     * @param  boolean $skip
     * @return void
     */
    public function orderRecords($skip)
    {
        if ( ! $skip) {
            $this->ordering();
        }
    }

    /**
     * Perform sorting of columns.
     *
     * @return void
     */
    abstract public function ordering();

    /**
     * Perform necessary filters.
     *
     * @return void
     */
    public function filterRecords()
    {
        if ($this->autoFilter && $this->request->isSearchable()) {
            $this->filtering();
        } else {
            if (is_callable($this->filterCallback)) {
                call_user_func($this->filterCallback, $this->filterCallbackParameters);
            }
        }

        $this->columnSearch();
        $this->filteredRecords = $this->isFilterApplied ? $this->count() : $this->totalRecords;
    }

    /**
     * Perform global search.
     *
     * @return void
     */
    abstract public function filtering();

    /**
     * Perform column search.
     *
     * @return void
     */
    abstract public function columnSearch();

    /**
     * Apply pagination.
     *
     * @return void
     */
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
    abstract public function paging();

    /**
     * Render json response.
     *
     * @param bool $object
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($object = false)
    {
        $processor = new DataProcessor(
            $this->results(),
            $this->columnDef,
            $this->templates
        );

        $data   = $processor->process($object);
        $output = [
            'draw'            => (int) $this->request['draw'],
            'recordsTotal'    => $this->totalRecords,
            'recordsFiltered' => $this->filteredRecords,
        ];

        if (isset($this->transformer)) {
            $fractal        = new Manager();
            $resource       = new Collection($data, new $this->transformer());
            $collection     = $fractal->createData($resource)->toArray();
            $output['data'] = $collection['data'];
        } else {
            $output['data'] = Helper::transform($data);
        }

        if ($this->isDebugging()) {
            $output = $this->showDebugger($output);
        }

        return new JsonResponse($output);
    }

    /**
     * Get results
     *
     * @return array
     */
    abstract public function results();

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
    abstract public function filter(\Closure $callback);

    /**
     * Update flags to disable global search
     *
     * @param  \Closure $callback
     * @param  mixed $parameters
     * @return void
     */
    public function overrideGlobalSearch(\Closure $callback, $parameters)
    {
        $this->autoFilter               = false;
        $this->isFilterApplied          = true;
        $this->filterCallback           = $callback;
        $this->filterCallbackParameters = $parameters;
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
