<?php

namespace Yajra\Datatables\Engines;

/**
 * Laravel Datatables Query Builder Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Yajra\Datatables\Helper;
use Yajra\Datatables\Request;

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
     * @inheritdoc
     */
    public function filter(Closure $callback)
    {
        $this->overrideGlobalSearch($callback, $this->query);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function make($mDataSupport = false, $orderFirst = false)
    {
        return parent::make($mDataSupport, $orderFirst);
    }

    /**
     * @inheritdoc
     */
    public function totalCount()
    {
        return $this->count();
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
            $row_count = $this->connection->getQueryGrammar()->wrap('row_count');
            $myQuery->select($this->connection->raw("'1' as {$row_count}"));
        }

        return $this->connection->table($this->connection->raw('(' . $myQuery->toSql() . ') count_row_table'))
                                ->setBindings($myQuery->getBindings())->count();
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
                $keyword = $this->setupKeyword($this->request->keyword());
                foreach ($this->request->searchableColumnIndex() as $index) {
                    $columnName = $this->getColumnName($index);

                    if (isset($this->columnDef['filter'][$columnName])) {
                        $method     = Helper::getOrMethod($this->columnDef['filter'][$columnName]['method']);
                        $parameters = $this->columnDef['filter'][$columnName]['parameters'];
                        $this->compileColumnQuery(
                            $this->getQueryBuilder($query),
                            $method,
                            $parameters,
                            $columnName,
                            $keyword
                        );
                    } else {
                        if (count(explode('.', $columnName)) > 1) {
                            $eagerLoads     = $this->getEagerLoads();
                            $parts          = explode('.', $columnName);
                            $relationColumn = array_pop($parts);
                            $relation       = implode('.', $parts);
                            if (in_array($relation, $eagerLoads)) {
                                $this->compileRelationSearch(
                                    $this->getQueryBuilder($query),
                                    $relation,
                                    $relationColumn,
                                    $keyword
                                );
                            } else {
                                $this->compileGlobalSearch($this->getQueryBuilder($query), $columnName, $keyword);
                            }
                        } else {
                            $this->compileGlobalSearch($this->getQueryBuilder($query), $columnName, $keyword);
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
     * @param string $method
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
        $myQuery->orWhereHas($relation, function ($q) use ($column, $keyword, $query) {
            $sql = $q->select($this->connection->raw('count(1)'))
                     ->where($column, 'like', $keyword)
                     ->toSql();
            $sql = "($sql) >= 1";
            $query->orWhereRaw($sql, [$keyword]);
        });
    }

    /**
     * Add a query on global search.
     *
     * @param mixed $query
     * @param string $column
     * @param string $keyword
     */
    protected function compileGlobalSearch($query, $column, $keyword)
    {
        $column = $this->castColumn($column);
        $sql    = $column . ' LIKE ?';
        if ($this->isCaseInsensitive()) {
            $sql     = 'LOWER(' . $column . ') LIKE ?';
            $keyword = Str::lower($keyword);
        }

        $query->orWhereRaw($sql, [$keyword]);
    }

    /**
     * Wrap a column and cast in pgsql.
     *
     * @param  string $column
     * @return string
     */
    public function castColumn($column)
    {
        $column = $this->connection->getQueryGrammar()->wrap($column);
        if ($this->database === 'pgsql') {
            $column = 'CAST(' . $column . ' as TEXT)';
        }

        return $column;
    }

    /**
     * Perform column search.
     *
     * @return void
     */
    public function columnSearch()
    {
        $columns = $this->request->get('columns');
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($this->request->isColumnSearchable($i)) {
                $column  = $this->getColumnName($i);
                $keyword = $this->getSearchKeyword($i);

                if (isset($this->columnDef['filter'][$column])) {
                    $method     = $this->columnDef['filter'][$column]['method'];
                    $parameters = $this->columnDef['filter'][$column]['parameters'];
                    $this->compileColumnQuery($this->getQueryBuilder(), $method, $parameters, $column, $keyword);
                } else {
                    $column = $this->castColumn($column);
                    if ($this->isCaseInsensitive()) {
                        $this->compileColumnSearch($i, $column, $keyword, false);
                    } else {
                        $col = strstr($column, '(') ? $this->connection->raw($column) : $column;
                        $this->compileColumnSearch($i, $col, $keyword, true);
                    }
                }

                $this->isFilterApplied = true;
            }
        }
    }

    /**
     * Get proper keyword to use for search.
     *
     * @param int $i
     * @return string
     */
    private function getSearchKeyword($i)
    {
        if ($this->request->isRegex($i)) {
            return $this->request->columnKeyword($i);
        }

        return $this->setupKeyword($this->request->columnKeyword($i));
    }

    /**
     * Compile queries for column search.
     *
     * @param int $i
     * @param mixed $column
     * @param string $keyword
     * @param bool $caseSensitive
     */
    protected function compileColumnSearch($i, $column, $keyword, $caseSensitive = true)
    {
        if ($this->request->isRegex($i)) {
            $this->regexColumnSearch($column, $keyword, $caseSensitive);
        } else {
            $sql     = $caseSensitive ? $column . ' LIKE ?' : 'LOWER(' . $column . ') LIKE ?';
            $keyword = $caseSensitive ? $keyword : Str::lower($keyword);
            $this->query->whereRaw($sql, [$keyword]);
        }
    }

    /**
     * Compile regex query column search.
     *
     * @param mixed $column
     * @param string $keyword
     * @param bool $caseSensitive
     */
    protected function regexColumnSearch($column, $keyword, $caseSensitive = true)
    {
        if ($this->isOracleSql()) {
            $sql = $caseSensitive ? 'REGEXP_LIKE( ' . $column . ' , ? )' : 'REGEXP_LIKE( LOWER(' . $column . ') , ?, \'i\' )';
            $this->query->whereRaw($sql, [$keyword]);
        } else {
            $sql = $caseSensitive ? $column . ' REGEXP ?' : 'LOWER(' . $column . ') REGEXP ?';
            $this->query->whereRaw($sql, [Str::lower($keyword)]);
        }
    }

    /**
     * Check if the current sql language is based on oracle syntax.
     *
     * @return bool
     */
    protected function isOracleSql()
    {
        return $this->database === 'oracle';
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
                if (count(explode('.', $column)) > 1) {
                    $eagerLoads     = $this->getEagerLoads();
                    $parts          = explode('.', $column);
                    $relationColumn = array_pop($parts);
                    $relation       = implode('.', $parts);

                    if (in_array($relation, $eagerLoads)) {
                        $column = $this->joinEagerLoadedColumn($relation, $relationColumn);
                    }
                }

                $this->getQueryBuilder()->orderBy($column, $orderable['direction']);
            }
        }
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
        $table   = $this->query->getRelation($relation)->getRelated()->getTable();
        $foreign = $this->query->getRelation($relation)->getQualifiedForeignKey();
        $other   = $this->query->getRelation($relation)->getQualifiedOtherKeyName();
        $column  = $table . '.' . $relationColumn;

        $joins = [];
        foreach ((array) $this->getQueryBuilder()->joins as $key => $join) {
            $joins[] = $join->table;
        }

        if (! in_array($table, $joins)) {
            $this->getQueryBuilder()
                 ->leftJoin($table, $foreign, '=', $other);
        }

        return $column;
    }

    /**
     * Perform pagination
     *
     * @return void
     */
    public function paging()
    {
        $this->query->skip($this->request['start'])
                    ->take((int) $this->request['length'] > 0 ? $this->request['length'] : 10);
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
