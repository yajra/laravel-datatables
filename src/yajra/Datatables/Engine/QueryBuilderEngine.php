<?php

namespace yajra\Datatables\Engine;

/**
 * Laravel Datatables Query Builder Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use yajra\Datatables\Request;

class QueryBuilderEngine extends BaseEngine
{

    /**
     * @param Builder $builder
     * @param \yajra\Datatables\Request $request
     */
    public function __construct(Builder $builder, Request $request)
    {
        $this->query_type = 'builder';
        $this->query      = $builder;
        $this->columns    = $this->query->columns;
        $this->connection = $this->query->getConnection();

        if ($this->isDebugging()) {
            $this->connection->enableQueryLog();
        }

        $this->request = $request;
        $this->getTotalRecords();
    }

    /**
     * Set auto filter off and run your own filter.
     *
     * @param Closure $callback
     * @return $this
     */
    public function filter(Closure $callback)
    {
        $this->autoFilter = false;

        $query = $this->query;
        call_user_func($callback, $query);

        return $this;
    }

    /**
     * Counts current query.
     *
     * @return int
     */
    public function count()
    {
        $query = $this->query;

        // if its a normal query ( no union and having word ) replace the select with static text to improve performance
        $myQuery = clone $query;
        if ( ! Str::contains(Str::lower($myQuery->toSql()), 'union')
            && ! Str::contains(Str::lower($myQuery->toSql()), 'having')
        ) {
            $myQuery->select($this->connection->raw("'1' as row_count"));
        }

        return $this->connection->table($this->connection->raw('(' . $myQuery->toSql() . ') count_row_table'))
            ->setBindings($myQuery->getBindings())->count();
    }

    /**
     * Datatable ordering.
     */
    public function doOrdering()
    {
        foreach ($this->request->orderableColumns() as $orderable) {
            $column = $this->getOrderColumnName($orderable['column']);
            $this->query->orderBy($column, $orderable['direction']);
        }
    }

    /**
     * Datatables filtering.
     */
    public function doFiltering()
    {
        $this->query->where(
            function ($query) {
                $keyword = $this->setupKeyword($this->request->keyword());
                foreach ($this->request->searchableColumnIndex() as $index) {
                    $column = $this->setupColumnName($index);

                    if (isset($this->filter_columns[$column])) {
                        $method     = $this->getOrMethod($this->filter_columns[$column]['method']);
                        $parameters = $this->filter_columns[$column]['parameters'];
                        $this->compileFilterColumn($method, $parameters, $column);
                    } else {
                        $this->compileGlobalSearch($query, $column, $keyword);
                    }
                }
            }
        );
    }

    /**
     * Perform column search.
     */
    public function doColumnSearch()
    {
        $columns = $this->request->get('columns');
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($this->request->isColumnSearchable($i)) {
                $column  = $this->getColumnIdentity($i);
                $keyword = $this->setupKeyword($this->request->columnKeyword($i));

                if (isset($this->filter_columns[$column])) {
                    $method     = $this->filter_columns[$column]['method'];
                    $parameters = $this->filter_columns[$column]['parameters'];
                    $this->compileFilterColumn($method, $parameters, $column);
                } else {
                    $column = $this->castColumn($column);
                    if ($this->isCaseInsensitive()) {
                        $this->query->whereRaw('LOWER(' . $column . ') LIKE ?', [Str::lower($keyword)]);
                    } else {
                        $col = strstr($column, '(') ? $this->connection->raw($column) : $column;
                        $this->query->whereRaw($col . ' LIKE ?', [$keyword]);
                    }
                }
            }
        }
    }

    /**
     * Paginate query.
     *
     * @return mixed
     */
    protected function paginate()
    {
        return $this->query->skip($this->request['start'])
            ->take((int) $this->request['length'] > 0 ? $this->request['length'] : 10);
    }

    /**
     * Datatables paging.
     */
    public function doPaging()
    {
        if ($this->isPaginationable()) {
            $this->paginate();
        }
    }

}
