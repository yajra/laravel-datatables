<?php

namespace Yajra\Datatables\Engines;

use Closure;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;
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
     * Organizes works
     *
     * @param bool $mDataSupport
     * @param bool $orderFirst
     * @return \Illuminate\Http\JsonResponse
     */
    public function make($mDataSupport = false, $orderFirst = false)
    {
        return parent::make($mDataSupport, $orderFirst);
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

        // check for select soft deleted records
        if (! $this->withTrashed && $this->modelUseSoftDeletes()) {
            $myQuery->whereNull($myQuery->getModel()->getQualifiedDeletedAtColumn());
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
     * Check if model use SoftDeletes trait
     *
     * @return boolean
     */
    private function modelUseSoftDeletes()
    {
        if ($this->query_type == 'eloquent') {
            return in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->query->getModel()));
        }

        return false;
    }

    /**
     * Perform global search.
     *
     * @return void
     */
    public function filtering()
    {
        $this->query->where(
            function ($query) {
                $globalKeyword = $this->request->keyword();
                $queryBuilder  = $this->getQueryBuilder($query);

                foreach ($this->request->searchableColumnIndex() as $index) {
                    $columnName = $this->getColumnName($index);
                    if ($this->isBlacklisted($columnName)) {
                        continue;
                    }

                    // check if custom column filtering is applied
                    if (isset($this->columnDef['filter'][$columnName])) {
                        $columnDef = $this->columnDef['filter'][$columnName];
                        // check if global search should be applied for the specific column
                        $applyGlobalSearch = count($columnDef['parameters']) == 0 || end($columnDef['parameters']) !== false;
                        if (! $applyGlobalSearch) {
                            continue;
                        }

                        if ($columnDef['method'] instanceof Closure) {
                            $whereQuery = $queryBuilder->newQuery();
                            call_user_func_array($columnDef['method'], [$whereQuery, $globalKeyword]);
                            $queryBuilder->addNestedWhereQuery($whereQuery, 'or');
                        } else {
                            $this->compileColumnQuery(
                                $queryBuilder,
                                Helper::getOrMethod($columnDef['method']),
                                $columnDef['parameters'],
                                $columnName,
                                $globalKeyword
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
                                    $globalKeyword
                                );
                            } else {
                                $this->compileQuerySearch($queryBuilder, $columnName, $globalKeyword);
                            }
                        } else {
                            $this->compileQuerySearch($queryBuilder, $columnName, $globalKeyword);
                        }
                    }

                    $this->isFilterApplied = true;
                }
            }
        );
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
            $builder      = $chunk['builder'];
            $relationType = $chunk['relationType'];
            $query        = $chunk['query'];
            $builder      = "({$builder->toSql()}) >= 1";

            // Check if it last relation we will use orWhereRaw
            if ($lastRelation == $relation) {
                $relationMethod = "orWhereRaw";
            } else {
                // For case parent relation of nested relation.
                // We must use and for properly query and get correct result
                $relationMethod = "whereRaw";
            }

            if ($relationType instanceof MorphToMany) {
                $query->{$relationMethod}($builder, [$relationType->getMorphClass(), $this->prepareKeyword($keyword)]);
            } else {
                $query->{$relationMethod}($builder, [$this->prepareKeyword($keyword)]);
            }
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

            // Get table from query and add it.
            $column = $q->from . '.' . $column;
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
        $columns = (array) $this->request->input('columns');

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
    private function getSearchKeyword($i, $raw = false)
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
        $joins = [];
        foreach ((array) $this->getQueryBuilder()->joins as $key => $join) {
            $joins[] = $join->table;
        }

        $model = $this->query->getRelation($relation);
        if ($model instanceof BelongsToMany) {
            $pivot   = $model->getTable();
            $pivotPK = $model->getForeignKey();
            $pivotFK = $model->getQualifiedParentKeyName();

            if (! in_array($pivot, $joins)) {
                $this->getQueryBuilder()->leftJoin($pivot, $pivotPK, '=', $pivotFK);
            }

            $related = $model->getRelated();
            $table   = $related->getTable();
            $tablePK = $related->getForeignKey();
            $tableFK = $related->getQualifiedKeyName();

            if (! in_array($table, $joins)) {
                $this->getQueryBuilder()->leftJoin($table, $pivot . '.' . $tablePK, '=', $tableFK);
            }
        } else {
            $table = $model->getRelated()->getTable();
            if ($model instanceof HasOneOrMany) {
                $foreign = $model->getForeignKey();
                $other   = $model->getQualifiedParentKeyName();
            } else {
                $foreign = $model->getQualifiedForeignKey();
                $other   = $model->getQualifiedOtherKeyName();
            }

            if (! in_array($table, $joins)) {
                $this->getQueryBuilder()->leftJoin($table, $foreign, '=', $other);
            }
        }

        $column = $table . '.' . $relationColumn;

        return $column;
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

            if ($this->isBlacklisted($column)) {
                continue;
            }

            if (isset($this->columnDef['order'][$column])) {
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
                        $relationship = $this->query->getRelation($relation);
                        if (! ($relationship instanceof MorphToMany)) {
                            $column = $this->joinEagerLoadedColumn($relation, $relationColumn);
                        } else {
                            $valid = 0;
                        }
                    }
                }

                if ($valid == 1) {
                    $this->getQueryBuilder()->orderBy($column, $orderable['direction']);
                }
            }
        }
    }

    /**
     * Perform pagination
     *
     * @return void
     */
    public function paging()
    {
        $this->query->skip($this->request->input('start'))
                    ->take((int) $this->request->input('length') > 0 ? $this->request->input('length') : 10);
    }

    /**
     * Get results
     *
     * @return array|static[]
     */
    public function results()
    {
        return $this->query->get();
    }
}
