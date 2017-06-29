<?php

namespace Yajra\Datatables\Engines;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
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
     * Builder object.
     *
     * @var \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    protected $query;

    /**
     * Query builder object.
     *
     * @var \Illuminate\Database\Query\Builder
     */
    protected $builder;

    /**
     * Database connection used.
     *
     * @var \Illuminate\Database\Connection
     */
    protected $connection;

    /**
     * @param \Illuminate\Database\Query\Builder $builder
     * @param \Yajra\Datatables\Request          $request
     */
    public function __construct(Builder $builder, Request $request)
    {
        $this->query      = $builder;
        $this->request    = $request;
        $this->columns    = $builder->columns;
        $this->connection = $builder->getConnection();
        if ($this->isDebugging()) {
            $this->connection->enableQueryLog();
        }
    }

    /**
     * Set auto filter off and run your own filter.
     * Overrides global search.
     *
     * @param callable $callback
     * @param bool     $globalSearch
     * @return $this
     */
    public function filter(callable $callback, $globalSearch = false)
    {
        $this->overrideGlobalSearch($callback, $this->query, $globalSearch);

        return $this;
    }

    /**
     * Perform global search.
     *
     * @return void
     */
    public function filtering()
    {
        $keyword = $this->request->keyword();

        if ($this->isSmartSearch()) {
            $this->smartGlobalSearch($keyword);

            return;
        }

        $this->globalSearch($keyword);
    }

    /**
     * Perform multi-term search by splitting keyword into
     * individual words and searches for each of them.
     *
     * @param string $keyword
     */
    private function smartGlobalSearch($keyword)
    {
        $keywords = array_filter(explode(' ', $keyword));

        foreach ($keywords as $keyword) {
            $this->globalSearch($keyword);
        }
    }

    /**
     * Perform global search for the given keyword.
     *
     * @param string $keyword
     */
    protected function globalSearch($keyword)
    {
        $this->query->where(function ($query) use ($keyword) {
            $query = $this->getBaseQueryBuilder($query);

            foreach ($this->request->searchableColumnIndex() as $index) {
                $columnName = $this->getColumnName($index);
                if ($this->isBlacklisted($columnName) && !$this->hasCustomFilter($columnName)) {
                    continue;
                }

                if ($this->hasCustomFilter($columnName)) {
                    $this->applyFilterColumn($query, $columnName, $keyword, 'or');
                } else {
                    $this->compileQuerySearch($query, $columnName, $keyword);
                }

                $this->isFilterApplied = true;
            }
        });
    }

    /**
     * Get the base query builder instance.
     *
     * @param mixed $instance
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    protected function getBaseQueryBuilder($instance = null)
    {
        if (!$instance) {
            $instance = $this->query;
        }

        if ($instance instanceof EloquentBuilder) {
            return $instance->getQuery();
        }

        return $instance;
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
     * Apply filterColumn api search.
     *
     * @param mixed  $query
     * @param string $columnName
     * @param string $keyword
     * @param string $boolean
     */
    protected function applyFilterColumn($query, $columnName, $keyword, $boolean = 'and')
    {
        $callback = $this->columnDef['filter'][$columnName]['method'];
        $builder  = $query->newQuery();
        $callback($builder, $keyword);
        $query->addNestedWhereQuery($builder, $boolean);
    }

    /**
     * Compile query builder where clause depending on configurations.
     *
     * @param mixed  $query
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
     * @param mixed  $query
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

            if (!$q->from instanceof Expression) {
                // Get table from query and add it.
                $column = $q->from . '.' . $column;
            }
        }

        return $this->wrap($column);
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
     * Wrap a column and cast based on database driver.
     *
     * @param  string $column
     * @return string
     */
    protected function castColumn($column)
    {
        $driver = $this->connection->getDriverName();

        if ($driver === 'pgsql') {
            $column = 'CAST(' . $column . ' as TEXT)';
        } elseif ($driver === 'firebird') {
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
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function make($mDataSupport = false)
    {
        try {
            $this->totalRecords = $this->totalCount();

            if ($this->totalRecords) {
                $this->filterRecords();
                $this->ordering();
                $this->paginate();
            }

            $data = $this->transform($this->getProcessedData($mDataSupport));

            return $this->render($data);
        } catch (\Exception $exception) {
            return $this->errorResponse($exception);
        }
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
        $builder = $this->prepareCountQuery();
        $table   = $this->connection->raw('(' . $builder->toSql() . ') count_row_table');

        return $this->connection->table($table)
                                ->setBindings($builder->getBindings())
                                ->count();
    }

    /**
     * Prepare count query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    protected function prepareCountQuery()
    {
        $builder = clone $this->query;

        if ($this->isComplexQuery($builder)) {
            $row_count = $this->wrap('row_count');
            $builder->select($this->connection->raw("'1' as {$row_count}"));
        }

        return $builder;
    }

    /**
     * Check if builder query uses complex sql.
     *
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @return bool
     */
    protected function isComplexQuery($builder)
    {
        return !Str::contains(Str::lower($builder->toSql()), ['union', 'having', 'distinct', 'order by', 'group by']);
    }

    /**
     * Perform sorting of columns.
     *
     * @return void
     */
    public function ordering()
    {
        if ($this->orderCallback) {
            call_user_func($this->orderCallback, $this->getBaseQueryBuilder());

            return;
        }

        foreach ($this->request->orderableColumns() as $orderable) {
            $column = $this->getColumnName($orderable['column'], true);

            if ($this->isBlacklisted($column) && !$this->hasCustomOrder($column)) {
                continue;
            }

            if ($this->hasCustomOrder($column)) {
                $sql      = $this->columnDef['order'][$column]['sql'];
                $sql      = str_replace('$1', $orderable['direction'], $sql);
                $bindings = $this->columnDef['order'][$column]['bindings'];
                $this->query->orderByRaw($sql, $bindings);
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
                            if (!($relationship instanceof MorphToMany)) {
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
                        $this->getBaseQueryBuilder()->orderByRaw($this->getNullsLastSql($column,
                            $orderable['direction']));
                    } else {
                        $this->getBaseQueryBuilder()->orderBy($column, $orderable['direction']);
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
     * Get eager loads keys if eloquent.
     *
     * @return array
     */
    protected function getEagerLoads()
    {
        if ($this->query instanceof EloquentBuilder) {
            return array_keys($this->query->getEagerLoads());
        }

        return [];
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
        $table = '';
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
                    $table   = $model->getRelated()->getTable();
                    $foreign = $model->getQualifiedForeignKey();
                    $other   = $model->getQualifiedOtherKeyName();
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
        foreach ((array) $this->getBaseQueryBuilder()->joins as $key => $join) {
            $joins[] = $join->table;
        }

        if (!in_array($table, $joins)) {
            $this->getBaseQueryBuilder()->leftJoin($table, $foreign, '=', $other);
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
        $sql = config('datatables.nulls_last_sql', '%s %s NULLS LAST');

        return sprintf($sql, $column, $direction);
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
            if (!$this->request->isColumnSearchable($index)) {
                continue;
            }

            $column = $this->getColumnName($index);

            if ($this->hasCustomFilter($column)) {
                $keyword  = $this->getColumnSearchKeyword($index, $raw = true);
                $this->applyFilterColumn($this->query, $column, $keyword);
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

                $keyword = $this->getColumnSearchKeyword($index);
                $this->compileColumnSearch($index, $column, $keyword);
            }

            $this->isFilterApplied = true;
        }
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
     * Compile queries for column search.
     *
     * @param int    $i
     * @param string $column
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
     * @param mixed  $column
     * @param string $keyword
     */
    protected function regexColumnSearch($column, $keyword)
    {
        switch ($this->connection->getDriverName()) {
            case 'oracle':
                $sql = !$this->isCaseInsensitive() ? 'REGEXP_LIKE( ' . $column . ' , ? )' : 'REGEXP_LIKE( LOWER(' . $column . ') , ?, \'i\' )';
                break;

            case 'pgsql':
                $sql = !$this->isCaseInsensitive() ? $column . ' ~ ?' : $column . ' ~* ? ';
                break;

            default:
                $sql     = !$this->isCaseInsensitive() ? $column . ' REGEXP ?' : 'LOWER(' . $column . ') REGEXP ?';
                $keyword = Str::lower($keyword);
        }

        $this->query->whereRaw($sql, [$keyword]);
    }

    /**
     * Perform pagination.
     *
     * @return void
     */
    public function paging()
    {
        $this->query->skip($this->request->input('start'))
                    ->take((int) $this->request->input('length') > 0 ? $this->request->input('length') : 10);
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
     * Add column in collection.
     *
     * @param string          $name
     * @param string|callable $content
     * @param bool|int        $order
     * @return \Yajra\Datatables\Engines\BaseEngine|\Yajra\Datatables\Engines\QueryBuilderEngine
     */
    public function addColumn($name, $content, $order = false)
    {
        $this->pushToBlacklist($name);

        return parent::addColumn($name, $content, $order);
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
     * Append debug parameters on output.
     *
     * @param  array $output
     * @return array
     */
    protected function showDebugger(array $output)
    {
        $output['queries'] = $this->connection->getQueryLog();
        $output['input']   = $this->request->all();

        return $output;
    }
}
