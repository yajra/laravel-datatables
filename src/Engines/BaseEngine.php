<?php

namespace Yajra\Datatables\Engines;

use Illuminate\Contracts\Logging\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use League\Fractal\Resource\Collection;
use Yajra\Datatables\Contracts\DataTableEngineContract;
use Yajra\Datatables\Exception;
use Yajra\Datatables\Helper;
use Yajra\Datatables\Processors\DataProcessor;

/**
 * Class BaseEngine.
 *
 * @package Yajra\Datatables\Engines
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
abstract class BaseEngine implements DataTableEngineContract
{
    /**
     * Datatables Request object.
     *
     * @var \Yajra\Datatables\Request
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
     * @var \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    protected $query;

    /**
     * Query builder object.
     *
     * @var \Illuminate\Database\Query\Builder
     */
    protected $builder;

    /**
     * @var \Illuminate\Contracts\Logging\Log
     */
    protected $logger;

    /**
     * Array of result columns/fields.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * DT columns definitions container (add/edit/remove/filter/order/escape).
     *
     * @var array
     */
    protected $columnDef = [
        'index'     => false,
        'append'    => [],
        'edit'      => [],
        'excess'    => ['rn', 'row_num'],
        'filter'    => [],
        'order'     => [],
        'escape'    => '*',
        'raw'       => ['action'],
        'blacklist' => ['password', 'remember_token'],
        'whitelist' => '*',
    ];

    /**
     * Query type.
     *
     * @var string
     */
    protected $query_type;

    /**
     * Extra/Added columns.
     *
     * @var array
     */
    protected $extraColumns = [];

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
     * Callback to override global search.
     *
     * @var callable
     */
    protected $filterCallback;

    /**
     * Parameters to passed on filterCallback.
     *
     * @var mixed
     */
    protected $filterCallbackParameters;

    /**
     * DT row templates container.
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
     * @var \League\Fractal\TransformerAbstract
     */
    protected $transformer = null;

    /**
     * Database prefix
     *
     * @var string
     */
    protected $prefix;

    /**
     * Database driver used.
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
     * Fractal serializer class.
     *
     * @var string|null
     */
    protected $serializer = null;

    /**
     * Custom ordering callback.
     *
     * @var callable
     */
    protected $orderCallback;

    /**
     * Skip paginate as needed.
     *
     * @var bool
     */
    protected $skipPaging = false;

    /**
     * Array of data to append on json response.
     *
     * @var array
     */
    protected $appends = [];

    /**
     * Flag for ordering NULLS LAST option.
     *
     * @var bool
     */
    protected $nullsLast = false;

    /**
     * Add column in collection.
     *
     * @param string $name
     * @param string|callable $content
     * @param bool|int $order
     * @return $this
     */
    public function addColumn($name, $content, $order = false)
    {
        $this->extraColumns[] = $name;

        $this->columnDef['append'][] = ['name' => $name, 'content' => $content, 'order' => $order];

        return $this;
    }

    /**
     * Add DT row index column on response.
     *
     * @return $this
     */
    public function addIndexColumn()
    {
        $this->columnDef['index'] = true;

        return $this;
    }

    /**
     * Edit column's content.
     *
     * @param string $name
     * @param string|callable $content
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
     * Declare columns to escape values.
     *
     * @param string|array $columns
     * @return $this
     */
    public function escapeColumns($columns = '*')
    {
        $this->columnDef['escape'] = $columns;

        return $this;
    }

    /**
     * Set columns that should not be escaped.
     *
     * @param array $columns
     * @return $this
     */
    public function rawColumns(array $columns)
    {
        $this->columnDef['raw'] = $columns;

        return $this;
    }

    /**
     * Allows previous API calls where the methods were snake_case.
     * Will convert a camelCase API call to a snake_case call.
     * Allow query builder method to be used by the engine.
     *
     * @param  string $name
     * @param  array $arguments
     * @return mixed
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
     * Get Query Builder object.
     *
     * @param mixed $instance
     * @return mixed
     */
    public function getQueryBuilder($instance = null)
    {
        if (! $instance) {
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
     * Sets DT_RowClass template.
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
     * Sets DT_RowId template.
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
     * Set DT_RowAttr templates.
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
     * @param string|callable $method
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
     * Order each given columns versus the given custom sql.
     *
     * @param array $columns
     * @param string $sql
     * @param array $bindings
     * @return $this
     */
    public function orderColumns(array $columns, $sql, $bindings = [])
    {
        foreach ($columns as $column) {
            $this->orderColumn($column, str_replace(':column', $column, $sql), $bindings);
        }

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
     * Set fractal serializer class.
     *
     * @param string $serializer
     * @return $this
     */
    public function setSerializer($serializer)
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function make($mDataSupport = false)
    {
        try {
            $this->totalRecords = $this->totalCount();

            if ($this->totalRecords) {
                $this->filterRecords();
                $this->ordering();
                $this->paginate();
            }

            $data = $this->transform($this->getProcessedData($mDataSupport));

            return $this->render($data);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * Perform necessary filters.
     *
     * @return void
     */
    protected function filterRecords()
    {
        if ($this->autoFilter && $this->request->isSearchable()) {
            $this->filtering();
        }

        if (is_callable($this->filterCallback)) {
            call_user_func($this->filterCallback, $this->filterCallbackParameters);
        }

        $this->columnSearch();
        $this->filteredRecords = $this->isFilterApplied ? $this->count() : $this->totalRecords;
    }

    /**
     * Perform global search.
     *
     * @return void
     */
    public function filtering()
    {
        $keyword = $this->request->keyword();

        if ($this->isSmartSearch()) {
            $this->smartGlobalSearch($keyword);

            return;
        }

        $this->globalSearch($keyword);
    }

    /**
     * Perform multi-term search by splitting keyword into
     * individual words and searches for each of them.
     *
     * @param string $keyword
     */
    protected function smartGlobalSearch($keyword)
    {
        collect(explode(' ', $keyword))
            ->reject(function ($keyword) {
                return trim($keyword) === '';
            })
            ->each(function ($keyword) {
                $this->globalSearch($keyword);
            });
    }

    /**
     * Perform global search for the given keyword.
     *
     * @param string $keyword
     */
    abstract protected function globalSearch($keyword);

    /**
     * Apply pagination.
     *
     * @return void
     */
    protected function paginate()
    {
        if ($this->request->isPaginationable() && ! $this->skipPaging) {
            $this->paging();
        }
    }

    /**
     * Transform output.
     *
     * @param mixed $output
     * @return array
     */
    protected function transform($output)
    {
        if (!isset($this->transformer)) {
            return Helper::transform($output);
        }

        $fractal = app('datatables.fractal');

        if ($this->serializer) {
            $fractal->setSerializer($this->createSerializer());
        }

        //Get transformer reflection
        //Firs method parameter should be data/object to transform
        $reflection = new \ReflectionMethod($this->transformer, 'transform');
        $parameter  = $reflection->getParameters()[0];

        //If parameter is class assuming it requires object
        //Else just pass array by default
        if ($parameter->getClass()) {
            $resource = new Collection($this->results(), $this->createTransformer());
        } else {
            $resource = new Collection(
                $output,
                $this->createTransformer()
            );
        }

        $collection = $fractal->createData($resource)->toArray();

        return $collection['data'];
    }

    /**
     * Get processed data
     *
     * @param bool|false $object
     * @return array
     */
    protected function getProcessedData($object = false)
    {
        $processor = new DataProcessor(
            $this->results(),
            $this->getColumnsDefinition(),
            $this->templates,
            $this->request->input('start')
        );

        return $processor->process($object);
    }

    /**
     * Get columns definition.
     *
     * @return array
     */
    protected function getColumnsDefinition()
    {
        $config  = config('datatables.columns');
        $allowed = ['excess', 'escape', 'raw', 'blacklist', 'whitelist'];

        return array_merge(array_only($config, $allowed), $this->columnDef);
    }

    /**
     * Get or create transformer serializer instance.
     *
     * @return \League\Fractal\Serializer\SerializerAbstract
     */
    protected function createSerializer()
    {
        if ($this->serializer instanceof \League\Fractal\Serializer\SerializerAbstract) {
            return $this->serializer;
        }

        return new $this->serializer();
    }

    /**
     * Get or create transformer instance.
     *
     * @return \League\Fractal\TransformerAbstract
     */
    protected function createTransformer()
    {
        if ($this->transformer instanceof \League\Fractal\TransformerAbstract) {
            return $this->transformer;
        }

        return new $this->transformer();
    }

    /**
     * Render json response.
     *
     * @param array $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function render(array $data)
    {
        $output = array_merge([
            'draw'            => (int) $this->request->input('draw'),
            'recordsTotal'    => $this->totalRecords,
            'recordsFiltered' => $this->filteredRecords,
            'data'            => $data,
        ], $this->appends);

        if ($this->isDebugging()) {
            $output = $this->showDebugger($output);
        }

        return new JsonResponse(
            $output,
            200,
            config('datatables.json.header', []),
            config('datatables.json.options', 0)
        );
    }

    /**
     * Check if app is in debug mode.
     *
     * @return bool
     */
    public function isDebugging()
    {
        return config('app.debug', false);
    }

    /**
     * Append debug parameters on output.
     *
     * @param  array $output
     * @return array
     */
    protected function showDebugger(array $output)
    {
        $output['queries'] = $this->connection->getQueryLog();
        $output['input']   = $this->request->all();

        return $output;
    }

    /**
     * Return an error json response.
     *
     * @param \Exception $exception
     * @return \Illuminate\Http\JsonResponse
     * @throws \Yajra\Datatables\Exception
     */
    protected function errorResponse(\Exception $exception)
    {
        $error = config('datatables.error');
        if ($error === 'throw') {
            throw new Exception($exception->getMessage(), $code = 0, $exception);
        }

        $this->getLogger()->error($exception);

        return new JsonResponse([
            'draw'            => (int) $this->request->input('draw'),
            'recordsTotal'    => (int) $this->totalRecords,
            'recordsFiltered' => 0,
            'data'            => [],
            'error'           => $error ? __($error) : "Exception Message:\n\n" . $exception->getMessage(),
        ]);
    }

    /**
     * Get monolog/logger instance.
     *
     * @return \Illuminate\Contracts\Logging\Log
     */
    public function getLogger()
    {
        $this->logger = $this->logger ?: resolve(Log::class);

        return $this->logger;
    }

    /**
     * Set monolog/logger instance.
     *
     * @param \Illuminate\Contracts\Logging\Log $logger
     * @return $this
     */
    public function setLogger(Log $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get config is case insensitive status.
     *
     * @return bool
     */
    public function isCaseInsensitive()
    {
        return config('datatables.search.case_insensitive', false);
    }

    /**
     * Append data on json response.
     *
     * @param mixed $key
     * @param mixed $value
     * @return $this
     */
    public function with($key, $value = '')
    {
        if (is_array($key)) {
            $this->appends = $key;
        } elseif (is_callable($value)) {
            $this->appends[$key] = value($value);
        } else {
            $this->appends[$key] = value($value);
        }

        return $this;
    }

    /**
     * Override default ordering method with a closure callback.
     *
     * @param callable $closure
     * @return $this
     */
    public function order(callable $closure)
    {
        $this->orderCallback = $closure;

        return $this;
    }

    /**
     * Update list of columns that is not allowed for search/sort.
     *
     * @param  array $blacklist
     * @return $this
     */
    public function blacklist(array $blacklist)
    {
        $this->columnDef['blacklist'] = $blacklist;

        return $this;
    }

    /**
     * Update list of columns that is allowed for search/sort.
     *
     * @param  string|array $whitelist
     * @return $this
     */
    public function whitelist($whitelist = '*')
    {
        $this->columnDef['whitelist'] = $whitelist;

        return $this;
    }

    /**
     * Set smart search config at runtime.
     *
     * @param bool $bool
     * @return $this
     */
    public function smart($bool = true)
    {
        config(['datatables.search.smart' => $bool]);

        return $this;
    }

    /**
     * Set total records manually.
     *
     * @param int $total
     * @return $this
     */
    public function setTotalRecords($total)
    {
        $this->totalRecords = $total;

        return $this;
    }

    /**
     * Skip pagination as needed.
     *
     * @return $this
     */
    public function skipPaging()
    {
        $this->skipPaging = true;

        return $this;
    }

    /**
     * Check if the current sql language is based on oracle syntax.
     *
     * @return bool
     */
    public function isOracleSql()
    {
        return in_array($this->database, ['oracle', 'oci8']);
    }

    /**
     * Set datatables to do ordering with NULLS LAST option.
     *
     * @return $this
     */
    public function orderByNullsLast()
    {
        $this->nullsLast = true;

        return $this;
    }

    /**
     * Push a new column name to blacklist
     *
     * @param string $column
     * @return $this
     */
    public function pushToBlacklist($column)
    {
        if (! $this->isBlacklisted($column)) {
            array_push($this->columnDef['blacklist'], $column);
        }

        return $this;
    }

    /**
     * Check if column is blacklisted.
     *
     * @param string $column
     * @return bool
     */
    protected function isBlacklisted($column)
    {
        if (in_array($column, $this->columnDef['blacklist'])) {
            return true;
        }

        if ($this->columnDef['whitelist'] === '*' || in_array($column, $this->columnDef['whitelist'])) {
            return false;
        }

        return true;
    }

    /**
     * Setup search keyword.
     *
     * @param  string $value
     * @return string
     */
    protected function setupKeyword($value)
    {
        if ($this->isSmartSearch()) {
            $keyword = '%' . $value . '%';
            if ($this->isWildcard()) {
                $keyword = $this->wildcardLikeString($value);
            }
            // remove escaping slash added on js script request
            $keyword = str_replace('\\', '%', $keyword);

            return $keyword;
        }

        return $value;
    }

    /**
     * Check if DataTables uses smart search.
     *
     * @return bool
     */
    public function isSmartSearch()
    {
        return config('datatables.search.smart', true);
    }

    /**
     * Get config use wild card status.
     *
     * @return bool
     */
    public function isWildcard()
    {
        return config('datatables.search.use_wildcards', false);
    }

    /**
     * Adds % wildcards to the given string.
     *
     * @param string $str
     * @param bool $lowercase
     * @return string
     */
    protected function wildcardLikeString($str, $lowercase = true)
    {
        $wild  = '%';
        $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);

        if (count($chars) > 0) {
            foreach ($chars as $char) {
                $wild .= $char . '%';
            }
        }

        if ($lowercase) {
            $wild = Str::lower($wild);
        }

        return $wild;
    }

    /**
     * Update flags to disable global search
     *
     * @param  callable $callback
     * @param  mixed $parameters
     * @param  bool $autoFilter
     */
    protected function overrideGlobalSearch(callable $callback, $parameters, $autoFilter = false)
    {
        $this->autoFilter               = $autoFilter;
        $this->isFilterApplied          = true;
        $this->filterCallback           = $callback;
        $this->filterCallbackParameters = $parameters;
    }

    /**
     * Get column name to be use for filtering and sorting.
     *
     * @param integer $index
     * @param bool $wantsAlias
     * @return string
     */
    protected function getColumnName($index, $wantsAlias = false)
    {
        $column = $this->request->columnName($index);

        // DataTables is using make(false)
        if (is_numeric($column)) {
            $column = $this->getColumnNameByIndex($index);
        }

        if (Str::contains(Str::upper($column), ' AS ')) {
            $column = $this->extractColumnName($column, $wantsAlias);
        }

        return $column;
    }

    /**
     * Get column name by order column index.
     *
     * @param int $index
     * @return mixed
     */
    protected function getColumnNameByIndex($index)
    {
        $name = isset($this->columns[$index]) && $this->columns[$index] != '*' ? $this->columns[$index] : $this->getPrimaryKeyName();

        return in_array($name, $this->extraColumns, true) ? $this->getPrimaryKeyName() : $name;
    }

    /**
     * If column name could not be resolved then use primary key.
     *
     * @return string
     */
    protected function getPrimaryKeyName()
    {
        if ($this->isEloquent()) {
            return $this->query->getModel()->getKeyName();
        }

        return 'id';
    }

    /**
     * Check if the engine used was eloquent.
     *
     * @return bool
     */
    public function isEloquent()
    {
        return $this->query_type === 'eloquent';
    }

    /**
     * Get column name from string.
     *
     * @param string $str
     * @param bool $wantsAlias
     * @return string
     */
    protected function extractColumnName($str, $wantsAlias)
    {
        $matches = explode(' as ', Str::lower($str));

        if (! empty($matches)) {
            if ($wantsAlias) {
                return array_pop($matches);
            } else {
                return array_shift($matches);
            }
        } elseif (strpos($str, '.')) {
            $array = explode('.', $str);

            return array_pop($array);
        }

        return $str;
    }
}
