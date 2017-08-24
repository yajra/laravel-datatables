<?php

namespace Yajra\Datatables\Engines;

use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Yajra\Datatables\Helper;
use Yajra\Datatables\Request;

/**
 * Class QueryBuilderEngine.
 *
 * @package Yajra\Datatables\Engines
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
class QueryBuilderEngine extends BaseEngine
{
    /**
     * Filtered query results.
     *
     * @var mixed
     */
    protected $results;

    /**
     * Query callback for custom pagination using limit without offset.
     *
     * @var callable
     */
    protected $limitCallback;

    /**
     * @param \Illuminate\Database\Query\Builder $builder
     * @param \Yajra\Datatables\Request $request
     */
    public function __construct(Builder $builder, Request $request)
    {
        $this->query = $builder;
        $this->init($request, $builder);
    }

    /**
     * Initialize attributes.
     *
     * @param  \Yajra\Datatables\Request $request
     * @param  \Illuminate\Database\Query\Builder $builder
     * @param  string $type
     */
    protected function init($request, $builder, $type = 'builder')
    {
        $this->request    = $request;
        $this->query_type = $type;
        $this->columns    = $builder->columns;
        $this->connection = $builder->getConnection();
        $this->prefix     = $this->connection->getTablePrefix();
        $this->database   = $this->connection->getDriverName();
        if ($this->isDebugging()) {
            $this->connection->enableQueryLog();
        }
    }

    /**
     * Set auto filter off and run your own filter.
     * Overrides global search
     *
     * @param \Closure $callback
     * @param bool $globalSearch
     * @return $this
     */
    public function filter(Closure $callback, $globalSearch = false)
    {
        $this->overrideGlobalSearch($callback, $this->query, $globalSearch);

        return $this;
    }

    /**
     * Count total items.
     *
     * @return integer
     */
    public function totalCount()
    {
        return $this->totalRecords ? $this->totalRecords : $this->count();
    }

    /**
     * Counts current query.
     *
     * @return int
     */
    public function count()
    {
        $myQuery = clone $this->query;
        // if its a normal query ( no union, having and distinct word )
        // replace the select with static text to improve performance
        if (! Str::contains(Str::lower($myQuery->toSql()), ['union', 'having', 'distinct', 'order by', 'group by'])) {
            $row_count = $this->wrap('row_count');
            $myQuery->select($this->connection->raw("'1' as {$row_count}"));
        }

        return $this->connection->table($this->connection->raw('(' . $myQuery->toSql() . ') count_row_table'))
                                ->setBindings($myQuery->getBindings())->count();
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
     * Perform global search for the given keyword.
     *
     * @param string $keyword
     */
    protected function globalSearch($keyword)
    {
        $this->query->where(
            function ($query) use ($keyword) {
                $queryBuilder = $this->getQueryBuilder($query);

                foreach ($this->request->searchableColumnIndex() as $index) {
                    $columnName = $this->getColumnName($index);
                    if ($this->isBlacklisted($columnName) && ! $this->hasCustomFilter($columnName)) {
                        continue;
                    }

                    // check if custom column filtering is applied
                    if ($this->hasCustomFilter($columnName)) {
                        $columnDef = $this->columnDef['filter'][$columnName];
                        // check if global search should be applied for the specific column
                        $applyGlobalSearch = count($columnDef['parameters']) == 0 || end($columnDef['parameters']) !== false;
                        if (! $applyGlobalSearch) {
                            continue;
                        }

                        if ($columnDef['method'] instanceof Closure) {
                            $whereQuery = $queryBuilder->newQuery();
                            call_user_func_array($columnDef['method'], [$whereQuery, $keyword]);
                            $queryBuilder->addNestedWhereQuery($whereQuery, 'or');
                        } else {
                            $this->compileColumnQuery(
                                $queryBuilder,
                                Helper::getOrMethod($columnDef['method']),
                                $columnDef['parameters'],
                                $columnName,
                                $keyword
                            );
                        }
                    } else {
                        if (count(explode('.', $columnName)) > 1) {
                            $eagerLoads     = $this->getEagerLoads();
                            $parts          = explode('.', $columnName);
                            $relationColumn = array_pop($parts);
                            $relation       = implode('.', $parts);
                            if (in_array($relation, $eagerLoads)) {
                                $this->compileRelationSearch(
                                    $queryBuilder,
                                    $relation,
                                    $relationColumn,
                                    $keyword
                                );
                            } else {
                                $this->compileQuerySearch($queryBuilder, $columnName, $keyword);
                            }
                        } else {
                            $this->compileQuerySearch($queryBuilder, $columnName, $keyword);
                        }
                    }

                    $this->isFilterApplied = true;
                }
            }
        );
    }

    /**
     * Check if column has custom filter handler.
     *
     * @param  string $columnName
     * @return bool
     */
    public function hasCustomFilter($columnName)
    {
        return isset($this->columnDef['filter'][$columnName]);
    }

    /**
     * Perform filter column on selected field.
     *
     * @param mixed $query
     * @param string|Closure $method
     * @param mixed $parameters
     * @param string $column
     * @param string $keyword
     */
    protected function compileColumnQuery($query, $method, $parameters, $column, $keyword)
    {
        if (method_exists($query, $method)
            && count($parameters) <= with(new \ReflectionMethod($query, $method))->getNumberOfParameters()
        ) {
            if (Str::contains(Str::lower($method), 'raw')
                || Str::contains(Str::lower($method), 'exists')
            ) {
                call_user_func_array(
                    [$query, $method],
                    $this->parameterize($parameters, $keyword)
                );
            } else {
                call_user_func_array(
                    [$query, $method],
                    $this->parameterize($column, $parameters, $keyword)
                );
            }
        }
    }

    /**
     * Build Query Builder Parameters.
     *
     * @return array
     */
    protected function parameterize()
    {
        $args       = func_get_args();
        $keyword    = count($args) > 2 ? $args[2] : $args[1];
        $parameters = Helper::buildParameters($args);
        $parameters = Helper::replacePatternWithKeyword($parameters, $keyword, '$1');

        return $parameters;
    }

    /**
     * Get eager loads keys if eloquent.
     *
     * @return array
     */
    protected function getEagerLoads()
    {
        if ($this->query_type == 'eloquent') {
            return array_keys($this->query->getEagerLoads());
        }

        return [];
    }

    /**
     * Add relation query on global search.
     *
     * @param mixed $query
     * @param string $relation
     * @param string $column
     * @param string $keyword
     */
    protected function compileRelationSearch($query, $relation, $column, $keyword)
    {
        $myQuery = clone $this->query;

        /**
         * For compile nested relation, we need store all nested relation as array
         * and reverse order to apply where query.
         * With this method we can create nested sub query with properly relation.
         */

        /**
         * Store all relation data that require in next step
         */
        $relationChunk = [];

        /**
         * Store last eloquent query builder for get next relation.
         */
        $lastQuery = $query;

        $relations    = explode('.', $relation);
        $lastRelation = end($relations);
        foreach ($relations as $relation) {
            $relationType = $myQuery->getModel()->{$relation}();
            $myQuery->orWhereHas($relation, function ($builder) use (
                $column,
                $keyword,
                $query,
                $relationType,
                $relation,
                $lastRelation,
                &$relationChunk,
                &$lastQuery
            ) {
                $builder->select($this->connection->raw('count(1)'));

                // We will perform search on last relation only.
                if ($relation == $lastRelation) {
                    $this->compileQuerySearch($builder, $column, $keyword, '');
                }

                // Put require object to next step!!
                $relationChunk[$relation] = [
                    'builder'      => $builder,
                    'relationType' => $relationType,
                    'query'        => $lastQuery,
                ];

                // This is trick make sub query.
                $lastQuery = $builder;
            });

            // This is trick to make nested relation by pass previous relation to be next query eloquent builder
            $myQuery = $relationType;
        }

        /**
         * Reverse them all
         */
        $relationChunk = array_reverse($relationChunk, true);

        /**
         * Create valuable for use in check last relation
         */
        end($relationChunk);
        $lastRelation = key($relationChunk);
        reset($relationChunk);

        /**
         * Walking ...
         */
        foreach ($relationChunk as $relation => $chunk) {
            // Prepare variables
            $builder  = $chunk['builder'];
            $query    = $chunk['query'];
            $bindings = $builder->getBindings();
            $builder  = "({$builder->toSql()}) >= 1";

            // Check if it last relation we will use orWhereRaw
            if ($lastRelation == $relation) {
                $relationMethod = "orWhereRaw";
            } else {
                // For case parent relation of nested relation.
                // We must use and for properly query and get correct result
                $relationMethod = "whereRaw";
            }

            $query->{$relationMethod}($builder, $bindings);
        }
    }

    /**
     * Compile query builder where clause depending on configurations.
     *
     * @param mixed $query
     * @param string $column
     * @param string $keyword
     * @param string $relation
     */
    protected function compileQuerySearch($query, $column, $keyword, $relation = 'or')
    {
        $column = $this->addTablePrefix($query, $column);
        $column = $this->castColumn($column);
        $sql    = $column . ' LIKE ?';

        if ($this->isCaseInsensitive()) {
            $sql = 'LOWER(' . $column . ') LIKE ?';
        }

        $query->{$relation . 'WhereRaw'}($sql, [$this->prepareKeyword($keyword)]);
    }

    /**
     * Patch for fix about ambiguous field.
     * Ambiguous field error will appear when query use join table and search with keyword.
     *
     * @param mixed $query
     * @param string $column
     * @return string
     */
    protected function addTablePrefix($query, $column)
    {
        // Check if field does not have a table prefix
        if (strpos($column, '.') === false) {
            // Alternative method to check instanceof \Illuminate\Database\Eloquent\Builder
            if (method_exists($query, 'getQuery')) {
                $q = $query->getQuery();
            } else {
                $q = $query;
            }

            if (! $q->from instanceof Expression) {
                // Get table from query and add it.
                $column = $q->from . '.' . $column;
            }
        }

        return $this->wrap($column);
    }

    /**
     * Wrap a column and cast in pgsql.
     *
     * @param  string $column
     * @return string
     */
    protected function castColumn($column)
    {
        if ($this->database === 'pgsql') {
            $column = 'CAST(' . $column . ' as TEXT)';
        } elseif ($this->database === 'firebird') {
            $column = 'CAST(' . $column . ' as VARCHAR(255))';
        }

        return $column;
    }

    /**
     * Prepare search keyword based on configurations.
     *
     * @param string $keyword
     * @return string
     */
    protected function prepareKeyword($keyword)
    {
        if ($this->isCaseInsensitive()) {
            $keyword = Str::lower($keyword);
        }

        if ($this->isWildcard()) {
            $keyword = $this->wildcardLikeString($keyword);
        }

        if ($this->isSmartSearch()) {
            $keyword = "%$keyword%";
        }

        return $keyword;
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
            if (! $this->request->isColumnSearchable($index)) {
                continue;
            }

            $column = $this->getColumnName($index);

            if (isset($this->columnDef['filter'][$column])) {
                $columnDef = $this->columnDef['filter'][$column];
                // get a raw keyword (without wildcards)
                $keyword = $this->getSearchKeyword($index, true);
                $builder = $this->getQueryBuilder();

                if ($columnDef['method'] instanceof Closure) {
                    $whereQuery = $builder->newQuery();
                    call_user_func_array($columnDef['method'], [$whereQuery, $keyword]);
                    $builder->addNestedWhereQuery($whereQuery);
                } else {
                    $this->compileColumnQuery(
                        $builder,
                        $columnDef['method'],
                        $columnDef['parameters'],
                        $column,
                        $keyword
                    );
                }
            } else {
                if (count(explode('.', $column)) > 1) {
                    $eagerLoads     = $this->getEagerLoads();
                    $parts          = explode('.', $column);
                    $relationColumn = array_pop($parts);
                    $relation       = implode('.', $parts);
                    if (in_array($relation, $eagerLoads)) {
                        $column = $this->joinEagerLoadedColumn($relation, $relationColumn);
                    }
                }

                $keyword = $this->getSearchKeyword($index);
                $this->compileColumnSearch($index, $column, $keyword);
            }

            $this->isFilterApplied = true;
        }
    }

    /**
     * Get proper keyword to use for search.
     *
     * @param int $i
     * @param bool $raw
     * @return string
     */
    protected function getSearchKeyword($i, $raw = false)
    {
        $keyword = $this->request->columnKeyword($i);
        if ($raw || $this->request->isRegex($i)) {
            return $keyword;
        }

        return $this->setupKeyword($keyword);
    }

    /**
     * Join eager loaded relation and get the related column name.
     *
     * @param string $relation
     * @param string $relationColumn
     * @return string
     */
    protected function joinEagerLoadedColumn($relation, $relationColumn)
    {
        $lastQuery = $this->query;
        foreach (explode('.', $relation) as $eachRelation) {
            $model = $lastQuery->getRelation($eachRelation);
            switch (true) {
                case $model instanceof BelongsToMany:
                    $pivot   = $model->getTable();
                    $pivotPK = $model->getExistenceCompareKey();
                    $pivotFK = $model->getQualifiedParentKeyName();
                    $this->performJoin($pivot, $pivotPK, $pivotFK);

                    $related = $model->getRelated();
                    $table   = $related->getTable();
                    $tablePK = $related->getForeignKey();
                    $foreign = $pivot . '.' . $tablePK;
                    $other   = $related->getQualifiedKeyName();

                    $lastQuery->addSelect($table . '.' . $relationColumn);
                    $this->performJoin($table, $foreign, $other);

                    break;

                case $model instanceof HasOneOrMany:
                    $table   = $model->getRelated()->getTable();
                    $foreign = $model->getQualifiedForeignKeyName();
                    $other   = $model->getQualifiedParentKeyName();
                    break;

                case $model instanceof BelongsTo:
                    $table   = $model->getRelated()->getTable();
                    $foreign = $model->getQualifiedForeignKey();
                    $other   = $model->getQualifiedOwnerKeyName();
                    break;

                default:
                    $table = $model->getRelated()->getTable();
                    if ($model instanceof HasOneOrMany) {
                        $foreign = $model->getForeignKey();
                        $other   = $model->getQualifiedParentKeyName();
                    } else {
                        $foreign = $model->getQualifiedForeignKey();
                        $other   = $model->getQualifiedOwnerKeyName();
                    }
            }
            $this->performJoin($table, $foreign, $other);
            $lastQuery = $model->getQuery();
        }

        return $table . '.' . $relationColumn;
    }

    /**
     * Perform join query.
     *
     * @param string $table
     * @param string $foreign
     * @param string $other
     */
    protected function performJoin($table, $foreign, $other)
    {
        $joins = [];
        foreach ((array) $this->getQueryBuilder()->joins as $key => $join) {
            $joins[] = $join->table;
        }

        if (! in_array($table, $joins)) {
            $this->getQueryBuilder()->leftJoin($table, $foreign, '=', $other);
        }
    }

    /**
     * Compile queries for column search.
     *
     * @param int $i
     * @param mixed $column
     * @param string $keyword
     */
    protected function compileColumnSearch($i, $column, $keyword)
    {
        if ($this->request->isRegex($i)) {
            $column = strstr($column, '(') ? $this->connection->raw($column) : $column;
            $this->regexColumnSearch($column, $keyword);
        } else {
            $this->compileQuerySearch($this->query, $column, $keyword, '');
        }
    }

    /**
     * Compile regex query column search.
     *
     * @param mixed $column
     * @param string $keyword
     */
    protected function regexColumnSearch($column, $keyword)
    {
        if ($this->isOracleSql()) {
            $sql = ! $this->isCaseInsensitive() ? 'REGEXP_LIKE( ' . $column . ' , ? )' : 'REGEXP_LIKE( LOWER(' . $column . ') , ?, \'i\' )';
            $this->query->whereRaw($sql, [$keyword]);
        } elseif ($this->database == 'pgsql') {
            $column = $this->castColumn($column);
            $sql = ! $this->isCaseInsensitive() ? $column . ' ~ ?' : $column . ' ~* ? ';
            $this->query->whereRaw($sql, [$keyword]);
        } else {
            $sql = ! $this->isCaseInsensitive() ? $column . ' REGEXP ?' : 'LOWER(' . $column . ') REGEXP ?';
            $this->query->whereRaw($sql, [Str::lower($keyword)]);
        }
    }

    /**
     * Perform sorting of columns.
     *
     * @return void
     */
    public function ordering()
    {
        if ($this->orderCallback) {
            call_user_func($this->orderCallback, $this->getQueryBuilder());

            return;
        }

        foreach ($this->request->orderableColumns() as $orderable) {
            $column = $this->getColumnName($orderable['column'], true);

            if ($this->isBlacklisted($column) && ! $this->hasCustomOrder($column)) {
                continue;
            }

            if ($this->hasCustomOrder($column)) {
                $method     = $this->columnDef['order'][$column]['method'];
                $parameters = $this->columnDef['order'][$column]['parameters'];
                $this->compileColumnQuery(
                    $this->getQueryBuilder(),
                    $method,
                    $parameters,
                    $column,
                    $orderable['direction']
                );
            } else {
                $valid = 1;
                if (count(explode('.', $column)) > 1) {
                    $eagerLoads     = $this->getEagerLoads();
                    $parts          = explode('.', $column);
                    $relationColumn = array_pop($parts);
                    $relation       = implode('.', $parts);

                    if (in_array($relation, $eagerLoads)) {
                        // Loop for nested relations
                        // This code is check morph many or not.
                        // If one of nested relation is MorphToMany
                        // we will call joinEagerLoadedColumn.
                        $lastQuery     = $this->query;
                        $isMorphToMany = false;
                        foreach (explode('.', $relation) as $eachRelation) {
                            $relationship = $lastQuery->getRelation($eachRelation);
                            if (! ($relationship instanceof MorphToMany)) {
                                $isMorphToMany = true;
                            }
                            $lastQuery = $relationship;
                        }
                        if ($isMorphToMany) {
                            $column = $this->joinEagerLoadedColumn($relation, $relationColumn);
                        } else {
                            $valid = 0;
                        }
                    }
                }

                if ($valid == 1) {
                    if ($this->nullsLast) {
                        $this->getQueryBuilder()->orderByRaw($this->getNullsLastSql($column, $orderable['direction']));
                    } else {
                        $this->getQueryBuilder()->orderBy($column, $orderable['direction']);
                    }
                }
            }
        }
    }

    /**
     * Check if column has custom sort handler.
     *
     * @param string $column
     * @return bool
     */
    protected function hasCustomOrder($column)
    {
        return isset($this->columnDef['order'][$column]);
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
        $sql = Config::get('datatables.nulls_last_sql', '%s %s NULLS LAST');

        return sprintf($sql, $column, $direction);
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
     * Perform pagination
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
     * Get results
     *
     * @return array|static[]
     */
    public function results()
    {
        return $this->results ?: $this->results = $this->query->get();
    }

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
        $this->pushToBlacklist($name);

        return parent::addColumn($name, $content, $order);
    }
}
