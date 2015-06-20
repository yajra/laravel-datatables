<?php

namespace yajra\Datatables\Engine;

/*
 * Laravel Datatables Base Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
 */

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\TransformerAbstract;
use yajra\Datatables\Request;

class BaseEngine
{

    /**
     * Database connection used.
     *
     * @var \Illuminate\Database\Connection
     */
    public $connection;

    /**
     * Builder object.
     *
     * @var mixed
     */
    public $query;

    /**
     * Query builder object.
     *
     * @var \Illuminate\Database\Query\Builder
     */
    public $builder;

    /**
     * Input variables.
     *
     * @var \yajra\Datatables\Request
     */
    public $request;

    /**
     * Array of result columns/fields.
     *
     * @var array
     */
    public $columns = [];

    /**
     * Array of last columns.
     *
     * @var array
     */
    public $last_columns = [];

    /**
     * Query type.
     *
     * @var string
     */
    public $query_type;

    /**
     * Array of columns to be added on result.
     *
     * @var array
     */
    public $extra_columns = [];

    /**
     * Array of columns to be removed on output.
     *
     * @var array
     */
    public $excess_columns = ['rn', 'row_num'];

    /**
     * Array of columns to be edited.
     *
     * @var array
     */
    public $edit_columns = [];

    /**
     * sColumns to output.
     *
     * @var array
     */
    public $sColumns = [];

    /**
     * Total records.
     *
     * @var int
     */
    public $totalRecords = 0;

    /**
     * Total filtered records.
     *
     * @var int
     */
    public $filteredRecords = 0;

    /**
     * Eloquent/Builder result object.
     *
     * @var mixed
     */
    public $result_object;

    /**
     * Result array.
     *
     * @var array
     */
    public $result_array = [];

    /**
     * Regulated result array.
     *
     * @var array
     */
    public $result_array_r = [];

    /**
     * Flag for DT support for mData.
     *
     * @var bool
     */
    public $m_data_support = false;

    /**
     * Auto-filter flag.
     *
     * @var bool
     */
    public $autoFilter = true;

    /**
     * DT_RowID template.
     *
     * @var string|callable
     */
    public $row_id_tmpl;

    /**
     * DT_RowClass template.
     *
     * @var string|callable
     */
    public $row_class_tmpl;

    /**
     * DT_RowData template.
     *
     * @var array
     */
    public $row_data_tmpls = [];

    /**
     * DT_RowAttr template.
     *
     * @var array
     */
    public $row_attr_tmpls = [];

    /**
     * Override column search query type.
     *
     * @var array
     */
    public $filter_columns = [];

    /**
     * Output transformer.
     *
     * @var TransformerAbstract
     */
    public $transformer = null;

    /**
     * Construct base engine.
     *
     * @param \yajra\Datatables\Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->getTotalRecords(); // Total records
    }

    /**
     * Get total records.
     *
     * @return int
     */
    public function getTotalRecords()
    {
        return $this->totalRecords = $this->count();
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
     * Organizes works.
     *
     * @param bool $mDataSupport
     * @param bool $orderFirst For CollectionEngine, ordering should be done first
     * @return JsonResponse
     */
    public function make($mDataSupport = false, $orderFirst = false)
    {
        // set mData support flag
        $this->m_data_support = $mDataSupport;

        $this->compileQueryBuilder($orderFirst);

        return $this->compileOutput();
    }

    /**
     * Compile Datatables queries.
     *
     * @param bool $orderFirst
     */
    protected function compileQueryBuilder($orderFirst)
    {
        if ($orderFirst) {
            $this->doOrdering();
        }

        $this->compileFiltering();

        if ( ! $orderFirst) {
            $this->doOrdering();
        }

        $this->doPaging();
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
     * Get column name by order column index.
     *
     * @param int $column
     * @return mixed
     */
    protected function getOrderColumnName($column)
    {
        return $this->request->orderColumnName($column) ?: $this->columns[$column];
    }

    /**
     * Perform all filtering queries.
     */
    protected function compileFiltering()
    {
        if ($this->autoFilter && $this->request->isSearchable()) {
            $this->doFiltering();
        }

        $this->doColumnSearch();
        $this->getTotalFilteredRecords();
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
     * Setup search keyword.
     *
     * @param  $value
     * @return string
     */
    public function setupKeyword($value)
    {
        $keyword = '%' . $value . '%';
        if ($this->isWildcard()) {
            $keyword = $this->wildcardLikeString($value);
        }
        // remove escaping slash added on js script request
        $keyword = str_replace('\\', '%', $keyword);

        return $keyword;
    }

    /**
     * Get config use wild card status.
     *
     * @return bool
     */
    public function isWildcard()
    {
        return Config::get('datatables.search.use_wildcards', false);
    }

    /**
     * Adds % wildcards to the given string.
     *
     * @param string $str
     * @param bool $lowercase
     * @return string
     */
    public function wildcardLikeString($str, $lowercase = true)
    {
        $wild   = '%';
        $length = strlen($str);
        if ($length) {
            for ($i = 0; $i < $length; $i++) {
                $wild .= $str[$i] . '%';
            }
        }
        if ($lowercase) {
            $wild = Str::lower($wild);
        }

        return $wild;
    }

    /**
     * Setup column name to be use for filtering.
     *
     * @param integer $i
     * @return string
     */
    private function setupColumnName($i)
    {
        $column = $this->getColumnIdentity($i);

        if (Str::contains(Str::upper($column), ' AS ')) {
            $column = $this->getColumnName($column);
        }

        // there's no need to put the prefix unless the column name is prefixed with the table name.
        $column = $this->prefixColumn($column);

        return $column;
    }

    /**
     * Get column identity from input or database.
     *
     * @param integer $i
     * @return string
     */
    public function getColumnIdentity($i)
    {
        return $this->request->columnName($i) ?: $this->columns[$i];
    }

    /**
     * Get column name from string.
     *
     * @param string $str
     * @return string
     */
    public function getColumnName($str)
    {
        preg_match('#^(\S*?)\s+as\s+(\S*?)$#si', $str, $matches);

        if ( ! empty($matches)) {
            return $matches[2];
        } elseif (strpos($str, '.')) {
            $array = explode('.', $str);

            return array_pop($array);
        }

        return $str;
    }

    /**
     * Will prefix column if needed.
     *
     * @param string $column
     * @return string
     */
    public function prefixColumn($column)
    {
        $table_names = $this->tableNames();
        if (count(
            array_filter($table_names, function ($value) use (&$column) {
                return strpos($column, $value . '.') === 0;
            })
        )) {
            // the column starts with one of the table names
            $column = $this->databasePrefix() . $column;
        }

        return $column;
    }

    /**
     * Will look through the query and all it's joins to determine the table names.
     *
     * @return array
     */
    public function tableNames()
    {
        $names          = [];
        $query          = $this->getBuilder();
        $names[]        = $query->from;
        $joins          = $query->joins ?: [];
        $databasePrefix = $this->databasePrefix();
        foreach ($joins as $join) {
            $table   = preg_split('/ as /i', $join->table);
            $names[] = $table[0];
            if (isset($table[1]) && ! empty($databasePrefix) && strpos($table[1], $databasePrefix) == 0) {
                $names[] = preg_replace('/^' . $databasePrefix . '/', '', $table[1]);
            }
        }

        return $names;
    }

    /**
     * Get Query Builder object.
     *
     * @return mixed
     */
    public function getBuilder()
    {
        if ($this->isQueryBuilder()) {
            return $this->query;
        }

        return $this->query->getQuery();
    }

    /**
     * Check query type is a builder.
     *
     * @return bool
     */
    public function isQueryBuilder()
    {
        return $this->query_type == 'builder';
    }

    /**
     * Returns current database prefix.
     *
     * @return string
     */
    public function databasePrefix()
    {
        return $this->getBuilder()->getGrammar()->getTablePrefix();
    }

    /**
     * Get equivalent or method of query builder.
     *
     * @param string $method
     * @return string
     */
    protected function getOrMethod($method)
    {
        if ( ! Str::contains(Str::lower($method), 'or')) {
            return 'or' . ucfirst($method);
        }

        return $method;
    }

    /**
     * Perform filter column on selected field.
     *
     * @param $method
     * @param $parameters
     * @param $column
     */
    protected function compileFilterColumn($method, $parameters, $column)
    {
        if (method_exists($this->getBuilder(), $method)
            && count($parameters) <= with(
                new \ReflectionMethod(
                    $this->getBuilder(),
                    $method
                )
            )->getNumberOfParameters()
        ) {
            if (Str::contains(Str::lower($method), 'raw')
                || Str::contains(Str::lower($method), 'exists')
            ) {
                call_user_func_array(
                    [$this->getBuilder(), $method],
                    $this->parameterize($parameters)
                );
            } else {
                call_user_func_array(
                    [$this->getBuilder(), $method],
                    $this->parameterize($column, $parameters)
                );
            }
        }
    }

    /**
     * Build Query Builder Parameters.
     *
     * @return array
     */
    public function parameterize()
    {
        $args       = func_get_args();
        $parameters = [];

        if (count($args) > 1) {
            $parameters[] = $args[0];
            foreach ($args[1] as $param) {
                $parameters[] = $param;
            }
        } else {
            foreach ($args[0] as $param) {
                $parameters[] = $param;
            }
        }

        return $parameters;
    }

    /**
     * Add a query on global search.
     *
     * @param mixed $query
     * @param string $column
     * @param string $keyword
     */
    private function compileGlobalSearch($query, $column, $keyword)
    {
        // Check if the database driver is PostgreSQL
        // If it is, cast the current column to TEXT datatype
        $cast_begin = null;
        $cast_end   = null;
        if ($this->databaseDriver() === 'pgsql') {
            $cast_begin = 'CAST(';
            $cast_end   = ' as TEXT)';
        }

        // wrap column possibly allow reserved words to be used as column
        $column = $this->wrapValue($column);
        if ($this->isCaseInsensitive()) {
            $query->orWhereRaw('LOWER(' . $cast_begin . $column . $cast_end . ') LIKE ?', [Str::lower($keyword)]);
        } else {
            $query->orWhereRaw($cast_begin . $column . $cast_end . ' LIKE ?', [$keyword]);
        }
    }

    /**
     * Returns current database driver.
     *
     * @return string
     */
    public function databaseDriver()
    {
        return $this->connection->getDriverName();
    }

    /**
     * Wrap value depending on database type.
     *
     * @param string $value
     * @return string
     */
    public function wrapValue($value)
    {
        $parts  = explode('.', $value);
        $column = '';
        foreach ($parts as $key) {
            $column = $this->wrapColumn($key, $column);
        }

        return substr($column, 0, strlen($column) - 1);
    }

    /**
     * Database column wrapper
     *
     * @param string $key
     * @param string $column
     * @return string
     */
    protected function wrapColumn($key, $column)
    {
        switch ($this->databaseDriver()) {
            case 'mysql':
                $column .= '`' . str_replace('`', '``', $key) . '`' . '.';
                break;

            case 'sqlsrv':
                $column .= '[' . str_replace(']', ']]', $key) . ']' . '.';
                break;

            case 'pgsql':
            case 'sqlite':
                $column .= '"' . str_replace('"', '""', $key) . '"' . '.';
                break;

            default:
                $column .= $key . '.';
        }

        return $column;
    }

    /**
     * Get config is case insensitive status.
     *
     * @return bool
     */
    public function isCaseInsensitive()
    {
        return Config::get('datatables.search.case_insensitive', false);
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
                    $column = $this->wrapValue($column);
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
     * Get filtered records.
     *
     * @return int
     */
    public function getTotalFilteredRecords()
    {
        return $this->filteredRecords = $this->count();
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

    /**
     * Check if Datatables allow pagination.
     *
     * @return bool
     */
    protected function isPaginationable()
    {
        return ! is_null($this->request['start']) && ! is_null($this->request['length']) && $this->request['length'] != -1;
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
     * Compile Datatables final output.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function compileOutput()
    {
        $this->setResults();
        $this->initColumns();
        $this->regulateArray();

        return $this->output();
    }

    /**
     * Set datatables results object and arrays.
     */
    public function setResults()
    {
        $this->result_array = array_map(
            function ($object) {
                return $object instanceof Arrayable ? $object->toArray() : (array) $object;
            }, $this->getResults()
        );
    }

    /**
     * Get results of query and convert to array.
     *
     * @return array
     */
    public function getResults()
    {
        $this->result_object = $this->query->get();

        return $this->result_object->toArray();
    }

    /**
     * Places extra columns.
     */
    public function initColumns()
    {
        foreach ($this->result_array as $rkey => &$rvalue) {
            $data = $this->convertToArray($rvalue, $rkey);

            $rvalue = $this->processAddColumns($data, $rkey, $rvalue);

            $rvalue = $this->processEditColumns($data, $rkey, $rvalue);
        }
    }

    /**
     * Converts array object values to associative array.
     *
     * @param array $rvalue
     * @param string|int $rKey
     * @return array
     */
    protected function convertToArray(array $rvalue, $rKey)
    {
        $data = [];
        foreach ($rvalue as $key => $value) {
            if (is_object($this->result_object[$rKey])) {
                $data[$key] = $this->result_object[$rKey]->$key;
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Process add columns.
     *
     * @param array $data
     * @param string|int $rKey
     * @param array|null $rvalue
     * @return array
     */
    protected function processAddColumns(array $data, $rKey, $rvalue)
    {
        foreach ($this->extra_columns as $key => $value) {
            $value = $this->processContent($value, $data, $rKey);

            $rvalue = $this->includeInArray($value, $rvalue);
        }

        return $rvalue;
    }

    /**
     * @param $value
     * @param $data
     * @param $rkey
     * @return mixed
     * @throws \Exception
     */
    protected function processContent($value, $data, $rkey)
    {
        if (is_string($value['content'])) :
            $value['content'] = $this->compileBlade($value['content'], $data);

            return $value;
        elseif (is_callable($value['content'])) :
            $value['content'] = $value['content']($this->result_object[$rkey]);

            return $value;
        endif;

        return $value;
    }

    /**
     * Parses and compiles strings by using Blade Template System.
     *
     * @param       $str
     * @param array $data
     * @return string
     * @throws \Exception
     */
    public function compileBlade($str, $data = [])
    {
        $empty_filesystem_instance = new Filesystem();
        $blade                     = new BladeCompiler($empty_filesystem_instance, 'datatables');
        $parsed_string             = $blade->compileString($str);

        ob_start() && extract($data, EXTR_SKIP);

        try {
            eval('?>' . $parsed_string);
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        $str = ob_get_contents();
        ob_end_clean();

        return $str;
    }

    /**
     * Places item of extra columns into result_array by care of their order.
     *
     * @param  $item
     * @param  $array
     * @return array
     */
    public function includeInArray($item, $array)
    {
        if ($item['order'] === false) {
            return array_merge($array, [$item['name'] => $item['content']]);
        } else {
            $count = 0;
            $last  = $array;
            $first = [];
            foreach ($array as $key => $value) {
                if ($count == $item['order']) {
                    return array_merge($first, [$item['name'] => $item['content']], $last);
                }

                unset($last[$key]);
                $first[$key] = $value;

                $count++;
            }
        }
    }

    /**
     * Process edit columns.
     *
     * @param array $data
     * @param string|int $rkey
     * @param array|null $rvalue
     * @return array
     */
    protected function processEditColumns(array $data, $rkey, $rvalue)
    {
        foreach ($this->edit_columns as $key => $value) {
            $value = $this->processContent($value, $data, $rkey);

            $rvalue[$value['name']] = $value['content'];
        }

        return $rvalue;
    }

    /**
     * Converts result_array number indexed array and consider excess columns.
     */
    public function regulateArray()
    {
        if ($this->m_data_support) {
            foreach ($this->result_array as $key => $value) {
                $this->setupDTRowVariables($key, $value);
                $this->result_array_r[] = $this->removeExcessColumns($value);
            }
        } else {
            foreach ($this->result_array as $key => $value) {
                $this->setupDTRowVariables($key, $value);
                $this->result_array_r[] = Arr::flatten($this->removeExcessColumns($value));
            }
        }
    }

    /**
     * Setup additional DT row variables.
     *
     * @param string $key
     * @param array &$data
     * @return array
     */
    protected function setupDTRowVariables($key, array &$data)
    {
        $this->processDTRowValue('DT_RowId', $this->row_id_tmpl, $key, $data);
        $this->processDTRowValue('DT_RowClass', $this->row_class_tmpl, $key, $data);
        $this->processDTRowDataAttr('DT_RowData', $this->row_data_tmpls, $key, $data);
        $this->processDTRowDataAttr('DT_RowAttr', $this->row_attr_tmpls, $key, $data);
    }

    /**
     * Process DT RowId and Class value.
     *
     * @param string $key
     * @param string|callable $template
     * @param string $index
     * @param array $data
     */
    protected function processDTRowValue($key, $template, $index, array &$data)
    {
        if ( ! empty($template)) {
            if ( ! is_callable($template) && Arr::get($data, $template)) {
                $data[$key] = Arr::get($data, $template);
            } else {
                $data[$key] = $this->getContent($template, $data, $this->result_object[$index]);
            }
        }
    }

    /**
     * Determines if content is callable or blade string, processes and returns.
     *
     * @param string|callable $content Pre-processed content
     * @param mixed $data data to use with blade template
     * @param mixed $param parameter to call with callable
     * @return string Processed content
     */
    public function getContent($content, $data = null, $param = null)
    {
        if (is_string($content)) {
            $return = $this->compileBlade($content, $data);
        } elseif (is_callable($content)) {
            $return = $content($param);
        } else {
            $return = $content;
        }

        return $return;
    }

    /**
     * Process DT Row Data and Attr.
     *
     * @param string $key
     * @param array $template
     * @param string $index
     * @param array $data
     */
    protected function processDTRowDataAttr($key, array $template, $index, array &$data)
    {
        if (count($template)) {
            $data[$key] = [];
            foreach ($template as $tkey => $tvalue) {
                $data[$key][$tkey] = $this->getContent($tvalue, $data, $this->result_object[$index]);
            }
        }
    }

    /**
     * Remove declared excess columns.
     *
     * @param array $data
     * @return array
     */
    public function removeExcessColumns(array $data)
    {
        foreach ($this->excess_columns as $evalue) {
            unset($data[$evalue]);
        }

        return $data;
    }

    /**
     * Render json response.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function output()
    {
        $output = [
            'draw'            => (int) $this->request['draw'],
            'recordsTotal'    => $this->totalRecords,
            'recordsFiltered' => $this->filteredRecords,
        ];

        if (isset($this->transformer)) {
            $fractal        = new Manager();
            $resource       = new Collection($this->result_array_r, new $this->transformer());
            $collection     = $fractal->createData($resource)->toArray();
            $output['data'] = $collection['data'];
        } else {
            $output['data'] = $this->result_array_r;
        }

        if ($this->isDebugging()) {
            $output = $this->showDebugger($output);
        }

        return new JsonResponse($output);
    }

    /**
     * Check if app is in debug mode.
     *
     * @return bool
     */
    public function isDebugging()
    {
        return Config::get('app.debug', false);
    }

    /**
     * Show debug parameters.
     *
     * @param  $output
     * @return mixed
     */
    protected function showDebugger($output)
    {
        $output['queries'] = $this->connection->getQueryLog();
        $output['input']   = $this->request->all();

        return $output;
    }

    /**
     * Use data columns.
     *
     * @return array
     */
    public function useDataColumns()
    {
        if ( ! count($this->result_array_r)) {
            return [];
        }

        $query = clone $this->query;
        if ($this->isQueryBuilder()) {
            $this->columns = array_keys((array) $query->first());
        } else {
            $this->columns = array_keys((array) $query->getQuery()->first());
        }

        return $this->columns;
    }

    /**
     * Get sColumns output.
     *
     * @return array
     */
    public function getOutputColumns()
    {
        $columns = array_merge($this->columns, $this->sColumns);
        $columns = array_diff($columns, $this->excess_columns);

        return Arr::flatten($columns);
    }

    /**
     * Add column in collection.
     *
     * @param string $name
     * @param string $content
     * @param bool|int $order
     * @return $this
     */
    public function addColumn($name, $content, $order = false)
    {
        $this->sColumns[] = $name;

        $this->extra_columns[] = ['name' => $name, 'content' => $content, 'order' => $order];

        return $this;
    }

    /**
     * Edit column's content.
     *
     * @param string $name
     * @param string $content
     * @return $this
     */
    public function editColumn($name, $content)
    {
        $this->edit_columns[] = ['name' => $name, 'content' => $content];

        return $this;
    }

    /**
     * Remove column from collection.
     *
     * @return $this
     */
    public function removeColumn()
    {
        $names                = func_get_args();
        $this->excess_columns = array_merge($this->excess_columns, $names);

        return $this;
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
     * Allows previous API calls where the methods were snake_case.
     * Will convert a camelCase API call to a snake_case call.
     *
     * @param  $name
     * @param  $arguments
     * @return $this|mixed
     */
    public function __call($name, $arguments)
    {
        $name = Str::camel(Str::lower($name));
        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $arguments);
        } elseif (method_exists($this->getBuilder(), $name)) {
            call_user_func_array([$this->getBuilder(), $name], $arguments);
        } else {
            trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
        }

        return $this;
    }

    /**
     * Sets DT_RowClass template
     * result: <tr class="output_from_your_template">.
     *
     * @param string|callable $content
     * @return $this
     */
    public function setRowClass($content)
    {
        $this->row_class_tmpl = $content;

        return $this;
    }

    /**
     * Sets DT_RowId template
     * result: <tr id="output_from_your_template">.
     *
     * @param string|callable $content
     * @return $this
     */
    public function setRowId($content)
    {
        $this->row_id_tmpl = $content;

        return $this;
    }

    /**
     * Set DT_RowData templates.
     *
     * @param array $data
     * @return $this
     */
    public function setRowData(array $data)
    {
        $this->row_data_tmpls = $data;

        return $this;
    }

    /**
     * Add DT_RowData template.
     *
     * @param string $key
     * @param string|callable $value
     * @return $this
     */
    public function addRowData($key, $value)
    {
        $this->row_data_tmpls[$key] = $value;

        return $this;
    }

    /**
     * Set DT_RowAttr templates
     * result: <tr attr1="attr1" attr2="attr2">.
     *
     * @param array $data
     * @return $this
     */
    public function setRowAttr(array $data)
    {
        $this->row_attr_tmpls = $data;

        return $this;
    }

    /**
     * Add DT_RowAttr template.
     *
     * @param string $key
     * @param string|callable $value
     * @return $this
     */
    public function addRowAttr($key, $value)
    {
        $this->row_attr_tmpls[$key] = $value;

        return $this;
    }

    /**
     * Override default column filter search.
     *
     * @param string $column
     * @param string $method
     * @return $this
     * @internal param $mixed ...,... All the individual parameters required for specified $method
     */
    public function filterColumn($column, $method)
    {
        $params                        = func_get_args();
        $this->filter_columns[$column] = ['method' => $method, 'parameters' => array_splice($params, 2)];

        return $this;
    }

    /**
     * Set data output transformer.
     *
     * @param \League\Fractal\TransformerAbstract $transformer
     * @return $this
     */
    public function setTransformer($transformer)
    {
        $this->transformer = $transformer;

        return $this;
    }
}
