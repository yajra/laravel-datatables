<?php

namespace Yajra\DataTables;

use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Utilities\Helper;
use Illuminate\Support\Traits\Macroable;
use Yajra\DataTables\Contracts\DataTable;
use Illuminate\Contracts\Support\Jsonable;
use Yajra\DataTables\Exceptions\Exception;
use Illuminate\Contracts\Support\Arrayable;
use Yajra\DataTables\Processors\DataProcessor;

/**
 * @method DataTableAbstract setTransformer($transformer)
 * @method DataTableAbstract setSerializer($transformer)
 * @property mixed transformer
 * @property mixed serializer
 * @see     https://github.com/yajra/laravel-datatables-fractal for transformer related methods.
 */
abstract class DataTableAbstract implements DataTable, Arrayable, Jsonable
{
    use Macroable;

    /**
     * DataTables Request object.
     *
     * @var \Yajra\DataTables\Utilities\Request
     */
    public $request;

    /**
     * @var \Psr\Log\LoggerInterface
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
        'index'  => false,
        'append' => [],
        'edit'   => [],
        'filter' => [],
        'order'  => [],
    ];

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
     * [internal] Track if any filter was applied for at least one column.
     *
     * @var bool
     */
    protected $isFilterApplied = false;

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
     * @var \Yajra\DataTables\Utilities\Config
     */
    protected $config;

    /**
     * @var mixed
     */
    protected $serializer;

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param mixed $source
     * @return bool
     */
    public static function canCreate($source)
    {
        return false;
    }

    /**
     * Factory method, create and return an instance for the DataTable engine.
     *
     * @param mixed $source
     * @return DataTableAbstract
     */
    public static function create($source)
    {
        return new static($source);
    }

    /**
     * Add column in collection.
     *
     * @param string          $name
     * @param string|callable $content
     * @param bool|int        $order
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
     * @param string          $name
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
        $this->columnDef['excess'] = array_merge($this->getColumnsDefinition()['excess'], $names);

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
     * @param string          $key
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
     * @param string          $key
     * @param string|callable $value
     * @return $this
     */
    public function addRowAttr($key, $value)
    {
        $this->templates['DT_RowAttr'][$key] = $value;

        return $this;
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
        $this->config->set(['datatables.search.smart' => $bool]);

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
     * Push a new column name to blacklist.
     *
     * @param string $column
     * @return $this
     */
    public function pushToBlacklist($column)
    {
        if (! $this->isBlacklisted($column)) {
            $this->columnDef['blacklist'][] = $column;
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
        $colDef = $this->getColumnsDefinition();

        if (in_array($column, $colDef['blacklist'])) {
            return true;
        }

        if ($colDef['whitelist'] === '*' || in_array($column, $colDef['whitelist'])) {
            return false;
        }

        return true;
    }

    /**
     * Get columns definition.
     *
     * @return array
     */
    protected function getColumnsDefinition()
    {
        $config  = $this->config->get('datatables.columns');
        $allowed = ['excess', 'escape', 'raw', 'blacklist', 'whitelist'];

        return array_replace_recursive(array_only($config, $allowed), $this->columnDef);
    }

    /**
     * Perform sorting of columns.
     */
    public function ordering()
    {
        if ($this->orderCallback) {
            return call_user_func($this->orderCallback, $this->resolveCallbackParameter());
        }

        return $this->defaultOrdering();
    }

    /**
     * Resolve callback parameter instance.
     *
     * @return mixed
     */
    abstract protected function resolveCallbackParameter();

    /**
     * Perform default query orderBy clause.
     */
    abstract protected function defaultOrdering();

    /**
     * Set auto filter off and run your own filter.
     * Overrides global search.
     *
     * @param callable $callback
     * @param bool     $globalSearch
     * @return $this
     */
    public function filter(callable $callback, $globalSearch = false)
    {
        $this->autoFilter      = $globalSearch;
        $this->isFilterApplied = true;
        $this->filterCallback  = $callback;

        return $this;
    }

    /**
     * Convert instance to array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->make()->getData(true);
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int $options
     * @return \Illuminate\Http\JsonResponse
     */
    public function toJson($options = 0)
    {
        if ($options) {
            $this->config->set('datatables.json.options', $options);
        }

        return $this->make();
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
            call_user_func($this->filterCallback, $this->resolveCallbackParameter());
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

        if ($this->config->isMultiTerm()) {
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
     * @param mixed $results
     * @param mixed $processed
     * @return array
     */
    protected function transform($results, $processed)
    {
        if (isset($this->transformer) && class_exists('Yajra\\DataTables\\Transformers\\FractalTransformer')) {
            return app('datatables.transformer')->transform(
                $results,
                $this->transformer,
                $this->serializer ?? null
            );
        }

        return Helper::transform($processed);
    }

    /**
     * Get processed data.
     *
     * @param mixed $results
     * @param bool  $object
     * @return array
     */
    protected function processResults($results, $object = false)
    {
        $processor = new DataProcessor(
            $results,
            $this->getColumnsDefinition(),
            $this->templates,
            $this->request->input('start')
        );

        return $processor->process($object);
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

        if ($this->config->isDebugging()) {
            $output = $this->showDebugger($output);
        }

        return new JsonResponse(
            $output,
            200,
            $this->config->get('datatables.json.header', []),
            $this->config->get('datatables.json.options', 0)
        );
    }

    /**
     * Append debug parameters on output.
     *
     * @param  array $output
     * @return array
     */
    protected function showDebugger(array $output)
    {
        $output['input'] = $this->request->all();

        return $output;
    }

    /**
     * Return an error json response.
     *
     * @param \Exception $exception
     * @return \Illuminate\Http\JsonResponse
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    protected function errorResponse(\Exception $exception)
    {
        $error = $this->config->get('datatables.error');
        if ($error === 'throw') {
            throw new Exception($exception->getMessage(), $code = 0, $exception);
        }

        $this->getLogger()->error($exception);

        return new JsonResponse([
            'draw'            => (int) $this->request->input('draw'),
            'recordsTotal'    => $this->totalRecords,
            'recordsFiltered' => 0,
            'data'            => [],
            'error'           => $error ? __($error) : "Exception Message:\n\n" . $exception->getMessage(),
        ]);
    }

    /**
     * Get monolog/logger instance.
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        $this->logger = $this->logger ?: app(LoggerInterface::class);

        return $this->logger;
    }

    /**
     * Set monolog/logger instance.
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Setup search keyword.
     *
     * @param  string $value
     * @return string
     */
    protected function setupKeyword($value)
    {
        if ($this->config->isSmartSearch()) {
            $keyword = '%' . $value . '%';
            if ($this->config->isWildcard()) {
                $keyword = Helper::wildcardLikeString($value);
            }
            // remove escaping slash added on js script request
            $keyword = str_replace('\\', '%', $keyword);

            return $keyword;
        }

        return $value;
    }

    /**
     * Get column name to be use for filtering and sorting.
     *
     * @param int $index
     * @param bool    $wantsAlias
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
            $column = Helper::extractColumnName($column, $wantsAlias);
        }

        return $column;
    }

    /**
     * Get column name by order column index.
     *
     * @param int $index
     * @return string
     */
    protected function getColumnNameByIndex($index)
    {
        $name = (isset($this->columns[$index]) && $this->columns[$index] != '*')
            ? $this->columns[$index] : $this->getPrimaryKeyName();

        return in_array($name, $this->extraColumns, true) ? $this->getPrimaryKeyName() : $name;
    }

    /**
     * If column name could not be resolved then use primary key.
     *
     * @return string
     */
    protected function getPrimaryKeyName()
    {
        return 'id';
    }
}
