<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\JsonResponse;
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
     * Flag to keep the select bindings.
     *
     * @var bool
     */
    protected bool $keepSelectBindings = false;

    /**
     * @param  QueryBuilder  $builder
     */
    public function __construct(QueryBuilder $builder)
    {
        $this->query = $builder;
        $this->request = app('datatables.request');
        $this->config = app('datatables.config');
        $this->columns = $builder->columns;

        if ($this->config->isDebugging()) {
            $this->getConnection()->enableQueryLog();
        }
    }

    /**
     * @return \Illuminate\Database\Connection
     */
    public function getConnection(): Connection
    {
        /** @var Connection $connection */
        $connection = $this->query->getConnection();

        return $connection;
    }

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param  mixed  $source
     * @return bool
     */
    public static function canCreate($source): bool
    {
        return $source instanceof QueryBuilder && ! ($source instanceof EloquentBuilder);
    }

    /**
     * Organizes works.
     *
     * @param  bool  $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function make($mDataSupport = true): JsonResponse
    {
        try {
            $results = $this->prepareQuery()->results();
            $processed = $this->processResults($results, $mDataSupport);
            $data = $this->transform($results, $processed);

            return $this->render($data);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception);
        }
    }

    /**
     * Prepare query by executing count, filter, order and paginate.
     *
     * @return $this
     */
    protected function prepareQuery(): static
    {
        if (! $this->prepared) {
            $this->totalRecords = $this->totalCount();

            $this->filterRecords();
            $this->ordering();
            $this->paginate();
        }

        $this->prepared = true;

        return  $this;
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
            $table = $this->getConnection()->raw('('.$builder->toSql().') count_row_table');

            return $this->getConnection()->table($table)->setBindings($builder->getBindings());
        }

        $row_count = $this->wrap('row_count');
        $builder->select($this->getConnection()->raw("'1' as {$row_count}"));
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
        return $this->getConnection()->getQueryGrammar()->wrap($column);
    }

    /**
     * Get paginated results.
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    public function results(): Collection
    {
        return $this->query->get();
    }

    /**
     * Keep the select bindings.
     *
     * @return $this
     */
    public function keepSelectBindings(): static
    {
        $this->keepSelectBindings = true;

        return $this;
    }

    /**
     * Perform column search.
     *
     * @return void
     */
    public function columnSearch(): void
    {
        $columns = $this->request->columns();

        foreach ($columns as $index => $column) {
            $column = $this->getColumnName($index);

            if (is_null($column)) {
                continue;
            }

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
        }
    }

    /**
     * Check if column has custom filter handler.
     *
     * @param  string  $columnName
     * @return bool
     */
    public function hasFilterColumn(string $columnName): bool
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
    protected function getColumnSearchKeyword(int $i, bool $raw = false): string
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
     * @param  QueryBuilder  $query
     * @param  string  $columnName
     * @param  string  $keyword
     * @param  string  $boolean
     * @return void
     */
    protected function applyFilterColumn($query, string $columnName, string $keyword, string $boolean = 'and'): void
    {
        $query = $this->getBaseQueryBuilder($query);
        $callback = $this->columnDef['filter'][$columnName]['method'];

        if ($this->query instanceof EloquentBuilder) {
            $builder = $this->query->newModelInstance()->newQuery();
        } else {
            $builder = $this->query->newQuery();
        }

        $callback($builder, $keyword);

        /** @var \Illuminate\Database\Query\Builder $baseQueryBuilder */
        $baseQueryBuilder = $this->getBaseQueryBuilder($builder);
        $query->addNestedWhereQuery($baseQueryBuilder, $boolean);
    }

    /**
     * Get the base query builder instance.
     *
     * @param  QueryBuilder|EloquentBuilder|null  $instance
     * @return QueryBuilder
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
    protected function resolveRelationColumn(string $column): string
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
    protected function compileColumnSearch(int $i, string $column, string $keyword): void
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
     * @param  string  $column
     * @param  string  $keyword
     * @return void
     */
    protected function regexColumnSearch(string $column, string $keyword): void
    {
        $column = $this->wrap($column);

        switch ($this->getConnection()->getDriverName()) {
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
        switch ($this->getConnection()->getDriverName()) {
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
    protected function prepareKeyword(string $keyword): string
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
     * @return $this
     */
    public function filterColumn($column, callable $callback): static
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
     * @return $this
     */
    public function orderColumns(array $columns, $sql, $bindings = []): static
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
     * @return $this
     *
     * @internal string $1 Special variable that returns the requested order direction of the column.
     */
    public function orderColumn($column, $sql, $bindings = []): static
    {
        $this->columnDef['order'][$column] = compact('sql', 'bindings');

        return $this;
    }

    /**
     * Set datatables to do ordering with NULLS LAST option.
     *
     * @return $this
     */
    public function orderByNullsLast(): static
    {
        $this->nullsLast = true;

        return $this;
    }

    /**
     * Paginate dataTable using limit without offset
     * with additional where clause via callback.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function limit(callable $callback): static
    {
        $this->limitCallback = $callback;

        return $this;
    }

    /**
     * Perform pagination.
     *
     * @return void
     */
    public function paging(): void
    {
        $start = $this->request->start();
        $length = $this->request->length();

        $limit = $length > 0 ? $length : 10;

        if (is_callable($this->limitCallback)) {
            $this->query->limit($limit);
            call_user_func_array($this->limitCallback, [$this->query]);
        } else {
            $this->query->skip($start)->take($limit);
        }
    }

    /**
     * Add column in collection.
     *
     * @param  string  $name
     * @param  string|callable  $content
     * @param  bool|int  $order
     * @return $this
     */
    public function addColumn($name, $content, $order = false): static
    {
        $this->pushToBlacklist($name);

        return parent::addColumn($name, $content, $order);
    }

    /**
     * Perform search using search pane values.
     *
     * @return void
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function searchPanesSearch(): void
    {
        /** @var string[] $columns */
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
        }
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
     *
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
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected function getNullsLastSql($column, $direction): string
    {
        /** @var string $sql */
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
     * @return void
     */
    protected function globalSearch(string $keyword): void
    {
        $this->query->where(function ($query) use ($keyword) {
            collect($this->request->searchableColumnIndex())
                ->map(function ($index) {
                    return $this->getColumnName($index);
                })
                ->filter()
                ->reject(function ($column) {
                    return $this->isBlacklisted($column) && ! $this->hasFilterColumn($column);
                })
                ->each(function ($column) use ($keyword, $query) {
                    if ($this->hasFilterColumn($column)) {
                        $this->applyFilterColumn($query, $column, $keyword, 'or');
                    } else {
                        $this->compileQuerySearch($query, $column, $keyword);
                    }
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
        $query_log = $this->getConnection()->getQueryLog();
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
