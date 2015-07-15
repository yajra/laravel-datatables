<?php

namespace yajra\Datatables\Engines;

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
use yajra\Datatables\Contracts\DataTableEngine;
use yajra\Datatables\Helper;
use yajra\Datatables\Request;

class QueryBuilderEngine extends BaseEngine implements DataTableEngine
{
    /**
     * @param \Illuminate\Database\Query\Builder $builder
     * @param \yajra\Datatables\Request $request
     */
    public function __construct(Builder $builder, Request $request)
    {
        $this->request    = $request;
        $this->query_type = 'builder';
        $this->query      = $builder;
        $this->columns    = $this->query->columns;
        $this->connection = $this->query->getConnection();
        $this->prefix     = $this->getQueryBuilder()->getGrammar()->getTablePrefix();
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
     * Counts current query.
     *
     * @return int
     */
    public function count()
    {
        $myQuery = clone $this->query;
        // if its a normal query ( no union and having word ) replace the select with static text to improve performance
        if ( ! Str::contains(Str::lower($myQuery->toSql()), 'union')
            && ! Str::contains(Str::lower($myQuery->toSql()), 'having')
        ) {
            $myQuery->select($this->connection->raw("'1' as row_count"));
        }

        return $this->connection->table($this->connection->raw('(' . $myQuery->toSql() . ') count_row_table'))
            ->setBindings($myQuery->getBindings())->count();
    }

    /**
     * @inheritdoc
     */
    public function filtering()
    {
        $this->query->where(
            function ($query) {
                $keyword = $this->setupKeyword($this->request->keyword());
                foreach ($this->request->searchableColumnIndex() as $index) {
                    $column = $this->setupColumnName($index);

                    if (isset($this->columnDef['filter'][$column])) {
                        $method     = Helper::getOrMethod($this->columnDef['filter'][$column]['method']);
                        $parameters = $this->columnDef['filter'][$column]['parameters'];
                        $this->compileColumnQuery(
                            $this->getQueryBuilder($query), $method, $parameters, $column, $keyword
                        );
                    } else {
                        $this->compileGlobalSearch($this->getQueryBuilder($query), $column, $keyword);
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
     * Wrap a column and cast in pgsql
     *
     * @param  string $column
     * @return string
     */
    public function castColumn($column)
    {
        $column = Helper::wrapDatabaseValue($this->database, $column);
        if ($this->database === 'pgsql') {
            $column = 'CAST(' . $column . ' as TEXT)';
        }

        return $column;
    }

    /**
     * @inheritdoc
     */
    public function columnSearch()
    {
        $columns = $this->request->get('columns');
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($this->request->isColumnSearchable($i)) {
                $column  = $this->setupColumnName($i);
                $keyword = $this->setupKeyword($this->request->columnKeyword($i));

                if (isset($this->columnDef['filter'][$column])) {
                    $method     = $this->columnDef['filter'][$column]['method'];
                    $parameters = $this->columnDef['filter'][$column]['parameters'];
                    $this->compileColumnQuery($this->getQueryBuilder(), $method, $parameters, $column, $keyword);
                } else {
                    $column = $this->castColumn($column);
                    if ($this->isCaseInsensitive()) {
                        $this->query->whereRaw('LOWER(' . $column . ') LIKE ?', [Str::lower($keyword)]);
                    } else {
                        $col = strstr($column, '(') ? $this->connection->raw($column) : $column;
                        $this->query->whereRaw($col . ' LIKE ?', [$keyword]);
                    }
                }

                $this->isFilterApplied = true;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function ordering()
    {
        foreach ($this->request->orderableColumns() as $orderable) {
            $column = $this->setupColumnName($orderable['column']);
            if (isset($this->columnDef['order'][$column])) {
                $method     = $this->columnDef['order'][$column]['method'];
                $parameters = $this->columnDef['order'][$column]['parameters'];
                $this->compileColumnQuery(
                    $this->getQueryBuilder(), $method, $parameters, $column, $orderable['direction']
                );
            } else {
                $this->getQueryBuilder()->orderBy($column, $orderable['direction']);
            }
        }
    }

    /**
     * @inheritdoc
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
