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
use yajra\Datatables\Request;

class QueryBuilderEngine extends BaseEngine implements DataTableEngine
{

    /**
     * @param Builder $builder
     * @param \yajra\Datatables\Request $request
     */
    public function __construct(Builder $builder, Request $request)
    {
        $this->request    = $request;
        $this->query_type = 'builder';
        $this->query      = $builder;
        $this->columns    = $this->query->columns;
        $this->connection = $this->query->getConnection();

        if ($this->isDebugging()) {
            $this->connection->enableQueryLog();
        }
    }

    /**
     * @inheritdoc
     */
    public function filter(Closure $callback)
    {
        $this->autoFilter = false;

        call_user_func($callback, $this->query);

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
     * @inheritdoc
     */
    public function columnSearch()
    {
        $columns = $this->request->get('columns');
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($this->request->isColumnSearchable($i)) {
                $column  = $this->getColumnName($i);
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
     * @inheritdoc
     */
    public function ordering()
    {
        foreach ($this->request->orderableColumns() as $orderable) {
            $column = $this->getColumnName($orderable['column']);
            $this->query->orderBy($column, $orderable['direction']);
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
     * @inheritdoc
     */
    public function setResults()
    {
        $this->result_object = $this->query->get();

        return $this->resultsToArray($this->result_object);
    }

}
