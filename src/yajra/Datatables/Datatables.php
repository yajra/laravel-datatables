<?php namespace yajra\Datatables;

/**
 * Laravel Datatables Package
 * This Package is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net)
 *
 * @package    Laravel
 * @category   Package
 * @author     Arjay Angeles <aqangeles@gmail.com>
 * @version    4.2.0
 */

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class Datatables
{

    /**
     * Database connection used
     *
     * @var Illuminate\Database\Connection
     */
    public $connection;

    /**
     * Query object
     *
     * @var Eloquent|Builder
     */
    public $query;

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
    protected $query_type;

    /**
     * Array of columns to be added on result
     *
     * @var array
     */
    protected $extra_columns = [];

    /**
     * Array of columns to be removed on output
     *
     * @var array
     */
    protected $excess_columns = ['rn', 'row_num'];

    /**
     * Array of columns to be edited
     *
     * @var array
     */
    protected $edit_columns = [];

    /**
     * sColumns to ouput
     *
     * @var array
     */
    protected $sColumns = [];

    /**
     * Total records
     *
     * @var integer
     */
    protected $totalRecords = 0;

    /**
     * Total filtered records
     *
     * @var integer
     */
    protected $filteredRecords = 0;

    /**
     * Eloquent/Builder result
     *
     * @var array
     */
    protected $result_object;

    /**
     * Result array
     *
     * @var array
     */
    protected $result_array = [];

    /**
     * Regulated result array
     *
     * @var array
     */
    protected $result_array_r = [];

    /**
     * Flag for DT support for mdata
     *
     * @var boolean
     */
    protected $mDataSupport = false;

    /**
     * Auto-filter flag
     * @var boolean
     */
    protected $autoFilter = true;

    /**
     * Flag for DT version
     *
     * @var boolean
     */
    protected $new_version = false;

    /**
     * DT_RowID template
     *
     * @var string
     */
    protected $row_id_tmpl;

    /**
     * DT_RowClass template
     *
     * @var string
     */
    protected $row_class_tmpl;

    /**
     * DT_RowData template
     *
     * @var array
     */
    protected $row_data_tmpls = array();

    /**
     * DT_RowAttr template
     *
     * @var array
     */
    protected $row_attr_tmpls = array();

    /**
     * Read Input into $this->input according to jquery.dataTables.js version
     *
     * @return this
     */
    public function __construct()
    {
        $request = new Request($_GET, $_POST);
        $this->setData($this->processData($request->input()));

        return $this;
    }

    /**
     * Will take an input array and return the formatted dataTables data as an array
     *
     * @param array $input
     * @return array
     */
    public function processData($input = [])
    {
        $formatted_input = [];
        if (isset($input['draw'])) {
            // DT version 1.10+
            $input['version'] = '1.10';
            $formatted_input = $input;
            $this->new_version = true;
        } else {
            // DT version < 1.10
            $formatted_input['version'] = '1.9';
            $formatted_input['draw'] = Arr::get($input, 'sEcho', '');
            $formatted_input['start'] = Arr::get($input, 'iDisplayStart', 0);
            $formatted_input['length'] = Arr::get($input, 'iDisplayLength', 10);
            $formatted_input['search'] = array(
                'value' => Arr::get($input, 'sSearch', ''),
                'regex' => Arr::get($input, 'bRegex', ''),
                );
            $formatted_input['_'] = Arr::get($input, '_', '');
            $columns = explode(',', Arr::get($input, 'sColumns', ''));
            $formatted_input['columns'] = array();
            for ($i = 0; $i < Arr::get($input, 'iColumns', 0); $i++) {
                $arr = array();
                $arr['name'] = isset($columns[$i]) ? $columns[$i] : '';
                $arr['data'] = Arr::get($input, 'mDataProp_' . $i, '');
                $arr['searchable'] = Arr::get($input, 'bSearchable_' . $i, '');
                $arr['search'] = array();
                $arr['search']['value'] = Arr::get($input, 'sSearch_' . $i, '');
                $arr['search']['regex'] = Arr::get($input, 'bRegex_' . $i, '');
                $arr['orderable'] = Arr::get($input, 'bSortable_' . $i, '');
                $formatted_input['columns'][] = $arr;
            }
            $formatted_input['order'] = array();
            for ($i = 0; $i < Arr::get($input, 'iSortingCols', 0); $i++) {
                $arr = array();
                $arr['column'] = Arr::get($input, 'iSortCol_' . $i, '');
                $arr['dir'] = Arr::get($input, 'sSortDir_' . $i, '');
                $formatted_input['order'][] = $arr;
            }
        }
        return $formatted_input;
    }

    /**
     * Get input data
     *
     * @return array $this->input
     */
    public function getData()
    {
        return $this->input;
    }

    /**
     * Sets input data.
     * Can be used when not wanting to use default Input data.
     *
     * @param array $data
     */
    public function setData($data)
    {
        $this->input = $data;
    }

    /**
     * Gets query and returns instance of class
     *
     * @param $query
     * @return static
     */
    public static function of($query)
    {
        $ins = new static;
        $ins->saveQuery($query);

        // set connection and query variable
        if ($ins->query_type == 'eloquent') {
            $ins->connection = $ins->query->getModel()->getConnection();
            $ins->query = $query;
        } else {
            $ins->connection = $query->getConnection();
            $ins->query = $query;
        }

        $ins->createLastColumn();

        $ins->getTotalRecords(); //Total records

        return $ins;
    }

    /**
     * Saves given query and determines its type
     *
     * @param $query
     */
    private function saveQuery($query)
    {
        $this->query = $query;
        $this->query_type = $query instanceof Builder ? 'fluent' : 'eloquent';
        $this->columns = $this->query_type == 'eloquent' ? $this->query->getQuery()->columns : $this->query->columns;
    }

    /**
     * Creates an array which contains published last columns in sql with their index
     *
     * @return null
     */
    private function createLastColumn()
    {
        $extra_columns_indexes = [];
        $last_columns = [];
        $count = 0;

        foreach ($this->extra_columns as $key => $value) {
            if ($value['order'] === false) {
                continue;
            }
            $extra_columns_indexes[] = $value['order'];
        }

        for ($i = 0, $c = count($this->columns); $i < $c; $i++) {

            if (in_array($this->getColumnName($this->columns[$i]), $this->excess_columns)) {
                continue;
            }

            if (in_array($count, $extra_columns_indexes)) {
                $count++;
                $i--;
                continue;
            }

            preg_match('#\s+as\s+(\S*?)$#si', $this->columns[$i], $matches);
            $last_columns[$count] = empty($matches) ? $this->columns[$i] : $matches[1];
            $count++;
        }

        $this->last_columns = $last_columns;
    }

    /**
     * Get column name from string
     *
     * @param  string $str
     * @return string
     */
    private function getColumnName($str)
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
    private function getTotalRecords()
    {
        return $this->totalRecords = $this->count();
    }

    /**
     * Counts current query
     *
     * @return int
     */
    private function count()
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
        $this->mDataSupport = $mDataSupport;

        // check if auto filtering was overidden
        if ($this->autoFilter) {
            $this->doFiltering();
        }

        $this->getFilteredRecords(); // Filtered records
        $this->doPaging();
        $this->doOrdering();

        $this->getResult();
        $this->initColumns();
        $this->regulateArray();

        return $this->output();
    }

    /**
     * Datatable filtering
     *
     * @return null
     */
    private function doFiltering()
    {
        $input = $this->input;
        $columns = $input['columns'];

        // if older version, set the column name to query's fields
        if ( ! $this->new_version) {
            for ($i=0; $i < count($columns); $i++) {
                $columns[$i]['name'] = $this->columns[$i];
                if (stripos($columns[$i]['name'], ' AS ') !== false or
                    $columns[$i]['name'] instanceof \Illuminate\Database\Query\Expression) {
                    $columns[$i]['name'] = '';
                    $columns[$i]['searchable'] = false;
                    $columns[$i]['orderable'] = false;
                }
            }
        }

        if ( ! empty($this->input['search']['value'])) {
            $this->query->where(function ($query) use ($columns, $input) {
                for ($i = 0, $c = count($columns); $i < $c; $i++) {
                    if ($columns[$i]['searchable'] == "true" and ! empty($columns[$i]['name'])) {
                        $column = $columns[$i]['name'];

                        if (stripos($column, ' AS ') !== false) {
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
                            $query->orWhereRaw('LOWER(' . $cast_begin . $column . $cast_end . ') LIKE ?', [strtolower($keyword)]);
                        } else {
                            $query->orWhereRaw($cast_begin . $column . $cast_end . ' LIKE ?', [$keyword]);
                        }
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
     * @param  array  $columns
     * @return void
     */
    public function doColumnSearch(array $columns)
    {
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($columns[$i]['searchable'] == "true" and ! empty($columns[$i]['search']['value']) and ! empty($columns[$i]['name'])) {
                $column = $columns[$i]['name'];
                $keyword = $this->setupKeyword ($columns[$i]['search']['value']);

                // wrap column possibly allow reserved words to be used as column
                $column = $this->wrapColumn($column);
                if ($this->isCaseInsensitive()) {
                    $this->query->whereRaw('LOWER(' . $column . ') LIKE ?', [strtolower($keyword)]);
                } else {
                    $col = strstr($column, '(') ? $this->connection->raw($column) : $column;
                    $this->query->where($col, 'LIKE', $keyword);
                }
            }
        }
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
    private function cleanColumns($cols, $use_alias = true)
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
        if ($this->query_type == 'eloquent') {
            $this->columns = array_keys((array) $query->getQuery()->first());
        } else {
            $this->columns = array_keys((array) $query->first());
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
            $wild = strtolower($wild);
        }

        return $wild;
    }

    /**
     * Will prefix column if needed
     *
     * @param string $column
     * @return string
     */
    protected function prefixColumn($column)
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
    protected function tableNames()
    {
        $names = [];
        $query = ($this->query_type == 'eloquent') ? $this->query->getQuery() : $this->query;
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
        if ($this->query_type == 'eloquent') {
            $query = $this->query->getQuery();
        } else {
            $query = $this->query;
        }

        return $query->getGrammar()->getTablePrefix();
    }

    /**
     * Get filtered records
     *
     * @return int
     */
    private function getFilteredRecords()
    {
        return $this->filteredRecords = $this->count();
    }

    /**
     * Datatables paging
     *
     * @return null
     */
    private function doPaging()
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
    private function doOrdering()
    {
        if (array_key_exists('order', $this->input) && count($this->input['order']) > 0) {
            $columns = $this->cleanColumns($this->last_columns);

            for ($i = 0, $c = count($this->input['order']); $i < $c; $i++) {
                $order_col = (int) $this->input['order'][$i]['column'];
                $order_dir = $this->input['order'][$i]['dir'];
                if ($this->new_version) {
                    $column = $this->input['columns'][$order_col];
                    if ($column['orderable'] == "true") {
                        if ( ! empty($column['name'])) {
                            $this->query->orderBy($column['name'], $order_dir);
                        } elseif (isset($columns[$order_col])) {
                            $this->query->orderBy($columns[$order_col], $order_dir);
                        }
                    }
                } else {
                    if (isset($columns[$order_col])) {
                        if ($this->input['columns'][$order_col]['orderable'] == "true") {
                            $this->query->orderBy($columns[$order_col], $order_dir);
                        }
                    }
                }
            }
        }
    }

    /**
     * Gets results from prepared query
     *
     * @return null
     */
    private function getResult()
    {
        $this->result_object = $this->query->get();
        if ($this->query_type == 'eloquent') {
            $this->result_array = array_map(function ($object) {
                return (array) $object;
            }, $this->result_object->toArray());
        } else {
            $this->result_array = array_map(function ($object) {
                return (array) $object;
            }, $this->result_object);
        }
    }

    /**
     * Places extra columns
     *
     * @return null
     */
    private function initColumns()
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
                    $value['content'] = $this->blader($value['content'], $data);
                elseif (is_callable($value['content'])):
                    $value['content'] = $value['content']($this->result_object[$rkey]);
                endif;

                $rvalue = $this->includeInArray($value, $rvalue);
            }

            // Process edit columns
            foreach ($this->edit_columns as $key => $value) {
                if (is_string($value['content'])):
                    $value['content'] = $this->blader($value['content'], $data);
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
     * @return string
     */
    private function blader($str, $data = [])
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
     * @return null
     */
    private function includeInArray($item, $array)
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
    private function regulateArray()
    {
        if ($this->mDataSupport) {
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
     * @param  array  &$data
     * @return array
     */
    protected function setupDTRowVariables($key, array &$data)
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
            $data['DT_RowData'] = array();
            foreach ($this->row_data_tmpls as $tkey => $tvalue) {
                $data['DT_RowData'][$tkey] = $this->getContent($tvalue, $data, $this->result_object[$key]);
            }
        }

        if (count($this->row_attr_tmpls)) {
            $data['DT_RowAttr'] = array();
            foreach ($this->row_attr_tmpls as $tkey => $tvalue) {
                $data['DT_RowAttr'][$tkey] = $this->getContent($tvalue, $data, $this->result_object[$key]);
            }
        }

    }

    /**
     * Remove declared excess columns
     *
     * @param  array  $data
     * @return array
     */
    private function removeExcessColumns(array $data)
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
    private function output()
    {
        if ($this->new_version) {
            $output = [
                "draw"            => (int) $this->input['draw'],
                "recordsTotal"    => $this->totalRecords,
                "recordsFiltered" => $this->filteredRecords,
                "data"            => $this->result_array_r,
            ];
        } else {
            $sColumns = $this->getOutputColumns();
            $output = [
                "sEcho"                => (int) $this->input['draw'],
                "iTotalRecords"        => $this->totalRecords,
                "iTotalDisplayRecords" => $this->filteredRecords,
                "aaData"               => $this->result_array_r,
                "sColumns"             => $sColumns
            ];
        }

        if (Config::get('app.debug', false)) {
            $output['aQueries'] = $this->connection->getQueryLog();
        }

        return new JsonResponse($output);
    }

    /**
     * Get sColumns output
     *
     * @return array
     */
    private function getOutputColumns()
    {
        $columns = array_diff($this->useDataColumns(), $this->excess_columns);

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
    protected function databaseDriver()
    {
        if ($this->query_type == 'eloquent') {
            $query = $this->query->getQuery();
        } else {
            $query = $this->query;
        }

        return $query->getConnection()->getDriverName();
    }

    /**
     * Wrap column depending on database type
     *
     * @param  string $value
     * @return string
     */
    protected function wrapColumn($value)
    {
        $parts = explode('.', $value);
        $column = '';
        foreach ($parts as $key) {
            switch ($this->databaseDriver()) {
                case 'mysql':
                    $column .= '`'.str_replace('`', '``', $key).'`' . '.';
                    break;

                case 'sqlsrv':
                    $column .= '['.str_replace(']', ']]', $key).']' . '.';
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
            return call_user_func_array(array($this, $name), $arguments);
        } else {
            trigger_error('Call to undefined method ' . __CLASS__ . '::' . $name . '()', E_USER_ERROR);
        }
    }

    /**
     * Determines if content is callable or blade string, processes and returns
     *
     * @param string|callable $content Pre-processed content
     * @param mixed           $data    data to use with blade template
     * @param mixed           $param   parameter to call with callable
     *
     * @return string Processed content
     */
    protected function getContent($content, $data = null, $param = null)
    {
        if (is_string($content)) {
            $return = $this->blader($content, $data);
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
     *
     * @return $this
     */
    protected function setRowClass($content)
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
    protected function setRowId($content)
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
    protected function setRowData(array $data)
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
    protected function addRowData($key, $value)
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
    protected function setRowAttr(array $data)
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
    protected function addRowAttr($key, $value)
    {
        $this->row_attr_tmpls[$key] = $value;

        return $this;
    }

}
