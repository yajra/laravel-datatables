<?php

namespace Yajra\DataTables;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use Yajra\DataTables\Utilities\Helper;

class QueryDataTable extends DataTableAbstract
{
    /**
     * Builder object.
     *
     * @var \Illuminate\Database\Query\Builder
     */
    protected $query;

    /**
     * Database connection used.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $connection;

    /**
     * Flag for ordering NULLS LAST option.
     *
     * @var bool
     */
    protected $nullsLast = false;

    /**
     * Flag to check if query preparation was already done.
     *
     * @var bool
     */
    protected $prepared = false;

    /**
     * Query callback for custom pagination using limit without offset.
     *
     * @var callable
     */
    protected $limitCallback;

    /**
     * Flag to skip total records count query.
     *
     * @var bool
     */
    protected $skipTotalRecords = false;

    /**
     * Flag to keep the select bindings.
     *
     * @var bool
     */
    protected $keepSelectBindings = false;

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param mixed $source
     * @return bool
     */
    public static function canCreate($source)
    {
        return $source instanceof Builder;
    }

    /**
     * @param \Illuminate\Database\Query\Builder $builder
     */
    public function __construct(Builder $builder)
    {
        $this->query      = $builder;
        $this->request    = app('datatables.request');
        $this->config     = app('datatables.config');
        $this->columns    = $builder->columns;
        $this->connection = $builder->getConnection();
        if ($this->config->isDebugging()) {
            $this->connection->enableQueryLog();
        }
    }

    /**
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function make($mDataSupport = true)
    {
        try {
            $this->prepareQuery();

            $results   = $this->results();
            $processed = $this->processResults($results, $mDataSupport);
            $data      = $this->transform($results, $processed);

            return $this->render($data);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * Perform search using search pane values.
     */
    protected function searchPanesSearch()
    {
        $columns = $this->request->get('searchPanes', []);

        foreach ($columns as $column => $values) {
            if ($this->isBlacklisted($column)) {
                continue;
            }

            if ($this->searchPanes[$column] && $callback = $this->searchPanes[$column]['builder']) {
                $callback($this->query, $values);
            } else {
                $this->query->whereIn($column, $values);
            }

            $this->isFilterApplied = true;
        }
    }

    /**
     * Prepare query by executing count, filter, order and paginate.
     */
    protected function prepareQuery()
    {
        if (! $this->prepared) {
            $this->totalRecords = $this->totalCount();

            if ($this->totalRecords) {
                $this->filterRecords();
                $this->ordering();
                $this->paginate();
            }
        }

        $this->prepared = true;
    }

    /**
     * Skip total records and set the recordsTotal equals to recordsFiltered.
     * This will improve the performance by skipping the total count query.
     *
     * @return $this
     */
    public function skipTotalRecords()
    {
        $this->skipTotalRecords = true;

        return $this;
    }

    /**
     * Keep the select bindings.
     *
     * @return $this
     */
    public function keepSelectBindings()
    {
        $this->keepSelectBindings = true;

        return $this;
    }

    /**
     * Count total items.
     *
     * @return int
     */
    public function totalCount()
    {
        if ($this->skipTotalRecords) {
            $this->isFilterApplied = true;

            return 1;
        }

        return $this->totalRecords ? $this->totalRecords : $this->count();
    }

    /**
     * Count filtered items.
     *
     * @return int
     */
    protected function filteredCount()
    {
        $this->filteredRecords = $this->filteredRecords ?: $this->count();
        if ($this->skipTotalRecords) {
            $this->totalRecords = $this->filteredRecords;
        }

        return $this->filteredRecords;
    }

    /**
     * Counts current query.
     *
     * @return int
     */
    public function count()
    {
        return $this->prepareCountQuery()->count();
    }

    /**
     * Prepare count query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function prepareCountQuery()
    {
        $builder = clone $this->query;

        if ($this->isComplexQuery($builder)) {
            $table = $this->connection->raw('('.$builder->toSql().') count_row_table');

            return $this->connection->table($table)
                ->setBindings($builder->getBindings());
        }

        $row_count = $this->wrap('row_count');
        $builder->select($this->connection->raw("'1' as {$row_count}"));
        if (! $this->keepSelectBindings) {
            $builder->setBindings([], 'select');
        }

        return $builder;
    }

    /**
     * Check if builder query uses complex sql.
     *
     * @param \Illuminate\Database\Query\Builder $builder
     * @return bool
     */
    protected function isComplexQuery($builder)
    {
        return Str::contains(Str::lower($builder->toSql()), ['union', 'having', 'distinct', 'order by', 'group by']);
    }

    /**
     * Wrap column with DB grammar.
     *
     * @param string $column
     * @return string
     */
    protected function wrap($column)
    {
        return $this->connection->getQueryGrammar()->wrap($column);
    }

    /**
     * Get paginated results.
     *
     * @return \Illuminate\Support\Collection
     */
    public function results()
    {
        return $this->query->get();
    }

    /**
     * Get filtered, ordered and paginated query.
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function getFilteredQuery()
    {
        $this->prepareQuery();

        return $this->getQuery();
    }

    /**
     * Get query builder instance.
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Perform column search.
     *
     * @return void
     */
    public function columnSearch()
    {
        $columns = $this->request->columns();

        foreach ($columns as $index => $column) {
            $column = $this->getColumnName($index);

            if (! $this->request->isColumnSearchable($index) || $this->isBlacklisted($column) && ! $this->hasFilterColumn($column)) {
                continue;
            }

            if ($this->hasFilterColumn($column)) {
                $keyword = $this->getColumnSearchKeyword($index, true);
                $this->applyFilterColumn($this->getBaseQueryBuilder(), $column, $keyword);
            } else {
                $column  = $this->resolveRelationColumn($column);
                $keyword = $this->getColumnSearchKeyword($index);
                $this->compileColumnSearch($index, $column, $keyword);
            }

            $this->isFilterApplied = true;
        }
    }

    /**
     * Check if column has custom filter handler.
     *
     * @param  string $columnName
     * @return bool
     */
    public function hasFilterColumn($columnName)
    {
        return isset($this->columnDef['filter'][$columnName]);
    }

    /**
     * Get column keyword to use for search.
     *
     * @param int  $i
     * @param bool $raw
     * @return string
     */
    protected function getColumnSearchKeyword($i, $raw = false)
    {
        $keyword = $this->request->columnKeyword($i);
        if ($raw || $this->request->isRegex($i)) {
            return $keyword;
        }

        return $this->setupKeyword($keyword);
    }

    /**
     * Apply filterColumn api search.
     *
     * @param mixed  $query
     * @param string $columnName
     * @param string $keyword
     * @param string $boolean
     */
    protected function applyFilterColumn($query, $columnName, $keyword, $boolean = 'and')
    {
        $query    = $this->getBaseQueryBuilder($query);
        $callback = $this->columnDef['filter'][$columnName]['method'];

        if ($this->query instanceof EloquentBuilder) {
            $builder = $this->query->newModelInstance()->newQuery();
        } else {
            $builder = $this->query->newQuery();
        }

        $callback($builder, $keyword);

        $query->addNestedWhereQuery($this->getBaseQueryBuilder($builder), $boolean);
    }

    /**
     * Get the base query builder instance.
     *
     * @param mixed $instance
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getBaseQueryBuilder($instance = null)
    {
        if (! $instance) {
            $instance = $this->query;
        }

        if ($instance instanceof EloquentBuilder) {
            return $instance->getQuery();
        }

        return $instance;
    }

    /**
     * Resolve the proper column name be used.
     *
     * @param string $column
     * @return string
     */
    protected function resolveRelationColumn($column)
    {
        return $column;
    }

    /**
     * Compile queries for column search.
     *
     * @param int    $i
     * @param string $column
     * @param string $keyword
     */
    protected function compileColumnSearch($i, $column, $keyword)
    {
        if ($this->request->isRegex($i)) {
            $this->regexColumnSearch($column, $keyword);
        } else {
            $this->compileQuerySearch($this->query, $column, $keyword, '');
        }
    }

    /**
     * Compile regex query column search.
     *
     * @param mixed  $column
     * @param string $keyword
     */
    protected function regexColumnSearch($column, $keyword)
    {
        $column = $this->wrap($column);

        switch ($this->connection->getDriverName()) {
            case 'oracle':
                $sql = ! $this->config->isCaseInsensitive()
                    ? 'REGEXP_LIKE( ' . $column . ' , ? )'
                    : 'REGEXP_LIKE( LOWER(' . $column . ') , ?, \'i\' )';
                break;

            case 'pgsql':
                $column = $this->castColumn($column);
                $sql    = ! $this->config->isCaseInsensitive() ? $column . ' ~ ?' : $column . ' ~* ? ';
                break;

            default:
                $sql = ! $this->config->isCaseInsensitive()
                    ? $column . ' REGEXP ?'
                    : 'LOWER(' . $column . ') REGEXP ?';
                $keyword = Str::lower($keyword);
        }

        $this->query->whereRaw($sql, [$keyword]);
    }

    /**
     * Wrap a column and cast based on database driver.
     *
     * @param  string $column
     * @return string
     */
    protected function castColumn($column)
    {
        switch ($this->connection->getDriverName()) {
            case 'pgsql':
                return 'CAST(' . $column . ' as TEXT)';
            case 'firebird':
                return 'CAST(' . $column . ' as VARCHAR(255))';
            default:
                return $column;
        }
    }

    /**
     * Compile query builder where clause depending on configurations.
     *
     * @param mixed  $query
     * @param string $column
     * @param string $keyword
     * @param string $boolean
     */
    protected function compileQuerySearch($query, $column, $keyword, $boolean = 'or')
    {
        $column = $this->addTablePrefix($query, $column);
        $column = $this->castColumn($column);
        $sql    = $column . ' LIKE ?';

        if ($this->config->isCaseInsensitive()) {
            $sql = 'LOWER(' . $column . ') LIKE ?';
        }

        $query->{$boolean . 'WhereRaw'}($sql, [$this->prepareKeyword($keyword)]);
    }

    /**
     * Patch for fix about ambiguous field.
     * Ambiguous field error will appear when query use join table and search with keyword.
     *
     * @param mixed  $query
     * @param string $column
     * @return string
     */
    protected function addTablePrefix($query, $column)
    {
        if (strpos($column, '.') === false) {
            $q = $this->getBaseQueryBuilder($query);
            if (! $q->from instanceof Expression) {
                $column = $q->from . '.' . $column;
            }
        }

        return $this->wrap($column);
    }

    /**
     * Prepare search keyword based on configurations.
     *
     * @param string $keyword
     * @return string
     */
    protected function prepareKeyword($keyword)
    {
        if ($this->config->isStartsWithSearch()) {
            return "$keyword%";
        }

        if ($this->config->isCaseInsensitive()) {
            $keyword = Str::lower($keyword);
        }

        if ($this->config->isWildcard()) {
            $keyword = Helper::wildcardLikeString($keyword);
        }

        if ($this->config->isSmartSearch()) {
            $keyword = "%$keyword%";
        }

        return $keyword;
    }

    /**
     * Add custom filter handler for the give column.
     *
     * @param string   $column
     * @param callable $callback
     * @return $this
     */
    public function filterColumn($column, callable $callback)
    {
        $this->columnDef['filter'][$column] = ['method' => $callback];

        return $this;
    }

    /**
     * Order each given columns versus the given custom sql.
     *
     * @param array  $columns
     * @param string $sql
     * @param array  $bindings
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
     * @param string|\Closure $sql
     * @param array  $bindings
     * @return $this
     * @internal string $1 Special variable that returns the requested order direction of the column.
     */
    public function orderColumn($column, $sql, $bindings = [])
    {
        $this->columnDef['order'][$column] = compact('sql', 'bindings');

        return $this;
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
     * Paginate dataTable using limit without offset
     * with additional where clause via callback.
     *
     * @param callable $callback
     * @return $this
     */
    public function limit(callable $callback)
    {
        $this->limitCallback = $callback;

        return $this;
    }

    /**
     * Perform pagination.
     *
     * @return void
     */
    public function paging()
    {
        $limit = (int) $this->request->input('length') > 0 ? $this->request->input('length') : 10;
        if (is_callable($this->limitCallback)) {
            $this->query->limit($limit);
            call_user_func_array($this->limitCallback, [$this->query]);
        } else {
            $this->query->skip($this->request->input('start'))->take($limit);
        }
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
        $this->pushToBlacklist($name);

        return parent::addColumn($name, $content, $order);
    }

    /**
     * Resolve callback parameter instance.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function resolveCallbackParameter()
    {
        return $this->query;
    }

    /**
     * Perform default query orderBy clause.
     */
    protected function defaultOrdering()
    {
        collect($this->request->orderableColumns())
            ->map(function ($orderable) {
                $orderable['name'] = $this->getColumnName($orderable['column'], true);

                return $orderable;
            })
            ->reject(function ($orderable) {
                return $this->isBlacklisted($orderable['name']) && ! $this->hasOrderColumn($orderable['name']);
            })
            ->each(function ($orderable) {
                $column = $this->resolveRelationColumn($orderable['name']);

                if ($this->hasOrderColumn($column)) {
                    $this->applyOrderColumn($column, $orderable);
                } else {
                    $nullsLastSql = $this->getNullsLastSql($column, $orderable['direction']);
                    $normalSql = $this->wrap($column) . ' ' . $orderable['direction'];
                    $sql = $this->nullsLast ? $nullsLastSql : $normalSql;
                    $this->query->orderByRaw($sql);
                }
            });
    }

    /**
     * Check if column has custom sort handler.
     *
     * @param string $column
     * @return bool
     */
    protected function hasOrderColumn($column)
    {
        return isset($this->columnDef['order'][$column]);
    }

    /**
     * Apply orderColumn custom query.
     *
     * @param string $column
     * @param array $orderable
     */
    protected function applyOrderColumn($column, $orderable)
    {
        $sql = $this->columnDef['order'][$column]['sql'];
        if ($sql === false) {
            return;
        }

        if (is_callable($sql)) {
            call_user_func($sql, $this->query, $orderable['direction']);
        } else {
            $sql      = str_replace('$1', $orderable['direction'], $sql);
            $bindings = $this->columnDef['order'][$column]['bindings'];
            $this->query->orderByRaw($sql, $bindings);
        }
    }

    /**
     * Get NULLS LAST SQL.
     *
     * @param  string $column
     * @param  string $direction
     * @return string
     */
    protected function getNullsLastSql($column, $direction)
    {
        $sql = $this->config->get('datatables.nulls_last_sql', '%s %s NULLS LAST');

        return str_replace(
            [':column', ':direction'],
            [$column, $direction],
            sprintf($sql, $column, $direction)
        );
    }

    /**
     * Perform global search for the given keyword.
     *
     * @param string $keyword
     */
    protected function globalSearch($keyword)
    {
        $this->query->where(function ($query) use ($keyword) {
            collect($this->request->searchableColumnIndex())
                ->map(function ($index) {
                    return $this->getColumnName($index);
                })
                ->reject(function ($column) {
                    return $this->isBlacklisted($column) && ! $this->hasFilterColumn($column);
                })
                ->each(function ($column) use ($keyword, $query) {
                    if ($this->hasFilterColumn($column)) {
                        $this->applyFilterColumn($query, $column, $keyword, 'or');
                    } else {
                        $this->compileQuerySearch($query, $column, $keyword);
                    }

                    $this->isFilterApplied = true;
                });
        });
    }

    /**
     * Append debug parameters on output.
     *
     * @param  array $output
     * @return array
     */
    protected function showDebugger(array $output)
    {
        $query_log = $this->connection->getQueryLog();
        array_walk_recursive($query_log, function (&$item) {
            if (is_string($item)) {
                $item = utf8_encode($item);
            }
        });

        $output['queries'] = $query_log;
        $output['input']   = $this->request->all();

        return $output;
    }

    /**
     * Attach custom with meta on response.
     *
     * @param array $data
     * @return array
     */
    protected function attachAppends(array $data)
    {
        $appends = [];
        foreach ($this->appends as $key => $value) {
            if (is_callable($value)) {
                $appends[$key] = value($value($this->getFilteredQuery()));
            } else {
                $appends[$key] = $value;
            }
        }

        return array_merge($data, $appends);
    }
}
