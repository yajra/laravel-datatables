<?php namespace yajra\Datatables\Engine;

/**
 * Laravel Datatables Base Engine
 *
 * @package    Laravel
 * @category   Package
 * @author     Arjay Angeles <aqangeles@gmail.com>
 */

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\View\Compilers\BladeCompiler;

class BaseEngine
{

    /**
     * Database connection used
     *
     * @var \Illuminate\Database\Connection
     */
    public $connection;

    /**
     * Query object
     *
     * @var EloquentBuilder|QueryBuilder
     */
    public $query;

    /**
     * QueryBuilder object
     *
     * @var QueryBuilder
     */
    public $builder;

    /**
     * Input variables
     *
     * @var array
     */
    public $input;

    /**
     * Array of result columns/fields
     *
     * @var array
     */
    public $columns = [];

    /**
     * Array of last columns
     *
     * @var array
     */
    public $last_columns = [];

    /**
     * Query type
     *
     * @var string
     */
    public $query_type;

    /**
     * Array of columns to be added on result
     *
     * @var array
     */
    public $extra_columns = [];

    /**
     * Array of columns to be removed on output
     *
     * @var array
     */
    public $excess_columns = ['rn', 'row_num'];

    /**
     * Array of columns to be edited
     *
     * @var array
     */
    public $edit_columns = [];

    /**
     * sColumns to output
     *
     * @var array
     */
    public $sColumns = [];

    /**
     * Total records
     *
     * @var integer
     */
    public $totalRecords = 0;

    /**
     * Total filtered records
     *
     * @var integer
     */
    public $filteredRecords = 0;

    /**
     * Eloquent/Builder result
     *
     * @var array
     */
    public $result_object;

    /**
     * Result array
     *
     * @var array
     */
    public $result_array = [];

    /**
     * Regulated result array
     *
     * @var array
     */
    public $result_array_r = [];

    /**
     * Flag for DT support for mdata
     *
     * @var boolean
     */
    public $m_data_support = false;

    /**
     * Auto-filter flag
     *
     * @var boolean
     */
    public $autoFilter = true;

    /**
     * DT_RowID template
     *
     * @var string
     */
    public $row_id_tmpl;

    /**
     * DT_RowClass template
     *
     * @var string
     */
    public $row_class_tmpl;

    /**
     * DT_RowData template
     *
     * @var array
     */
    public $row_data_tmpls = [];

    /**
     * DT_RowAttr template
     *
     * @var array
     */
    public $row_attr_tmpls = [];

    /**
     * Construct base engine
     */
    public function __construct()
    {
        $request = Request::capture();
        $this->input = $request->all();
        $this->getTotalRecords(); // Total records
    }

    /**
     * Get column name from string
     *
     * @param  string $str
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
     * Get total records
     *
     * @return int
     */
    public function getTotalRecords()
    {
        return $this->totalRecords = $this->count();
    }

    /**
     * Counts current query
     *
     * @return int
     */
    public function count()
    {
        $query = $this->query;

        // if its a normal query ( no union ) replace the select with static text to improve performance
        $myQuery = clone $query;
        if ( ! preg_match('/UNION/i', strtoupper($myQuery->toSql()))) {
            $myQuery->select($this->connection->raw("'1' as row_count"));
        }

        return $this->connection->table($this->connection->raw('(' . $myQuery->toSql() . ') count_row_table'))
            ->setBindings($myQuery->getBindings())->count();
    }

    /**
     * Organizes works
     *
     * @param bool $mDataSupport
     * @return null
     */
    public function make($mDataSupport = false)
    {
        // set mData support flag
        $this->m_data_support = $mDataSupport;

        // check if auto filtering was overridden
        if ($this->autoFilter) {
            $this->doFiltering();
        }

        $this->getTotalFilteredRecords();
        $this->doPaging();
        $this->doOrdering();

        $this->setResults();
        $this->initColumns();
        $this->regulateArray();

        return $this->output();
    }

    /**
     * Set datatables results object and arrays
     */
    public function setResults()
    {
        // @todo: implement set results on child class
    }

    /**
     * Setup search keyword
     *
     * @param  string $value
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
     * Get config use wild card status
     *
     * @return boolean
     */
    public function isWildcard()
    {
        return Config::get('datatables.search.use_wildcards', false);
    }


    /**
     * Get config is case insensitive status
     *
     * @return boolean
     */
    public function isCaseInsensitive()
    {
        return Config::get('datatables.search.case_insensitive', false);
    }

    /**
     * Clean columns name
     *
     * @param array $cols
     * @param bool $use_alias
     * @return array
     */
    public function cleanColumns($cols, $use_alias = true)
    {
        $return = [];
        foreach ($cols as $i => $col) {
            preg_match('#^(.*?)\s+as\s+(\S*?)$#si', $col, $matches);
            $return[$i] = empty($matches) ? $col : $matches[$use_alias ? 2 : 1];
        }

        return $return;
    }

    /**
     * Use data columns
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
     * Adds % wildcards to the given string
     *
     * @param string $str
     * @param bool $lowercase
     * @return string
     */
    public function wildcardLikeString($str, $lowercase = true)
    {
        $wild = '%';
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
     * Will prefix column if needed
     *
     * @param string $column
     * @return string
     */
    public function prefixColumn($column)
    {
        $table_names = $this->tableNames();
        if (count(array_filter($table_names, function ($value) use (&$column) {
            return strpos($column, $value . ".") === 0;
        }))) {
            //the column starts with one of the table names
            $column = $this->databasePrefix() . $column;
        }

        return $column;
    }

    /**
     * Will look through the query and all it's joins to determine the table names
     *
     * @return array
     */
    public function tableNames()
    {
        $names = [];
        $query = ($this->isQueryBuilder()) ? $this->query : $this->query->getQuery();
        $names[] = $query->from;
        $joins = $query->joins ?: [];
        $databasePrefix = $this->databasePrefix();
        foreach ($joins as $join) {
            $table = preg_split("/ as /i", $join->table);
            $names[] = $table[0];
            if (isset($table[1]) && ! empty($databasePrefix) && strpos($table[1], $databasePrefix) == 0) {
                $names[] = preg_replace('/^' . $databasePrefix . '/', '', $table[1]);
            }
        }

        return $names;
    }

    /**
     * Returns current database prefix
     *
     * @return string
     */
    public function databasePrefix()
    {
        if ($this->isQueryBuilder()) {
            $query = $this->query;
        } else {
            $query = $this->query->getQuery();
        }

        return $query->getGrammar()->getTablePrefix();
    }

    /**
     * Get filtered records
     *
     * @return int
     */
    public function getTotalFilteredRecords()
    {
        return $this->filteredRecords = $this->count();
    }

    /**
     * Datatables paging
     *
     * @return null
     */
    public function doPaging()
    {
        if ( ! is_null($this->input['start']) && ! is_null($this->input['length']) && $this->input['length'] != -1) {
            $this->query->skip($this->input['start'])
                ->take((int) $this->input['length'] > 0 ? $this->input['length'] : 10);
        }
    }

    /**
     * Datatable ordering
     *
     * @return null
     */
    public function doOrdering()
    {
        if (array_key_exists('order', $this->input) && count($this->input['order']) > 0) {
            for ($i = 0, $c = count($this->input['order']); $i < $c; $i++) {
                $order_col = (int) $this->input['order'][$i]['column'];
                $order_dir = $this->input['order'][$i]['dir'];
                $column = $this->input['columns'][$order_col];

                if ($column['orderable'] <> "true") {
                    continue;
                }

                if (isset($column['name']) and $column['name'] <> '') {
                    $this->query->orderBy($column['name'], $order_dir);
                } else {
                    $this->query->orderBy($this->columns[$order_col], $order_dir);
                }
            }
        }
    }

    /**
     * Places extra columns
     */
    public function initColumns()
    {
        foreach ($this->result_array as $rkey => &$rvalue) {

            // Convert data array to object value
            $data = [];
            foreach ($rvalue as $key => $value) {
                if (is_object($this->result_object[$rkey])) {
                    $data[$key] = $this->result_object[$rkey]->$key;
                } else {
                    $data[$key] = $value;
                }
            }

            // Process add columns
            foreach ($this->extra_columns as $key => $value) {
                if (is_string($value['content'])):
                    $value['content'] = $this->compileBlade($value['content'], $data);
                elseif (is_callable($value['content'])):
                    $value['content'] = $value['content']($this->result_object[$rkey]);
                endif;

                $rvalue = $this->includeInArray($value, $rvalue);
            }

            // Process edit columns
            foreach ($this->edit_columns as $key => $value) {
                if (is_string($value['content'])):
                    $value['content'] = $this->compileBlade($value['content'], $data);
                elseif (is_callable($value['content'])):
                    $value['content'] = $value['content']($this->result_object[$rkey]);
                endif;

                $rvalue[$value['name']] = $value['content'];
            }
        }
    }

    /**
     * Parses and compiles strings by using Blade Template System
     *
     * @param $str
     * @param array $data
     * @return string
     * @throws \Exception
     */
    public function compileBlade($str, $data = [])
    {
        $empty_filesystem_instance = new Filesystem;
        $blade = new BladeCompiler($empty_filesystem_instance, 'datatables');
        $parsed_string = $blade->compileString($str);

        ob_start() and extract($data, EXTR_SKIP);

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
     * Places item of extra columns into result_array by care of their order
     *
     * @param $item
     * @param $array
     * @return array
     */
    public function includeInArray($item, $array)
    {
        if ($item['order'] === false) {
            return array_merge($array, [$item['name'] => $item['content']]);
        } else {
            $count = 0;
            $last = $array;
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
     * Converts result_array number indexed array and consider excess columns
     *
     * @return null
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
     * Setup additional DT row variables
     *
     * @param  string $key
     * @param  array &$data
     * @return array
     */
    public function setupDTRowVariables($key, array &$data)
    {
        if ( ! empty($this->row_id_tmpl)) {
            if ( ! is_callable($this->row_id_tmpl) and Arr::get($data, $this->row_id_tmpl)) {
                $data['DT_RowId'] = Arr::get($data, $this->row_id_tmpl);
            } else {
                $data['DT_RowId'] = $this->getContent($this->row_id_tmpl, $data, $this->result_object[$key]);
            }
        }

        if ( ! empty($this->row_class_tmpl)) {
            if ( ! is_callable($this->row_class_tmpl) and Arr::get($data, $this->row_class_tmpl)) {
                $data['DT_RowClass'] = Arr::get($data, $this->row_class_tmpl);
            } else {
                $data['DT_RowClass'] = $this->getContent($this->row_class_tmpl, $data, $this->result_object[$key]);
            }
        }

        if (count($this->row_data_tmpls)) {
            $data['DT_RowData'] = [];
            foreach ($this->row_data_tmpls as $tkey => $tvalue) {
                $data['DT_RowData'][$tkey] = $this->getContent($tvalue, $data, $this->result_object[$key]);
            }
        }

        if (count($this->row_attr_tmpls)) {
            $data['DT_RowAttr'] = [];
            foreach ($this->row_attr_tmpls as $tkey => $tvalue) {
                $data['DT_RowAttr'][$tkey] = $this->getContent($tvalue, $data, $this->result_object[$key]);
            }
        }

    }

    /**
     * Remove declared excess columns
     *
     * @param  array $data
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
     * Render json response
     *
     * @return JsonResponse
     */
    public function output()
    {
        $output = [
            "draw"            => (int) $this->input['draw'],
            "recordsTotal"    => $this->totalRecords,
            "recordsFiltered" => $this->filteredRecords,
            "data"            => $this->result_array_r,
        ];

        return new JsonResponse($output);
    }

    /**
     * Get sColumns output
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
     * Add column in collection
     *
     * @param string $name
     * @param string $content
     * @param bool|int $order
     * @return Datatables
     */
    public function addColumn($name, $content, $order = false)
    {
        $this->sColumns[] = $name;

        $this->extra_columns[] = ['name' => $name, 'content' => $content, 'order' => $order];

        return $this;
    }

    /**
     * Edit column's content
     *
     * @param  string $name
     * @param  string $content
     * @return Datatables
     */
    public function editColumn($name, $content)
    {
        $this->edit_columns[] = ['name' => $name, 'content' => $content];

        return $this;
    }

    /**
     * Remove column from collection
     *
     * @return Datatables
     */
    public function removeColumn()
    {
        $names = func_get_args();
        $this->excess_columns = array_merge($this->excess_columns, $names);

        return $this;
    }

    /**
     * Set auto filter off and run your own filter
     *
     * @param callable $callback
     * @return Datatables
     * @internal param $Closure
     */
    public function filter(Closure $callback)
    {
        $this->autoFilter = false;

        $query = $this->query;
        call_user_func($callback, $query);

        return $this;
    }

    /**
     * Returns current database driver
     *
     * @return string
     */
    public function databaseDriver()
    {
        return $this->connection->getDriverName();
    }

    /**
     * Wrap column depending on database type
     *
     * @param  string $value
     * @return string
     */
    public function wrapColumn($value)
    {
        $parts = explode('.', $value);
        $column = '';
        foreach ($parts as $key) {
            switch ($this->databaseDriver()) {
                case 'mysql':
                    $column .= '`' . str_replace('`', '``', $key) . '`' . '.';
                    break;

                case 'sqlsrv':
                    $column .= '[' . str_replace(']', ']]', $key) . ']' . '.';
                    break;

                default:
                    $column .= $key . '.';
            }
        }

        return substr($column, 0, strlen($column) - 1);
    }

    /**
     * Allows previous API calls where the methods were snake_case.
     * Will convert a camelCase API call to a snake_case call.
     */
    public function __call($name, $arguments)
    {
        $name = Str::camel(Str::lower($name));
        if (method_exists($this, $name)) {
            return call_user_func_array([$this, $name], $arguments);
        } else {
            trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
        }
    }

    /**
     * Determines if content is callable or blade string, processes and returns
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
     * Sets DT_RowClass template
     * result: <tr class="output_from_your_template">
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
     * result: <tr id="output_from_your_template">
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
     * Set DT_RowData templates
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
     * Add DT_RowData template
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
     * result: <tr attr1="attr1" attr2="attr2">
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
     * Add DT_RowAttr template
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
     * @return bool
     */
    public function isQueryBuilder()
    {
        return $this->query_type == 'builder';
    }

    /**
     * Datatable filtering
     *
     * @return null
     */
    public function doFiltering()
    {
        $input = $this->input;
        $columns = $input['columns'];

        if ( ! empty($this->input['search']['value'])) {
            $this->query->where(function ($query) use ($columns, $input) {
                for ($i = 0, $c = count($columns); $i < $c; $i++) {
                    if ( ! $columns[$i]['searchable'] == "true") {
                        continue;
                    }

                    if ( ! empty($columns[$i]['name'])) {
                        $column = $columns[$i]['name'];
                    } else {
                        $column = $this->columns[$i];
                    }

                    if (Str::contains(Str::upper($column), ' AS ')) {
                        $column = $this->getColumnName($column);
                    }

                    // there's no need to put the prefix unless the column name is prefixed with the table name.
                    $column = $this->prefixColumn($column);

                    $keyword = '%' . $input['search']['value'] . '%';
                    if ($this->isWildcard()) {
                        $keyword = $this->wildcardLikeString($input['search']['value']);
                    }

                    // Check if the database driver is PostgreSQL
                    // If it is, cast the current column to TEXT datatype
                    $cast_begin = null;
                    $cast_end = null;
                    if ($this->databaseDriver() === 'pgsql') {
                        $cast_begin = "CAST(";
                        $cast_end = " as TEXT)";
                    }

                    // wrap column possibly allow reserved words to be used as column
                    $column = $this->wrapColumn($column);
                    if ($this->isCaseInsensitive()) {
                        $query->orWhereRaw('LOWER(' . $cast_begin . $column . $cast_end . ') LIKE ?',
                            [Str::lower($keyword)]);
                    } else {
                        $query->orWhereRaw($cast_begin . $column . $cast_end . ' LIKE ?', [$keyword]);
                    }
                }
            });
        }

        // column search
        $this->doColumnSearch($columns);
    }

    /**
     * Perform column search
     *
     * @param  array $columns
     * @return void
     */
    public function doColumnSearch(array $columns)
    {
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($columns[$i]['searchable'] == "true" and ! empty($columns[$i]['search']['value']) and ! empty($columns[$i]['name'])) {
                $column = $columns[$i]['name'];
                $keyword = $this->setupKeyword($columns[$i]['search']['value']);

                // wrap column possibly allow reserved words to be used as column
                $column = $this->wrapColumn($column);
                if ($this->isCaseInsensitive()) {
                    $this->query->whereRaw('LOWER(' . $column . ') LIKE ?', [Str::lower($keyword)]);
                } else {
                    $col = strstr($column, '(') ? $this->connection->raw($column) : $column;
                    $this->query->where($col, 'LIKE', $keyword);
                }
            }
        }
    }

}
