<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Yajra\DataTables\Utilities\Helper;

class QueryDataTable extends DataTableAbstract
{
    /**
     * Builder object.
     *
     * @var QueryBuilder
     */
    protected QueryBuilder $query;

    /**
     * Database connection used.
     *
     * @var \Illuminate\Database\Connection
     */
    protected Connection $connection;

    /**
     * Flag for ordering NULLS LAST option.
     *
     * @var bool
     */
    protected bool $nullsLast = false;

    /**
     * Flag to check if query preparation was already done.
     *
     * @var bool
     */
    protected bool $prepared = false;

    /**
     * Query callback for custom pagination using limit without offset.
     *
     * @var callable|null
     */
    protected $limitCallback = null;

    /**
     * Flag to skip total records count query.
     *
     * @var bool
     */
    protected bool $skipTotalRecords = false;

    /**
     * Flag to keep the select bindings.
     *
     * @var bool
     */
    protected bool $keepSelectBindings = false;

    /**
     * @param  \Illuminate\Database\Query\Builder  $builder
     */
    public function __construct(QueryBuilder $builder)
    {
        $this->query = $builder;
        $this->request = app('datatables.request');
        $this->config = app('datatables.config');
        $this->columns = $builder->columns;

        /** @var \Illuminate\Database\Connection connection */
        $connection = $builder->getConnection();

        $this->connection = $connection;
        if ($this->config->isDebugging()) {
            $connection->enableQueryLog();
        }
    }

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param  mixed  $source
     * @return bool
     */
    public static function canCreate($source): bool
    {
        return $source instanceof QueryBuilder;
    }

    /**
     * Organizes works.
     *
     * @param  bool  $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function make($mDataSupport = true)
    {
        try {
            $this->prepareQuery();

            $results = $this->results();
            $processed = $this->processResults($results, $mDataSupport);
            $data = $this->transform($results, $processed);

            return $this->render($data);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * Prepare query by executing count, filter, order and paginate.
     */
    protected function prepareQuery(): void
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

        return $this->totalRecords ?: $this->count();
    }

    /**
     * Counts current query.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->prepareCountQuery()->count();
    }

    /**
     * Prepare count query builder.
     *
     * @return QueryBuilder
     */
    public function prepareCountQuery(): QueryBuilder
    {
        $builder = clone $this->query;

        if ($this->isComplexQuery($builder)) {
            $table = $this->connection->raw('('.$builder->toSql().') count_row_table');

            return $this->connection->table($table)->setBindings($builder->getBindings());
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
     * @param  QueryBuilder|EloquentBuilder  $query
     * @return bool
     */
    protected function isComplexQuery($query): bool
    {
        return Str::contains(Str::lower($query->toSql()), ['union', 'having', 'distinct', 'order by', 'group by']);
    }

    /**
     * Wrap column with DB grammar.
     *
     * @param  string  $column
     * @return string
     */
    protected function wrap(string $column): string
    {
        return $this->connection->getQueryGrammar()->wrap($column);
    }

    /**
     * Get paginated results.
     *
     * @return \Illuminate\Support\Collection
     */
    public function results(): Collection
    {
        return $this->query->get();
    }

    /**
     * Skip total records and set the recordsTotal equals to recordsFiltered.
     * This will improve the performance by skipping the total count query.
     *
     * @return static
     */
    public function skipTotalRecords()
    {
        $this->skipTotalRecords = true;

        return $this;
    }

    /**
     * Keep the select bindings.
     *
     * @return static
     */
    public function keepSelectBindings()
    {
        $this->keepSelectBindings = true;

        return $this;
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
                $column = $this->resolveRelationColumn($column);
                $keyword = $this->getColumnSearchKeyword($index);
                $this->compileColumnSearch($index, $column, $keyword);
            }

            $this->isFilterApplied = true;
        }
    }

    /**
     * Check if column has custom filter handler.
     *
     * @param  string  $columnName
     * @return bool
     */
    public function hasFilterColumn($columnName)
    {
        return isset($this->columnDef['filter'][$columnName]);
    }

    /**
     * Get column keyword to use for search.
     *
     * @param  int  $i
     * @param  bool  $raw
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
     * @param  mixed  $query
     * @param  string  $columnName
     * @param  string  $keyword
     * @param  string  $boolean
     * @return void
     */
    protected function applyFilterColumn($query, $columnName, $keyword, $boolean = 'and'): void
    {
        $query = $this->getBaseQueryBuilder($query);
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
     * @param  mixed  $instance
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
     * @param  string  $column
     * @return string
     */
    protected function resolveRelationColumn($column)
    {
        return $column;
    }

    /**
     * Compile queries for column search.
     *
     * @param  int  $i
     * @param  string  $column
     * @param  string  $keyword
     * @return void
     */
    protected function compileColumnSearch($i, $column, $keyword): void
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
     * @param  mixed  $column
     * @param  string  $keyword
     * @return void
     */
    protected function regexColumnSearch($column, $keyword): void
    {
        $column = $this->wrap($column);

        switch ($this->connection->getDriverName()) {
            case 'oracle':
                $sql = ! $this->config->isCaseInsensitive()
                    ? 'REGEXP_LIKE( '.$column.' , ? )'
                    : 'REGEXP_LIKE( LOWER('.$column.') , ?, \'i\' )';
                break;

            case 'pgsql':
                $column = $this->castColumn($column);
                $sql = ! $this->config->isCaseInsensitive() ? $column.' ~ ?' : $column.' ~* ? ';
                break;

            default:
                $sql = ! $this->config->isCaseInsensitive()
                    ? $column.' REGEXP ?'
                    : 'LOWER('.$column.') REGEXP ?';
                $keyword = Str::lower($keyword);
        }

        $this->query->whereRaw($sql, [$keyword]);
    }

    /**
     * Wrap a column and cast based on database driver.
     *
     * @param  string  $column
     * @return string
     */
    protected function castColumn(string $column): string
    {
        switch ($this->connection->getDriverName()) {
            case 'pgsql':
                return 'CAST('.$column.' as TEXT)';
            case 'firebird':
                return 'CAST('.$column.' as VARCHAR(255))';
            default:
                return $column;
        }
    }

    /**
     * Compile query builder where clause depending on configurations.
     *
     * @param  QueryBuilder|EloquentBuilder  $query
     * @param  string  $column
     * @param  string  $keyword
     * @param  string  $boolean
     * @return void
     */
    protected function compileQuerySearch($query, string $column, string $keyword, string $boolean = 'or'): void
    {
        $column = $this->addTablePrefix($query, $column);
        $column = $this->castColumn($column);
        $sql = $column.' LIKE ?';

        if ($this->config->isCaseInsensitive()) {
            $sql = 'LOWER('.$column.') LIKE ?';
        }

        $query->{$boolean.'WhereRaw'}($sql, [$this->prepareKeyword($keyword)]);
    }

    /**
     * Patch for fix about ambiguous field.
     * Ambiguous field error will appear when query use join table and search with keyword.
     *
     * @param  QueryBuilder|EloquentBuilder  $query
     * @param  string  $column
     * @return string
     */
    protected function addTablePrefix($query, string $column): string
    {
        if (! str_contains($column, '.')) {
            $q = $this->getBaseQueryBuilder($query);
            /** @phpstan-ignore-next-line */
            if (! $q->from instanceof Expression) {
                $column = $q->from.'.'.$column;
            }
        }

        return $this->wrap($column);
    }

    /**
     * Prepare search keyword based on configurations.
     *
     * @param  string  $keyword
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
     * @param  string  $column
     * @param  callable  $callback
     * @return static
     */
    public function filterColumn($column, callable $callback)
    {
        $this->columnDef['filter'][$column] = ['method' => $callback];

        return $this;
    }

    /**
     * Order each given columns versus the given custom sql.
     *
     * @param  array  $columns
     * @param  string  $sql
     * @param  array  $bindings
     * @return static
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
     * @param  string  $column
     * @param  string|\Closure  $sql
     * @param  array  $bindings
     * @return static
     *
     * @internal string $1 Special variable that returns the requested order direction of the column.
     */
    public function orderColumn($column, $sql, $bindings = []): self
    {
        $this->columnDef['order'][$column] = compact('sql', 'bindings');

        return $this;
    }

    /**
     * Set datatables to do ordering with NULLS LAST option.
     *
     * @return static
     */
    public function orderByNullsLast(): self
    {
        $this->nullsLast = true;

        return $this;
    }

    /**
     * Paginate dataTable using limit without offset
     * with additional where clause via callback.
     *
     * @param  callable  $callback
     * @return static
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
     * @param  string  $name
     * @param  string|callable  $content
     * @param  bool|int  $order
     * @return static
     */
    public function addColumn($name, $content, $order = false): self
    {
        $this->pushToBlacklist($name);

        return parent::addColumn($name, $content, $order);
    }

    /**
     * Perform search using search pane values.
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function searchPanesSearch(): void
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
     * Count filtered items.
     *
     * @return int
     */
    protected function filteredCount(): int
    {
        $this->filteredRecords = $this->filteredRecords ?: $this->count();
        if ($this->skipTotalRecords) {
            $this->totalRecords = $this->filteredRecords;
        }

        return $this->filteredRecords;
    }

    /**
     * Resolve callback parameter instance.
     *
     * @return QueryBuilder
     */
    protected function resolveCallbackParameter()
    {
        return $this->query;
    }

    /**
     * Perform default query orderBy clause.
     *
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function defaultOrdering(): void
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
                    $normalSql = $this->wrap($column).' '.$orderable['direction'];
                    $sql = $this->nullsLast ? $nullsLastSql : $normalSql;
                    $this->query->orderByRaw($sql);
                }
            });
    }

    /**
     * Check if column has custom sort handler.
     *
     * @param  string  $column
     * @return bool
     */
    protected function hasOrderColumn(string $column): bool
    {
        return isset($this->columnDef['order'][$column]);
    }

    /**
     * Apply orderColumn custom query.
     *
     * @param  string  $column
     * @param  array  $orderable
     */
    protected function applyOrderColumn(string $column, array $orderable): void
    {
        $sql = $this->columnDef['order'][$column]['sql'];
        if ($sql === false) {
            return;
        }

        if (is_callable($sql)) {
            call_user_func($sql, $this->query, $orderable['direction']);
        } else {
            $sql = str_replace('$1', $orderable['direction'], $sql);
            $bindings = $this->columnDef['order'][$column]['bindings'];
            $this->query->orderByRaw($sql, $bindings);
        }
    }

    /**
     * Get NULLS LAST SQL.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return string
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
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
     * @param  string  $keyword
     */
    protected function globalSearch($keyword): void
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
     * @param  array  $output
     * @return array
     */
    protected function showDebugger(array $output): array
    {
        $query_log = $this->connection->getQueryLog();
        array_walk_recursive($query_log, function (&$item) {
            if (is_string($item)) {
                $item = utf8_encode($item);
            }
        });

        $output['queries'] = $query_log;
        $output['input'] = $this->request->all();

        return $output;
    }

    /**
     * Attach custom with meta on response.
     *
     * @param  array  $data
     * @return array
     */
    protected function attachAppends(array $data): array
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

    /**
     * Get filtered, ordered and paginated query.
     *
     * @return QueryBuilder
     */
    public function getFilteredQuery(): QueryBuilder
    {
        $this->prepareQuery();

        return $this->getQuery();
    }

    /**
     * Get query builder instance.
     *
     * @return QueryBuilder
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }
}
