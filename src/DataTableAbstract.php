<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Psr\Log\LoggerInterface;
use Yajra\DataTables\Contracts\DataTable;
use Yajra\DataTables\Contracts\Formatter;
use Yajra\DataTables\Exceptions\Exception;
use Yajra\DataTables\Processors\DataProcessor;
use Yajra\DataTables\Utilities\Helper;

/**
 * @method DataTableAbstract setTransformer($transformer)
 * @method DataTableAbstract setSerializer($transformer)
 *
 * @property-read mixed $transformer
 * @property-read mixed $serializer
 *
 * @see https://github.com/yajra/laravel-datatables-fractal for transformer related methods.
 */
abstract class DataTableAbstract implements DataTable
{
    use Macroable;

    /**
     * DataTables Request object.
     *
     * @var \Yajra\DataTables\Utilities\Request
     */
    public Utilities\Request $request;

    /**
     * @var \Psr\Log\LoggerInterface|null
     */
    protected ?LoggerInterface $logger = null;

    /**
     * Array of result columns/fields.
     *
     * @var array|null
     */
    protected ?array $columns = [];

    /**
     * DT columns definitions container (add/edit/remove/filter/order/escape).
     *
     * @var array
     */
    protected array $columnDef = [
        'index' => false,
        'append' => [],
        'edit' => [],
        'filter' => [],
        'order' => [],
        'only' => null,
        'hidden' => [],
        'visible' => [],
    ];

    /**
     * Extra/Added columns.
     *
     * @var array
     */
    protected array $extraColumns = [];

    /**
     * Total records.
     *
     * @var int
     */
    protected int $totalRecords = 0;

    /**
     * Total filtered records.
     *
     * @var int
     */
    protected int $filteredRecords = 0;

    /**
     * Auto-filter flag.
     *
     * @var bool
     */
    protected bool $autoFilter = true;

    /**
     * Callback to override global search.
     *
     * @var callable
     */
    protected $filterCallback = null;

    /**
     * DT row templates container.
     *
     * @var array
     */
    protected array $templates = [
        'DT_RowId' => '',
        'DT_RowClass' => '',
        'DT_RowData' => [],
        'DT_RowAttr' => [],
    ];

    /**
     * [internal] Track if any filter was applied for at least one column.
     *
     * @var bool
     */
    protected bool $isFilterApplied = false;

    /**
     * Custom ordering callback.
     *
     * @var ?callable
     */
    protected $orderCallback = null;

    /**
     * Skip paginate as needed.
     *
     * @var bool
     */
    protected bool $skipPaging = false;

    /**
     * Array of data to append on json response.
     *
     * @var array
     */
    protected array $appends = [];

    /**
     * @var \Yajra\DataTables\Utilities\Config
     */
    protected Utilities\Config $config;

    /**
     * @var mixed
     */
    protected mixed $serializer;

    /**
     * @var array
     */
    protected array $searchPanes = [];

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param  mixed  $source
     * @return bool
     */
    public static function canCreate($source)
    {
        return false;
    }

    /**
     * Factory method, create and return an instance for the DataTable engine.
     *
     * @param  mixed  $source
     * @return static
     */
    public static function create($source)
    {
        /** @phpstan-ignore-next-line */
        return new static($source);
    }

    /**
     * @param  string|array  $columns
     * @param  string|\Yajra\DataTables\Contracts\Formatter  $formatter
     * @return static
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    public function formatColumn($columns, $formatter): self
    {
        if (is_string($formatter) && class_exists($formatter)) {
            $formatter = app($formatter);
        }

        if ($formatter instanceof Formatter) {
            foreach ((array) $columns as $column) {
                $this->addColumn($column.'_formatted', $formatter);
            }

            return $this;
        }

        throw new Exception('$formatter must be an instance of '.Formatter::class);
    }

    /**
     * Add column in collection.
     *
     * @param  string  $name
     * @param  string|callable|Formatter  $content
     * @param  bool|int  $order
     * @return static
     */
    public function addColumn($name, $content, $order = false): self
    {
        $this->extraColumns[] = $name;

        $this->columnDef['append'][] = ['name' => $name, 'content' => $content, 'order' => $order];

        return $this;
    }

    /**
     * Add DT row index column on response.
     *
     * @return static
     */
    public function addIndexColumn(): self
    {
        $this->columnDef['index'] = true;

        return $this;
    }

    /**
     * Edit column's content.
     *
     * @param  string  $name
     * @param  string|callable  $content
     * @return static
     */
    public function editColumn($name, $content): self
    {
        $this->columnDef['edit'][] = ['name' => $name, 'content' => $content];

        return $this;
    }

    /**
     * Remove column from collection.
     *
     * @return static
     */
    public function removeColumn(): self
    {
        $names = func_get_args();
        $this->columnDef['excess'] = array_merge($this->getColumnsDefinition()['excess'], $names);

        return $this;
    }

    /**
     * Get columns definition.
     *
     * @return array
     */
    protected function getColumnsDefinition(): array
    {
        $config = (array) $this->config->get('datatables.columns');
        $allowed = ['excess', 'escape', 'raw', 'blacklist', 'whitelist'];

        return array_replace_recursive(Arr::only($config, $allowed), $this->columnDef);
    }

    /**
     * Get only selected columns in response.
     *
     * @param  array  $columns
     * @return static
     */
    public function only(array $columns = []): self
    {
        $this->columnDef['only'] = $columns;

        return $this;
    }

    /**
     * Declare columns to escape values.
     *
     * @param  string|array  $columns
     * @return static
     */
    public function escapeColumns($columns = '*'): self
    {
        $this->columnDef['escape'] = $columns;

        return $this;
    }

    /**
     * Add a makeHidden() to the row object.
     *
     * @param  array  $attributes
     * @return static
     */
    public function makeHidden(array $attributes = []): self
    {
        $hidden = (array) Arr::get($this->columnDef, 'hidden', []);
        $this->columnDef['hidden'] = array_merge_recursive($hidden, $attributes);

        return $this;
    }

    /**
     * Add a makeVisible() to the row object.
     *
     * @param  array  $attributes
     * @return static
     */
    public function makeVisible(array $attributes = []): self
    {
        $visible = (array) Arr::get($this->columnDef, 'visible', []);
        $this->columnDef['visible'] = array_merge_recursive($visible, $attributes);

        return $this;
    }

    /**
     * Set columns that should not be escaped.
     * Optionally merge the defaults from config.
     *
     * @param  array  $columns
     * @param  bool  $merge
     * @return static
     */
    public function rawColumns(array $columns, $merge = false): self
    {
        if ($merge) {
            /** @var array[] $config */
            $config = $this->config->get('datatables.columns');

            $this->columnDef['raw'] = array_merge($config['raw'], $columns);
        } else {
            $this->columnDef['raw'] = $columns;
        }

        return $this;
    }

    /**
     * Sets DT_RowClass template.
     * result: <tr class="output_from_your_template">.
     *
     * @param  string|callable  $content
     * @return static
     */
    public function setRowClass($content): self
    {
        $this->templates['DT_RowClass'] = $content;

        return $this;
    }

    /**
     * Sets DT_RowId template.
     * result: <tr id="output_from_your_template">.
     *
     * @param  string|callable  $content
     * @return static
     */
    public function setRowId($content): self
    {
        $this->templates['DT_RowId'] = $content;

        return $this;
    }

    /**
     * Set DT_RowData templates.
     *
     * @param  array  $data
     * @return static
     */
    public function setRowData(array $data): self
    {
        $this->templates['DT_RowData'] = $data;

        return $this;
    }

    /**
     * Add DT_RowData template.
     *
     * @param  string  $key
     * @param  string|callable  $value
     * @return static
     */
    public function addRowData($key, $value): self
    {
        $this->templates['DT_RowData'][$key] = $value;

        return $this;
    }

    /**
     * Set DT_RowAttr templates.
     * result: <tr attr1="attr1" attr2="attr2">.
     *
     * @param  array  $data
     * @return static
     */
    public function setRowAttr(array $data): self
    {
        $this->templates['DT_RowAttr'] = $data;

        return $this;
    }

    /**
     * Add DT_RowAttr template.
     *
     * @param  string  $key
     * @param  string|callable  $value
     * @return static
     */
    public function addRowAttr($key, $value): self
    {
        $this->templates['DT_RowAttr'][$key] = $value;

        return $this;
    }

    /**
     * Append data on json response.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return static
     */
    public function with($key, $value = ''): self
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
     * Add with query callback value on response.
     *
     * @param  string  $key
     * @param  callable  $value
     * @return static
     */
    public function withQuery($key, callable $value): self
    {
        $this->appends[$key] = $value;

        return $this;
    }

    /**
     * Override default ordering method with a closure callback.
     *
     * @param  callable  $closure
     * @return static
     */
    public function order(callable $closure): self
    {
        $this->orderCallback = $closure;

        return $this;
    }

    /**
     * Update list of columns that is not allowed for search/sort.
     *
     * @param  array  $blacklist
     * @return static
     */
    public function blacklist(array $blacklist): self
    {
        $this->columnDef['blacklist'] = $blacklist;

        return $this;
    }

    /**
     * Update list of columns that is allowed for search/sort.
     *
     * @param  string|array  $whitelist
     * @return static
     */
    public function whitelist($whitelist = '*'): self
    {
        $this->columnDef['whitelist'] = $whitelist;

        return $this;
    }

    /**
     * Set smart search config at runtime.
     *
     * @param  bool  $state
     * @return static
     */
    public function smart($state = true): self
    {
        $this->config->set('datatables.search.smart', $state);

        return $this;
    }

    /**
     * Set starts_with search config at runtime.
     *
     * @param  bool  $state
     * @return static
     */
    public function startsWithSearch($state = true): self
    {
        $this->config->set('datatables.search.starts_with', $state);

        return $this;
    }

    /**
     * Set multi_term search config at runtime.
     *
     * @param  bool  $multiTerm
     * @return static
     */
    public function setMultiTerm($multiTerm = true): self
    {
        $this->config->set('datatables.search.multi_term', $multiTerm);

        return $this;
    }

    /**
     * Set total records manually.
     *
     * @param  int  $total
     * @return static
     */
    public function setTotalRecords($total): self
    {
        $this->totalRecords = $total;

        return $this;
    }

    /**
     * Set filtered records manually.
     *
     * @param  int  $total
     * @return static
     */
    public function setFilteredRecords($total): self
    {
        $this->filteredRecords = $total;

        return $this;
    }

    /**
     * Skip pagination as needed.
     *
     * @return static
     */
    public function skipPaging(): self
    {
        $this->skipPaging = true;

        return $this;
    }

    /**
     * Push a new column name to blacklist.
     *
     * @param  string  $column
     * @return static
     */
    public function pushToBlacklist($column): self
    {
        if (! $this->isBlacklisted($column)) {
            $this->columnDef['blacklist'][] = $column;
        }

        return $this;
    }

    /**
     * Check if column is blacklisted.
     *
     * @param  string  $column
     * @return bool
     */
    protected function isBlacklisted($column): bool
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
     * Perform sorting of columns.
     *
     * @return void
     */
    public function ordering(): void
    {
        if ($this->orderCallback) {
            call_user_func($this->orderCallback, $this->resolveCallbackParameter());
        } else {
            $this->defaultOrdering();
        }
    }

    /**
     * Resolve callback parameter instance.
     *
     * @return mixed
     */
    abstract protected function resolveCallbackParameter();

    /**
     * Perform default query orderBy clause.
     *
     * @return void
     */
    abstract protected function defaultOrdering(): void;

    /**
     * Set auto filter off and run your own filter.
     * Overrides global search.
     *
     * @param  callable  $callback
     * @param  bool  $globalSearch
     * @return static
     */
    public function filter(callable $callback, $globalSearch = false): self
    {
        $this->autoFilter = $globalSearch;
        $this->isFilterApplied = true;
        $this->filterCallback = $callback;

        return $this;
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
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
     * Add a search pane options on response.
     *
     * @param  string  $column
     * @param  mixed  $options
     * @param  callable|null  $builder
     * @return static
     */
    public function searchPane($column, $options, callable $builder = null): self
    {
        $options = value($options);

        if ($options instanceof Arrayable) {
            $options = $options->toArray();
        }

        $this->searchPanes[$column]['options'] = $options;
        $this->searchPanes[$column]['builder'] = $builder;

        return $this;
    }

    /**
     * Convert instance to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return (array) $this->make()->getData(true);
    }

    /**
     * Perform necessary filters.
     *
     * @return void
     */
    protected function filterRecords(): void
    {
        if ($this->autoFilter && $this->request->isSearchable()) {
            $this->filtering();
        }

        if (is_callable($this->filterCallback)) {
            call_user_func($this->filterCallback, $this->resolveCallbackParameter());
        }

        $this->columnSearch();
        $this->searchPanesSearch();
        $this->filteredRecords = $this->isFilterApplied ? $this->filteredCount() : $this->totalRecords;
    }

    /**
     * Perform global search.
     *
     * @return void
     */
    public function filtering(): void
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
     * @param  string  $keyword
     * @return void
     */
    protected function smartGlobalSearch($keyword): void
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
     * @param  string  $keyword
     * @return void
     */
    abstract protected function globalSearch(string $keyword): void;

    /**
     * Perform search using search pane values.
     *
     * @return void
     */
    protected function searchPanesSearch(): void
    {
        // Add support for search pane.
    }

    /**
     * Count filtered items.
     *
     * @return int
     */
    protected function filteredCount(): int
    {
        return $this->filteredRecords ?: $this->count();
    }

    /**
     * Apply pagination.
     *
     * @return void
     */
    protected function paginate(): void
    {
        if ($this->request->isPaginationable() && ! $this->skipPaging) {
            $this->paging();
        }
    }

    /**
     * Transform output.
     *
     * @param  iterable  $results
     * @param  array  $processed
     * @return array
     */
    protected function transform($results, $processed): array
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
     * @param  iterable  $results
     * @param  bool  $object
     * @return array
     */
    protected function processResults($results, $object = false): array
    {
        $processor = new DataProcessor(
            $results,
            $this->getColumnsDefinition(),
            $this->templates,
            $this->request->start()
        );

        return $processor->process($object);
    }

    /**
     * Render json response.
     *
     * @param  array  $data
     * @return \Illuminate\Http\JsonResponse
     */
    protected function render(array $data): JsonResponse
    {
        $output = $this->attachAppends([
            'draw' => $this->request->draw(),
            'recordsTotal' => $this->totalRecords,
            'recordsFiltered' => $this->filteredRecords,
            'data' => $data,
        ]);

        if ($this->config->isDebugging()) {
            $output = $this->showDebugger($output);
        }

        foreach ($this->searchPanes as $column => $searchPane) {
            $output['searchPanes']['options'][$column] = $searchPane['options'];
        }

        return new JsonResponse(
            $output,
            200,
            $this->config->jsonHeaders(),
            $this->config->jsonOptions()
        );
    }

    /**
     * Attach custom with meta on response.
     *
     * @param  array  $data
     * @return array
     */
    protected function attachAppends(array $data): array
    {
        return array_merge($data, $this->appends);
    }

    /**
     * Append debug parameters on output.
     *
     * @param  array  $output
     * @return array
     */
    protected function showDebugger(array $output): array
    {
        $output['input'] = $this->request->all();

        return $output;
    }

    /**
     * Return an error json response.
     *
     * @param  \Exception  $exception
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Yajra\DataTables\Exceptions\Exception|\Exception
     */
    protected function errorResponse(\Exception $exception)
    {
        /** @var string $error */
        $error = $this->config->get('datatables.error');
        $debug = $this->config->get('app.debug');

        if ($error === 'throw' || (! $error && ! $debug)) {
            throw $exception;
        }

        $this->getLogger()->error($exception);

        return new JsonResponse([
            'draw' => $this->request->draw(),
            'recordsTotal' => $this->totalRecords,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => $error ? __($error) : "Exception Message:\n\n".$exception->getMessage(),
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
     * @param  \Psr\Log\LoggerInterface  $logger
     * @return static
     */
    public function setLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Setup search keyword.
     *
     * @param  string  $value
     * @return string
     */
    protected function setupKeyword(string $value): string
    {
        if ($this->config->isSmartSearch()) {
            $keyword = '%'.$value.'%';
            if ($this->config->isWildcard()) {
                $keyword = Helper::wildcardLikeString($value);
            }

            // remove escaping slash added on js script request
            return str_replace('\\', '%', $keyword);
        }

        return $value;
    }

    /**
     * Get column name to be use for filtering and sorting.
     *
     * @param  int  $index
     * @param  bool  $wantsAlias
     * @return string|null
     */
    protected function getColumnName(int $index, bool $wantsAlias = false): ?string
    {
        $column = $this->request->columnName($index);

        if (is_null($column)) {
            return null;
        }

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
     * @param  int  $index
     * @return string
     */
    protected function getColumnNameByIndex(int $index): string
    {
        $name = (isset($this->columns[$index]) && $this->columns[$index] != '*')
            ? $this->columns[$index]
            : $this->getPrimaryKeyName();

        return in_array($name, $this->extraColumns, true) ? $this->getPrimaryKeyName() : $name;
    }

    /**
     * If column name could not be resolved then use primary key.
     *
     * @return string
     */
    protected function getPrimaryKeyName(): string
    {
        return 'id';
    }
}
