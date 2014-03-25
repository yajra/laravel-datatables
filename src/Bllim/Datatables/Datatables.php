<?php namespace Bllim\Datatables;

/**
 * Laravel Datatable Bundle
 *
 * This bundle is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net)
 *
 * @package Laravel
 * @category Bundle
 * @version 1.3.3
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
    protected $sColumns = array();

    public $columns = array();
    public $last_columns = array();

    protected $count_all = 0;
    protected $display_all = 0;

    protected $result_object;
    protected $result_array = array();
    protected $result_array_r = array();

    protected $mDataSupport;


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
    private function get_result()
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
    private function init()
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
     * Saves given query and determines its type
     *
     * @return null
     */
    private function save_query($query)
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
    private function init_columns()
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
    private function regulate_array()
    {
        if($this->mDataSupport){
            $this->result_array_r = $this->result_array;
        }else{
            foreach ($this->result_array as $key => $value) {
                foreach ($this->excess_columns as $evalue) {
                    unset($value[$evalue]);
                }

                $this->result_array_r[] = array_values($value);
            }
        }
    }

    /**
     * Creates an array which contains published last columns in sql with their index
     *
     * @return null
     */
    private function create_last_columns()
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
    private function blader($str,$data = array())
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
    private function include_in_array($item,$array)
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
    private function paging()
    {
        if(!is_null(Input::get('iDisplayStart')) && Input::get('iDisplayLength') != -1)
        {
            $this->query->skip(Input::get('iDisplayStart'))->take(Input::get('iDisplayLength',10));
        }
    }

    /**
     * Datatable ordering
     *
     * @return null
     */
    private function ordering()
    {


        if(!is_null(Input::get('iSortCol_0')))
        {
            $columns = $this->clean_columns( $this->last_columns );

            for ( $i=0, $c=intval(Input::get('iSortingCols')); $i<$c ; $i++ )
            {
                if ( Input::get('bSortable_'.intval(Input::get('iSortCol_'.$i))) == "true" )
                {
                    if(isset($columns[intval(Input::get('iSortCol_'.$i))]))
                        $this->query->orderBy($columns[intval(Input::get('iSortCol_'.$i))],Input::get('sSortDir_'.$i));
                }
            }

        }
    }

    /**
     * @param array $cols
     * @param bool $use_alias weather to get the column/function or the alias
     * @return array
     */
    private function clean_columns( $cols, $use_alias = true)
    {
        $return = array();
        foreach ( $cols as $i=> $col )
        {
            preg_match('#^(.*?)\s+as\s+(\S*?)$#si',$col,$matches);
            $return[$i] = empty($matches) ? $col : $matches[$use_alias?2:1];
        }

        return $return;
    }

    /**
     * Datatable filtering
     *
     * @return null
     */
    private function filtering()
    {
        $columns = $this->clean_columns( $this->columns, false );

        if (Input::get('sSearch','') != '')
        {
            $copy_this = $this;
            $copy_this->columns = $columns;

            $this->query->where(function($query) use ($copy_this) {

                $db_prefix = $copy_this->database_prefix();



                for ($i=0,$c=count($copy_this->columns);$i<$c;$i++)
                {
                    if (Input::get('bSearchable_'.$i) == "true")
                    {
                        $column = $copy_this->columns[$i];

                        if (stripos($column, ' AS ') !== false){
                            $column = substr($column, stripos($column, ' AS ')+4);
                        }

                        $keyword = '%'.Input::get('sSearch').'%';

                        if(Config::get('datatables.search.use_wildcards', false)) {
                            $keyword = $copy_this->wildcard_like_string(Input::get('sSearch'));
                        }

                        // Check if the database driver is PostgreSQL
                        // If it is, cast the current column to TEXT datatype
                        $cast_begin = null;
                        $cast_end = null;
                        if( DB::getDriverName() === 'pgsql') {
                            $cast_begin = "CAST(";
                            $cast_end = " as TEXT)";
                        }

                        $column = $db_prefix . $column;
                        if(Config::get('datatables.search.case_insensitive', false)) {
                            $query->orwhere(DB::raw('LOWER('.$cast_begin.$column.$cast_end.')'), 'LIKE', strtolower($keyword));
                        } else {
                            $query->orwhere(DB::raw($cast_begin.$column.$cast_end), 'LIKE', $keyword);
                        }
                    }
                }
            });

        }

        $db_prefix = $this->database_prefix();

        for ($i=0,$c=count($columns);$i<$c;$i++)
        {
            if (Input::get('bSearchable_'.$i) == "true" && Input::get('sSearch_'.$i) != '')
            {
                $keyword = '%'.Input::get('sSearch_'.$i).'%';

                if(Config::get('datatables.search.use_wildcards', false)) {
                    $keyword = $copy_this->wildcard_like_string(Input::get('sSearch_'.$i));
                }

                if(Config::get('datatables.search.case_insensitive', false)) {
                    $column = $db_prefix . $columns[$i];
                    $this->query->where(DB::raw('LOWER('.$column.')'),'LIKE', strtolower($keyword));
                } else {
                    $col = strstr($columns[$i],'(')?DB::raw($columns[$i]):$columns[$i];
                    $this->query->where($col, 'LIKE', $keyword);
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
     private function count($count  = 'count_all')
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
        	$myQuery->select( DB::Raw("'1' as row") );	        	
        }


        $this->$count = DB::connection($connection)
        ->table(DB::raw('('.$myQuery->toSql().') AS count_row_table'))
        ->setBindings($myQuery->getBindings())->remember(1)->count();

     }

    /**
     * Returns column name from <table>.<column>
     *
     * @return null
     */
    private function getColumnName($str)
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
    private function output($raw=false)
    {
        $sColumns = array_merge_recursive($this->columns,$this->sColumns);

        $output = array(
                "sEcho" => intval(Input::get('sEcho')),
                "iTotalRecords" => $this->count_all,
                "iTotalDisplayRecords" => $this->display_all,
                "aaData" => $this->result_array_r,
                "sColumns" => $sColumns
        );

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
}
