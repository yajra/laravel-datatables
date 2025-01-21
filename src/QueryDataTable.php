<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Utilities\Helper;

class QueryDataTable extends DataTableAbstract
{
    /**
     * Flag for ordering NULLS LAST option.
     */
    protected bool $nullsLast = false;

    /**
     * Flag to check if query preparation was already done.
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
     */
    protected bool $keepSelectBindings = false;

    /**
     * Flag to ignore the selects in count query.
     */
    protected bool $ignoreSelectInCountQuery = false;

    /**
     * Enable scout search and use this model for searching.
     */
    protected ?Model $scoutModel = null;

    /**
     * Maximum number of hits to return from scout.
     */
    protected int $scoutMaxHits = 1000;

    /**
     * Add dynamic filters to scout search.
     *
     * @var callable|null
     */
    protected $scoutFilterCallback = null;

    /**
     * Flag if scout search was performed.
     */
    protected bool $scoutSearched = false;

    /**
     * Scout index name.
     */
    protected string $scoutIndex;

    /**
     * Scout key name.
     */
    protected string $scoutKey;

    /**
     * Flag to disable user ordering if a fixed ordering was performed (e.g. scout search).
     * Only works with corresponding javascript listener.
     */
    protected bool $disableUserOrdering = false;

    public function __construct(protected QueryBuilder $query)
    {
        $this->request = app('datatables.request');
        $this->config = app('datatables.config');
        $this->columns = $this->query->getColumns();

        if ($this->config->isDebugging()) {
            $this->getConnection()->enableQueryLog();
        }
    }

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
     */
    public static function canCreate($source): bool
    {
        return $source instanceof QueryBuilder && ! ($source instanceof EloquentBuilder);
    }

    /**
     * Organizes works.
     *
     * @throws \Exception
     */
    public function make(bool $mDataSupport = true): JsonResponse
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
     * Get paginated results.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    public function results(): Collection
    {
        return $this->query->get();
    }

    /**
     * Prepare query by executing count, filter, order and paginate.
     *
     * @return $this
     */
    public function prepareQuery(): static
    {
        if (! $this->prepared) {
            $this->totalRecords = $this->totalCount();

            $this->filterRecords();
            $this->ordering();
            $this->paginate();
        }

        $this->prepared = true;

        return $this;
    }

    /**
     * Counts current query.
     */
    public function count(): int
    {
        return $this->prepareCountQuery()->count();
    }

    /**
     * Prepare count query builder.
     */
    public function prepareCountQuery(): QueryBuilder
    {
        $builder = clone $this->query;

        if ($this->isComplexQuery($builder)) {
            $builder->select(DB::raw('1 as dt_row_count'));
            $clone = $builder->clone();
            $clone->setBindings([]);
            if ($clone instanceof EloquentBuilder) {
                $clone->getQuery()->wheres = [];
            } else {
                $clone->wheres = [];
            }

            if ($this->isComplexQuery($clone)) {
                if (! $this->ignoreSelectInCountQuery) {
                    $builder = clone $this->query;
                }

                return $this->getConnection()
                    ->query()
                    ->fromRaw('('.$builder->toSql().') count_row_table')
                    ->setBindings($builder->getBindings());
            }
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
     */
    protected function isComplexQuery($query): bool
    {
        return Str::contains(Str::lower($query->toSql()), ['union', 'having', 'distinct', 'order by', 'group by']);
    }

    /**
     * Wrap column with DB grammar.
     */
    protected function wrap(string $column): string
    {
        return $this->getConnection()->getQueryGrammar()->wrap($column);
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
     */
    protected function filterRecords(): void
    {
        $initialQuery = clone $this->query;

        if ($this->autoFilter && $this->request->isSearchable()) {
            $this->filtering();
        }

        if (is_callable($this->filterCallback)) {
            call_user_func_array($this->filterCallback, $this->resolveCallbackParameter());
        }

        $this->columnSearch();
        $this->searchPanesSearch();

        // If no modification between the original query and the filtered one has been made
        // the filteredRecords equals the totalRecords
        if (! $this->skipTotalRecords && $this->query == $initialQuery) {
            $this->filteredRecords ??= $this->totalRecords;
        } else {
            $this->filteredCount();

            if ($this->skipTotalRecords) {
                $this->totalRecords = $this->filteredRecords;
            }
        }
    }

    /**
     * Perform column search.
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
     */
    public function hasFilterColumn(string $columnName): bool
    {
        return isset($this->columnDef['filter'][$columnName]);
    }

    /**
     * Get column keyword to use for search.
     */
    protected function getColumnSearchKeyword(int $i, bool $raw = false): string
    {
        $keyword = $this->request->columnKeyword($i);
        if ($raw || $this->request->isRegex($i)) {
            return $keyword;
        }

        return $this->setupKeyword($keyword);
    }

    protected function getColumnNameByIndex(int $index): string
    {
        $name = (isset($this->columns[$index]) && $this->columns[$index] != '*')
            ? $this->columns[$index]
            : $this->getPrimaryKeyName();

        if ($name instanceof Expression) {
            $name = $name->getValue($this->query->getGrammar());
        }

        return in_array($name, $this->extraColumns, true) ? $this->getPrimaryKeyName() : $name;
    }

    /**
     * Apply filterColumn api search.
     *
     * @param  QueryBuilder  $query
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
     */
    protected function getBaseQueryBuilder($instance = null): QueryBuilder
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
     * Get query builder instance.
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Resolve the proper column name be used.
     */
    protected function resolveRelationColumn(string $column): string
    {
        return $column;
    }

    /**
     * Compile queries for column search.
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
     */
    protected function castColumn(string $column): string
    {
        return match ($this->getConnection()->getDriverName()) {
            'pgsql' => 'CAST('.$column.' as TEXT)',
            'firebird' => 'CAST('.$column.' as VARCHAR(255))',
            default => $column,
        };
    }

    /**
     * Compile query builder where clause depending on configurations.
     *
     * @param  QueryBuilder|EloquentBuilder  $query
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
     */
    protected function addTablePrefix($query, string $column): string
    {
        if (! str_contains($column, '.')) {
            $q = $this->getBaseQueryBuilder($query);
            $from = $q->from ?? '';

            if (! $from instanceof Expression) {
                if (str_contains((string) $from, ' as ')) {
                    $from = explode(' as ', (string) $from)[1];
                }

                $column = $from.'.'.$column;
            }
        }

        return $this->wrap($column);
    }

    /**
     * Prepare search keyword based on configurations.
     */
    protected function prepareKeyword(string $keyword): string
    {
        if ($this->config->isCaseInsensitive()) {
            $keyword = Str::lower($keyword);
        }

        if ($this->config->isStartsWithSearch()) {
            return "$keyword%";
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
     * Perform pagination.
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
     * Paginate dataTable using limit without offset
     * with additional where clause via callback.
     *
     * @return $this
     */
    public function limit(callable $callback): static
    {
        $this->limitCallback = $callback;

        return $this;
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
     * @return array<int|string, mixed>
     */
    protected function resolveCallbackParameter(): array
    {
        return [$this->query, $this->scoutSearched];
    }

    /**
     * Perform default query orderBy clause.
     *
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
            ->reject(fn ($orderable) => $this->isBlacklisted($orderable['name']) && ! $this->hasOrderColumn($orderable['name']))
            ->each(function ($orderable) {
                $column = $this->resolveRelationColumn($orderable['name']);

                if ($this->hasOrderColumn($orderable['name'])) {
                    $this->applyOrderColumn($orderable['name'], $orderable);
                } elseif ($this->hasOrderColumn($column)) {
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
     */
    protected function hasOrderColumn(string $column): bool
    {
        return isset($this->columnDef['order'][$column]);
    }

    /**
     * Apply orderColumn custom query.
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
            $sql = str_replace('$1', $orderable['direction'], (string) $sql);
            $bindings = $this->columnDef['order'][$column]['bindings'];
            $this->query->orderByRaw($sql, $bindings);
        }
    }

    /**
     * Get NULLS LAST SQL.
     *
     * @param  string  $column
     * @param  string  $direction
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
     */
    protected function globalSearch(string $keyword): void
    {
        // Try scout search first & fall back to default search if disabled/failed
        if ($this->applyScoutSearch($keyword)) {
            return;
        }

        $this->query->where(function ($query) use ($keyword) {
            collect($this->request->searchableColumnIndex())
                ->map(fn ($index) => $this->getColumnName($index))
                ->filter()
                ->reject(fn ($column) => $this->isBlacklisted($column) && ! $this->hasFilterColumn($column))
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
     * Perform multi-term search by splitting keyword into
     * individual words and searches for each of them.
     *
     * @param  string  $keyword
     */
    protected function smartGlobalSearch($keyword): void
    {
        // Try scout search first & fall back to default search if disabled/failed
        if ($this->applyScoutSearch($keyword)) {
            return;
        }

        parent::smartGlobalSearch($keyword);
    }

    /**
     * Append debug parameters on output.
     */
    protected function showDebugger(array $output): array
    {
        $query_log = $this->getConnection()->getQueryLog();
        array_walk_recursive($query_log, function (&$item) {
            if (is_string($item) && extension_loaded('iconv')) {
                $item = iconv('iso-8859-1', 'utf-8', $item);
            }
        });

        $output['queries'] = $query_log;
        $output['input'] = $this->request->all();

        return $output;
    }

    /**
     * Attach custom with meta on response.
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

        // Set flag to disable ordering
        $appends['disableOrdering'] = $this->disableUserOrdering;

        return array_merge($data, $appends);
    }

    /**
     * Get filtered, ordered and paginated query.
     */
    public function getFilteredQuery(): QueryBuilder
    {
        $this->prepareQuery();

        return $this->getQuery();
    }

    /**
     * Ignore the selects in count query.
     *
     * @return $this
     */
    public function ignoreSelectsInCountQuery(): static
    {
        $this->ignoreSelectInCountQuery = true;

        return $this;
    }

    /**
     * Perform sorting of columns.
     */
    public function ordering(): void
    {
        // Skip if user ordering is disabled (e.g. scout search)
        if ($this->disableUserOrdering) {
            return;
        }

        parent::ordering();
    }

    /**
     * Enable scout search and use provided model for searching.
     * $max_hits is the maximum number of hits to return from scout.
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function enableScoutSearch(string $model, int $max_hits = 1000): static
    {
        $scout_model = new $model;
        if (! class_exists($model) || ! ($scout_model instanceof Model)) {
            throw new \Exception("$model must be an Eloquent Model.");
        }
        if (! method_exists($scout_model, 'searchableAs') || ! method_exists($scout_model, 'getScoutKeyName')) {
            throw new \Exception("$model must use the Searchable trait.");
        }

        $this->scoutModel = $scout_model;
        $this->scoutMaxHits = $max_hits;
        $this->scoutIndex = $this->scoutModel->searchableAs();
        $this->scoutKey = $this->scoutModel->getScoutKeyName();

        return $this;
    }

    /**
     * Add dynamic filters to scout search.
     *
     * @return $this
     */
    public function scoutFilter(callable $callback): static
    {
        $this->scoutFilterCallback = $callback;

        return $this;
    }

    /**
     * Apply scout search to query if enabled.
     */
    protected function applyScoutSearch(string $search_keyword): bool
    {
        if ($this->scoutModel == null) {
            return false;
        }

        try {
            // Perform scout search
            $search_filters = '';
            if (is_callable($this->scoutFilterCallback)) {
                $search_filters = ($this->scoutFilterCallback)($search_keyword);
            }

            $search_results = $this->performScoutSearch($search_keyword, $search_filters);

            // Apply scout search results to query
            $this->query->where(function ($query) use ($search_results) {
                $this->query->whereIn($this->scoutKey, $search_results);
            });

            // Order by scout search results & disable user ordering (if db driver is supported)
            if (count($search_results) > 0 && $this->applyFixedOrderingToQuery($this->scoutKey, $search_results)) {
                // Disable user ordering because we already ordered by search relevancy
                $this->disableUserOrdering = true;
            }

            $this->scoutSearched = true;

            return true;
        } catch (\Exception) {
            // Scout search failed, fallback to default search
            return false;
        }
    }

    /**
     * Apply fixed ordering to query by a fixed set of values depending on database driver (used for scout search).
     *
     * Currently supported drivers: MySQL
     *
     * @return bool
     */
    protected function applyFixedOrderingToQuery(string $keyName, array $orderedKeys)
    {
        $connection = $this->getConnection();
        $driverName = $connection->getDriverName();

        // Escape keyName and orderedKeys
        $keyName = $connection->getQueryGrammar()->wrap($keyName);
        $orderedKeys = collect($orderedKeys)
            ->map(fn ($value) => $connection->escape($value));

        switch ($driverName) {
            case 'mariadb':
            case 'mysql':
                $this->query->orderByRaw("FIELD($keyName, ".$orderedKeys->implode(',').')');

                return true;

            case 'pgsql':
            case 'oracle':
                $this->query->orderByRaw(
                    'CASE '
                    .
                    $orderedKeys
                        ->map(fn ($value, $index) => "WHEN $keyName=$value THEN $index")
                        ->implode(' ')
                    .
                    ' END'
                );

                return true;

            case 'sqlite':
            case 'sqlsrv':
                $this->query->orderByRaw(
                    "CASE $keyName "
                    .
                    $orderedKeys
                        ->map(fn ($value, $index) => "WHEN $value THEN $index")
                        ->implode(' ')
                    .
                    ' END'
                );

                return true;

            default:
                return false;
        }
    }

    /**
     * Perform a scout search with the configured engine and given parameters. Return matching model IDs.
     *
     *
     * @throws \Exception
     */
    protected function performScoutSearch(string $searchKeyword, mixed $searchFilters = []): array
    {
        if (! class_exists(\Laravel\Scout\EngineManager::class)) {
            throw new \Exception('Laravel Scout is not installed.');
        }
        $engine = app(\Laravel\Scout\EngineManager::class)->engine();

        if ($engine instanceof \Laravel\Scout\Engines\MeilisearchEngine) {
            /** @var \Meilisearch\Client $engine */
            $search_results = $engine
                ->index($this->scoutIndex)
                ->rawSearch($searchKeyword, [
                    'limit' => $this->scoutMaxHits,
                    'attributesToRetrieve' => [$this->scoutKey],
                    'filter' => $searchFilters,
                ]);

            /** @var array<int, array<string, mixed>> $hits */
            $hits = $search_results['hits'] ?? [];

            return collect($hits)
                ->pluck($this->scoutKey)
                ->all();
        } elseif ($engine instanceof \Laravel\Scout\Engines\AlgoliaEngine) {
            /** @var \Algolia\AlgoliaSearch\SearchClient $engine */
            $algolia = $engine->initIndex($this->scoutIndex);

            $search_results = $algolia->search($searchKeyword, [
                'offset' => 0,
                'length' => $this->scoutMaxHits,
                'attributesToRetrieve' => [$this->scoutKey],
                'attributesToHighlight' => [],
                'filters' => $searchFilters,
            ]);

            /** @var array<int, array<string, mixed>> $hits */
            $hits = $search_results['hits'] ?? [];

            return collect($hits)
                ->pluck($this->scoutKey)
                ->all();
        } else {
            throw new \Exception('Unsupported Scout Engine. Currently supported: Meilisearch, Algolia');
        }
    }
}
