<?php namespace Bllim\Datatables;

/**
 * Laravel Datatable Bundle
 *
 * This bundle is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net)
 *
 * @package Laravel
 * @category Bundle
 * @version 1.4.1
 * @author Bilal Gultekin <bilal@bilal.im>
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Filesystem\Filesystem;

class Datatables
{
    public $query;
    protected $query_type;

    protected $extra_columns = array();
    protected $excess_columns = array();
    protected $edit_columns = array();
    protected $filter_columns = array();
    protected $sColumns = array();

    public $columns = array();
    public $last_columns = array();

    protected $count_all = 0;
    protected $display_all = 0;

    protected $result_object;
    protected $result_array = array();
    protected $result_array_r = array();

    protected $input = array();
    protected $mDataSupport;

    protected $index_column;
    protected $row_class_tmpl = null;
    protected $row_data_tmpls = array();    


    /**
     * Read Input into $this->input according to jquery.dataTables.js version
     */
    public function __construct() {
        
        if (Input::has('draw')) {
            
            // version 1.10+
            $this->input = Input::get();
            
        } else {
            
            // version < 1.10
            
            $this->input['draw'] = Input::get('sEcho','');
            $this->input['start'] = Input::get('iDisplayStart');
            $this->input['length'] = Input::get('iDisplayLength');
            $this->input['search'] = array(
                'value' => Input::get('sSearch',''),
                'regex' => Input::get('bRegex',''),
            );
            $this->input['_'] = Input::get('_','');

            $columns = explode(',',Input::get('sColumns',''));
            $this->input['columns'] = array();
            for($i=0;$i<Input::get('iColumns',0);$i++) {
                $arr = array();
                $arr['name'] = isset($columns[$i]) ? $columns[$i] : '';
                $arr['searchable'] = Input::get('bSearchable_'.$i,'');
                $arr['search'] = array();
                $arr['search']['value'] = Input::get('sSearch_'.$i,'');
                $arr['search']['regex'] = Input::get('bRegex_'.$i,'');
                $arr['orderable'] = Input::get('bSortable_'.$i,'');
                $this->input['columns'][] = $arr;
            }
            
            $this->input['order'] = array();
            for($i=0;$i<Input::get('iSortingCols',0);$i++) {
                $arr = array();
                $arr['column'] = Input::get('iSortCol_'.$i,'');
                $arr['dir'] = Input::get('sSortDir_'.$i,'');
                $this->input['order'][] = $arr;
            }
        }

        return $this;
    }
    
    /**
     * Gets query and returns instance of class
     *
     * @return null
     */
    public static function of($query)
    {
        $ins = new static;
        $ins->save_query($query);
        return $ins;
    }

    /**
     * Organizes works
     *
     * @return null
     */
    public function make($mDataSupport=false,$raw=false)
    {
        $this->mDataSupport = $mDataSupport;
        $this->create_last_columns();
        $this->init();
        $this->get_result();
        $this->init_columns();
        $this->regulate_array();

        return $this->output($raw);
    }

    /**
     * Gets results from prepared query
     *
     * @return null
     */
    protected function get_result()
    {
        if($this->query_type == 'eloquent')
        {
            $this->result_object = $this->query->get();
            $this->result_array = $this->result_object->toArray();
        }
        else
        {
            $this->result_object = $this->query->get();
            $this->result_array = array_map(function($object) {
                return (array) $object;
            }, $this->result_object);
        }
    }

    /**
     * Prepares variables according to Datatables parameters
     *
     * @return null
     */
    protected function init()
    {
        $this->count('count_all'); //Total records
        $this->filtering();
        $this->count('display_all'); // Filtered records
        $this->paging();
        $this->ordering();
    }

    /**
     * Adds extra columns to extra_columns
     *
     * @return $this
     */
    public function add_column($name,$content,$order = false)
    {
        $this->sColumns[] = $name;

        $this->extra_columns[] = array('name' => $name, 'content' => $content, 'order' => $order);
        return $this;
    }

    /**
     * Adds column names to edit_columns
     *
     * @return $this
     */
    public function edit_column($name,$content)
    {
        $this->edit_columns[] = array('name' => $name, 'content' => $content);
        return $this;
    }


    /**
     * Adds excess columns to excess_columns
     *
     * @return $this
     */
    public function remove_column()
    {
        $names = func_get_args();
        $this->excess_columns = array_merge($this->excess_columns,$names);
        return $this;
    }

    /**
    * Adds column filter to filter_columns
    *
    * @return $this
    */
    public function filter_column($column,$method)
    {
        $params = func_get_args();
        $this->filter_columns[$column] = array('method' => $method, 'parameters' => array_splice($params,2) );
        return $this;
    }


    /**
     * Sets the DataTables index column (as used to set, e.g., id of the <tr> tags) to the named column
     *
     * @param $name
     * @return $this
     */
    public function set_index_column($name) {
        $this->index_column = $name;
        return $this;
    }
    
    /**
     * Sets DT_RowClass template
     * result: <tr class="output_from_your_template">
     *
     * @param $content
     * @return $this
     */
    public function set_row_class($content) {
        $this->row_class_tmpl = $content;
        return $this;
    }    

    /**
     * Sets DT_RowData template for given attribute name
     * result: Datatables invoking $(row).data(name, output_from_your_template)
     *
     * @param $content
     * @return $this
     */
    public function set_row_data($name, $content) {
        $this->row_data_tmpls[$name] = $content;
        return $this;
    }    
    
    /**
     * Saves given query and determines its type
     *
     * @return null
     */
    protected function save_query($query)
    {
        $this->query = $query;
        $this->query_type = $query instanceof \Illuminate\Database\Query\Builder ? 'fluent' : 'eloquent';
        $this->columns = $this->query_type == 'eloquent' ? $this->query->getQuery()->columns : $this->query->columns;
    }

    /**
     * Places extra columns
     *
     * @return null
     */
    protected function init_columns()
    {
        foreach ($this->result_array as $rkey => &$rvalue) {

            foreach ($this->extra_columns as $key => $value) {

                if (is_string($value['content'])):
                $value['content'] = $this->blader($value['content'], $rvalue);
                elseif (is_callable($value['content'])):
                $value['content'] = $value['content']($this->result_object[$rkey]);
                endif;

                $rvalue = $this->include_in_array($value,$rvalue);
            }

            foreach ($this->edit_columns as $key => $value) {

                if (is_string($value['content'])):
                $value['content'] = $this->blader($value['content'], $rvalue);
                elseif (is_callable($value['content'])):
                $value['content'] = $value['content']($this->result_object[$rkey]);
                endif;

                $rvalue[$value['name']] = $value['content'];

            }
        }
    }

    /**
     * Converts result_array number indexed array and consider excess columns
     *
     * @return null
     */
    protected function regulate_array()
    {
        if($this->mDataSupport){
            $this->result_array_r = $this->result_array;
        }else{
            foreach ($this->result_array as $key => $value) {
                foreach ($this->excess_columns as $evalue) {
                    unset($value[$evalue]);
                }

                $row = array_values($value);
                if ($this->index_column) {
                    if (!array_key_exists($this->index_column, $value)) {
                        throw new \Exception('Index column set to non-existent column "' . $this->index_column . '"');
                    }
                    $row['DT_RowId'] = $value[$this->index_column];
                }
                
                if($this->row_class_tmpl!==null) {
                    $content = '';
                    if (is_string($this->row_class_tmpl)) {
                        $content = $this->blader($this->row_class_tmpl, $value);
                    } else if(is_callable($this->row_class_tmpl)) {
                        $content = $this->row_class_tmpl($this->result_object[$key]);
                    }
                    $row['DT_RowClass'] = $content;
                }

                if(count($this->row_data_tmpls)) {
                    $row['DT_RowData'] = array();
                    foreach($this->row_data_tmpls as $tkey => $tvalue) {
                        $content = '';
                        if (is_string($tvalue)) {
                            $content = $this->blader($tvalue, $value);
                        } else if(is_callable($tvalue)) {
                            $content = $tvalue($this->result_object[$key]);
                        }
                        $row['DT_RowData'][$tkey] = $content;
                    }
                }
                
                $this->result_array_r[] = $row;
            }
        }
    }

    /**
     * 
     * Inject searched string into $1 in filter_column parameters
     * 
     * @param array $params
     * @return array
     */
    private function inject_variable(&$params,$value)
    {
        if (is_array($params))
        {
            foreach($params as $key => $param)
            {
                $params[$key] = $this->inject_variable($param, $value);
            }
            
        } elseif ($params instanceof \Illuminate\Database\Query\Expression)
        {
            $params = DB::raw(str_replace('$1',$value,$params));
            
        } elseif (is_callable($params))
        {
            $params = $params($value);
            
        } elseif (is_string($params))
        {
            $params = str_replace('$1',$value,$params);
        }
        
        return $params;
    }

    /**
     * Creates an array which contains published last columns in sql with their index
     *
     * @return null
     */
    protected function create_last_columns()
    {
        $extra_columns_indexes = array();
        $last_columns = array();
        $count = 0;

        foreach ($this->extra_columns as $key => $value) {
            if($value['order'] === false) continue;
            $extra_columns_indexes[] = $value['order'];
        }

        for ($i=0,$c=count($this->columns);$i<$c;$i++) {

            if(in_array($this->getColumnName($this->columns[$i]), $this->excess_columns))
            {
                continue;
            }

            if(in_array($count, $extra_columns_indexes))
            {
                $count++; $i--; continue;
            }

            // previous regex #^(\S*?)\s+as\s+(\S*?)$# prevented subqueries and functions from being detected as alias
            preg_match('#\s+as\s+(\S*?)$#si',$this->columns[$i],$matches);
            $last_columns[$count] = empty($matches) ? $this->columns[$i] : $matches[1];
            $count++;
        }

        $this->last_columns = $last_columns;
    }

    /**
     * Parses and compiles strings by using Blade Template System
     *
     * @return string
     */
    protected function blader($str,$data = array())
    {
        $empty_filesystem_instance = new Filesystem;
        $blade = new BladeCompiler($empty_filesystem_instance,'datatables');
        $parsed_string = $blade->compileString($str);

        ob_start() and extract($data, EXTR_SKIP);

        try
        {
            eval('?>'.$parsed_string);
        }

        catch (\Exception $e)
        {
            ob_end_clean(); throw $e;
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
    protected function include_in_array($item,$array)
    {
        if($item['order'] === false)
        {
            return array_merge($array,array($item['name']=>$item['content']));
        }
        else
        {
            $count = 0;
            $last = $array;
            $first = array();
            
            if(count($array) <= $item['order'])
            {
		        return $array + array($item['name']=>$item['content']);
            }
	    
            foreach ($array as $key => $value) {
                if($count == $item['order'])
                {
                    return array_merge($first,array($item['name']=>$item['content']),$last);
                }

                unset($last[$key]);
                $first[$key] = $value;

                $count++;
            }
        }
    }

    /**
     * Datatable paging
     *
     * @return null
     */
    protected function paging()
    {
        if(!is_null($this->input['start']) && !is_null($this->input['length']))
        {
            $this->query->skip($this->input['start'])->take((int)$this->input['length']>0?$this->input['length']:10);
        }
    }

    /**
     * Datatable ordering
     *
     * @return null
     */
    protected function ordering()
    {
        if(array_key_exists('order', $this->input) && count($this->input['order'])>0)
        {
            $columns = $this->clean_columns( $this->last_columns );

            for ( $i=0, $c=count($this->input['order']); $i<$c ; $i++ )
            {
                $order_col = (int)$this->input['order'][$i]['column'];
                if (isset($columns[$order_col])) {
                    if ( $this->input['columns'][$order_col]['orderable'] == "true" )
                    {
                        $this->query->orderBy($columns[$order_col],$this->input['order'][$i]['dir']);
                    }
                }
            }

        }
    }

    /**
     * @param array $cols
     * @param bool $use_alias weather to get the column/function or the alias
     * @return array
     */
    protected function clean_columns( $cols, $use_alias = true)
    {
        $return = array();
        foreach ( $cols as $i=> $col )
        {
            preg_match('#^(.*?)\s+as\s+(\S*?)\s*$#si',$col,$matches);
            $return[$i] = empty($matches) ? ($use_alias?$this->getColumnName($col):$col) : $matches[$use_alias?2:1];
        }

        return $return;
    }

    /**
     * Datatable filtering
     *
     * @return null
     */
    protected function filtering()
    {
        
        // copy of $this->columns without columns removed by remove_column
        $columns_copy = $this->columns;
        for ($i=0,$c=count($columns_copy);$i<$c;$i++)
        {
            if(in_array($this->getColumnName($columns_copy[$i]), $this->excess_columns))
            {
                unset($columns_copy[$i]);
            }
        }
        $columns_copy = array_values($columns_copy);

        // copy of $this->columns cleaned for database queries
        $columns_clean = $this->clean_columns( $columns_copy, false );
        $columns_copy = $this->clean_columns( $columns_copy, true );

        // global search
        if ($this->input['search']['value'] != '')
        {
            $_this = $this;

            $this->query->where(function($query) use (&$_this, $columns_copy, $columns_clean) {
                
                $db_prefix = $_this->database_prefix();
 
               for ($i=0,$c=count($_this->input['columns']);$i<$c;$i++)
                {
                    if (isset($columns_copy[$i]) && $_this->input['columns'][$i]['searchable'] == "true")
                    {
                        // if filter column exists for this columns then use user defined method
                        if (isset($_this->filter_columns[$columns_copy[$i]]))
                        {
                            // check if "or" equivalent exists for given function
                            // and if the number of parameters given is not excess 
                            // than call the "or" equivalent
                            
                            $method_name = 'or' . ucfirst($_this->filter_columns[$columns_copy[$i]]['method']);
                            
                            if ( method_exists($query->getQuery(), $method_name) && count($_this->filter_columns[$columns_copy[$i]]['parameters']) <= with(new \ReflectionMethod($query->getQuery(),$method_name))->getNumberOfParameters() )
                            {
                                call_user_func_array(
                                    array(
                                        $query,
                                        $method_name
                                    ),
                                    $_this->inject_variable(
                                        $_this->filter_columns[$columns_copy[$i]]['parameters'],
                                        $_this->input['search']['value']
                                    )
                                );
                            }
                        } else
                        // otherwise do simple LIKE search                    
                        {
                        
                            $keyword = '%'.$_this->input['search']['value'].'%';
                        
                            if(Config::get('datatables::search.use_wildcards', false)) {
                                $keyword = $_this->wildcard_like_string($_this->input['search']['value']);
                            }
                        
                            // Check if the database driver is PostgreSQL
                            // If it is, cast the current column to TEXT datatype
                            $cast_begin = null;
                            $cast_end = null;
                            if( DB::getDriverName() === 'pgsql') {
                                $cast_begin = "CAST(";
                                $cast_end = " as TEXT)";
                            }
                        
                            $column = $db_prefix . $columns_clean[$i];
                        
                            if(Config::get('datatables::search.case_insensitive', false)) {
                                $query->orwhere(DB::raw('LOWER('.$cast_begin.$column.$cast_end.')'), 'LIKE', strtolower($keyword));
                            } else {
                                $query->orwhere(DB::raw($cast_begin.$column.$cast_end), 'LIKE', $keyword);
                            }
                        }
                    }
                }
            });

        }

        $db_prefix = $this->database_prefix();
        
        // column search
        for ($i=0,$c=count($this->input['columns']);$i<$c;$i++)
        {
            if (isset($columns_copy[$i]) && $this->input['columns'][$i]['orderable'] == "true" && $this->input['columns'][$i]['search']['value'] != '')
            {
                // if filter column exists for this columns then use user defined method
                if (isset($this->filter_columns[$columns_copy[$i]]))
                {
                    call_user_func_array(
                        array(
                            $this->query,
                            $this->filter_columns[$columns_copy[$i]]['method']
                        ),
                            $this->inject_variable(
                            $this->filter_columns[$columns_copy[$i]]['parameters'],
                            $this->input['columns'][$i]['search']['value']
                        )
                    );
                    
                } else
                // otherwise do simple LIKE search
                {                        
                    $keyword = '%'.$this->input['columns'][$i]['search']['value'].'%';
                    
                    if(Config::get('datatables::search.use_wildcards', false)) {
                        $keyword = $this->wildcard_like_string($this->input['columns'][$i]['search']['value']);
                    }
                    
                    if(Config::get('datatables::search.case_insensitive', false)) {
                        $column = $db_prefix . $columns_clean[$i];
                        $this->query->where(DB::raw('LOWER('.$column.')'),'LIKE', strtolower($keyword));
                    } else {
                        $col = strstr($columns_clean[$i],'(')?DB::raw($columns_clean[$i]):$columns_clean[$i];
                        $this->query->where($col, 'LIKE', $keyword);
                    }
                }
            }
        }
    }

    /**
     * Adds % wildcards to the given string
     *
     * @return string
     */
    public function wildcard_like_string($str, $lowercase = true) {
        $wild = '%';
        $length = strlen($str);
        if($length) {
            for ($i=0; $i < $length; $i++) {
                $wild .= $str[$i].'%';
            }
        }
        if($lowercase) $wild = strtolower($wild);
        return $wild;
    }


    /**
     * Returns current database prefix
     *
     * @return string
     */
    public function database_prefix() {
        return Config::get('database.connections.'.Config::get('database.default').'.prefix', '');
    }

    /**
     * Counts current query
     * @param string $count variable to store to 'count_all' for iTotalRecords, 'display_all' for iTotalDisplayRecords
     * @return null
     */
     protected function count($count  = 'count_all')
     {   

        //Get columns to temp var.
        if($this->query_type == 'eloquent') {
            $query = $this->query->getQuery();
            $connection = $this->query->getModel()->getConnection()->getName();
        }
        else {
            $query = $this->query;
            $connection = $query->getConnection()->getName();
        }

        // if its a normal query ( no union ) replace the slect with static text to improve performance
        $myQuery = clone $query;
        if( !preg_match( '/UNION/i', $myQuery->toSql() ) ){
            $myQuery->select( DB::raw("'1' as row") );     
            
            // if query has "having" clause add select columns
            if ($myQuery->havings) {
                foreach($myQuery->havings as $having) {
                    if (isset($having['column'])) {
                        $myQuery->addSelect($having['column']);
                    } else {
                        // search filter_columns for query string to get column name from an array key
                        $found = false;
                        foreach($this->filter_columns as $column => $val) {
                            if ($val['parameters'][0] == $having['sql'])
                            {
                                $found = $column;
                                break;
                            }
                        }
                        // then correct it if it's an alias and add to columns
                        if ($found!==false) {
                            foreach($this->columns as $val) {
                                $arr = explode(' as ',$val);
                                if (isset($arr[1]) && $arr[1]==$found)
                                {
                                    $found = $arr[0];
                                    break;
                                }
                            }
                            $myQuery->addSelect($found);
                        }
                    }
                }
            }
        }

        $this->$count = DB::connection($connection)
        ->table(DB::raw('('.$myQuery->toSql().') AS count_row_table'))
        ->setBindings($myQuery->getBindings())->count();

    }

    /**
     * Returns column name from <table>.<column>
     *
     * @return null
     */
    protected function getColumnName($str)
    {

        preg_match('#^(\S*?)\s+as\s+(\S*?)$#si',$str,$matches);

        if(!empty($matches))
        {
            return $matches[2];
        }
        elseif(strpos($str,'.'))
        {
            $array = explode('.', $str);
            return array_pop($array);
        }

        return $str;
    }

    /**
     * Prints output
     *
     * @return null
     */
    protected function output($raw=false)
    {
        if (Input::has('draw')) {
            
            $output = array(
                    "draw" => intval($this->input['draw']),
                    "recordsTotal" => $this->count_all,
                    "recordsFiltered" => $this->display_all,
                    "data" => $this->result_array_r,
            );
            
        } else {
            
            $sColumns = array_merge_recursive($this->columns,$this->sColumns);

            $output = array(
                    "sEcho" => intval($this->input['draw']),
                    "iTotalRecords" => $this->count_all,
                    "iTotalDisplayRecords" => $this->display_all,
                    "aaData" => $this->result_array_r,
                    "sColumns" => $sColumns
            );

        }
        if(Config::get('app.debug', false)) {
            $output['aQueries'] = DB::getQueryLog();
        }
        if($raw) {
            return $output;
        }
        else {
            return Response::json($output);
        }
    }
    
    /**
     * PR #93
     * camelCase to snake_case magic method
     */
    public function __call($name, $arguments)
    {
        $name = strtolower(preg_replace('/([^A-Z])([A-Z])/', "$1_$2", $name));
        if (method_exists($this, $name)) {
            return call_user_func_array(array($this, $name),$arguments);
        } else {
            trigger_error('Call to undefined method '.__CLASS__.'::'.$name.'()', E_USER_ERROR);
        }
    }
}
