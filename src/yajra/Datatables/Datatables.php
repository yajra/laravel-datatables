<?php namespace yajra\Datatables;

/**
* Laravel Datatables Package
*
* This Package is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net)
*
* @package    Laravel
* @category   Package
* @version    2.0
* @author     Arjay Angeles <aqangeles@gmail.com>
*/

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request as Input;

class Datatables
{
	public 		$connection;
	public 		$query;
	public 		$input;
	protected	$query_type;

	protected 	$extra_columns = array();
	protected 	$excess_columns = array();
	protected 	$edit_columns = array();
	protected 	$sColumns = array();

	public 		$columns = array();
	public 		$last_columns = array();

	protected	$count_all = 0;
	protected	$display_all = 0;

	protected	$result_object;
	protected	$result_array = array();
	protected	$result_array_r = array();

	protected 	$mDataSupport;


	public function __construct()
	{
		$this->input = new Input($_GET, $_POST);
	}

	/**
	 *	Gets query and returns instance of class
	 *	@return null
	 */
	public static function of($query)
	{
		$ins = new static;
		$ins->saveQuery($query);

		//Get columns to temp var.
		if($ins->query_type == 'eloquent') {
			$ins->connection = $ins->query->getModel()->getConnection();
		}
		else {
			$ins->connection = $query->getConnection();
		}

		return $ins;
	}

	/**
	 *	Organizes works
	 *
	 *	@return null
	 */
	public function make($mDataSupport=false)
	{
		$this->mDataSupport = $mDataSupport;
		$this->createLastColumn();
		$this->init();
		$this->getResult();
		$this->initColumns();
		$this->regulateArray();

		return $this->output();
	}


	/**
	 *	Gets results from prepared query
	 *	@return null
	 */
	private function getResult()
	{
		$this->result_object = $this->query->get();

		if($this->query_type == 'eloquent')
		{
			$this->result_array = $this->result_object->toArray();
		}
		else
		{
			$this->result_array = array_map(function($object) { return (array) $object; }, $this->result_object);
		}
	}

	/**
	 *	Prepares variables according to Datatables parameters
	 *	@return null
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
	 * alias for add_column to follow Laravel's coding style
	 * @param string  $name
	 * @param string  $content
	 * @param int $order
	 * @return Datatables
	 */
	public function addColumn($name, $content, $order = false) { return $this->add_column($name, $content, $order = false); }

	/**
	 * Add column in collection
	 * @param string  $name
	 * @param string  $content
	 * @param int $order
	 * @return Datatables
	 */
	public function add_column($name, $content, $order = false)
	{
		$this->sColumns[] = $name;

		$this->extra_columns[] = array('name' => $name, 'content' => $content, 'order' => $order);
		return $this;
	}

	/**
	 * alias for edit_column
	 * @param  string    $name
	 * @param  string $content
	 * @return Datatables
	 */
	public function editColumn($name, $content) { return $this->edit_column($name, $content); }

	/**
	 * edit column's content
	 * @param  string    $name
	 * @param  string $content
	 * @return Datatables
	 */
	public function edit_column($name, $content)
	{
		$this->edit_columns[] = array('name' => $name, 'content' => $content);
		return $this;
	}

	/**
	 * alias for remove_column
	 * @return Datatables
	 */
	public function removeColumn() { return $this->remove_column(); }

	/**
	 * remove column from collection
	 * @return Datatables
	 */
	public function remove_column()
	{
		$names = func_get_args();
		$this->excess_columns = array_merge($this->excess_columns, $names);
		return $this;
	}


	/**
	 *	Saves given query and determines its type
	 *	@return null
	 */
	private function saveQuery($query)
	{
		$this->query = $query;
		$this->query_type = $query instanceof \Illuminate\Database\Query\Builder ? 'fluent' : 'eloquent';
		$this->columns = $this->query_type == 'eloquent' ? $this->query->getQuery()->columns : $this->query->columns;
	}

	/**
	 *	Places extra columns
	 *	@return null
	 */
	private function initColumns()
	{
		foreach ($this->result_array as $rkey => &$rvalue) {

			foreach ($this->extra_columns as $key => $value) {

				if (is_string($value['content'])):
					$value['content'] = $this->blader($value['content'], $rvalue);
				elseif (is_callable($value['content'])):
					$value['content'] = $value['content']($this->result_object[$rkey]);
				endif;

				$rvalue = $this->includeInArray($value,$rvalue);
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
	 *	Converts result_array number indexed array and consider excess columns
	 *	@return null
	 */
	private function regulateArray()
	{
		if($this->mDataSupport){
			$this->result_array_r = $this->result_array;
		} else {
			foreach ($this->result_array as $key => $value) {
				foreach ($this->excess_columns as $evalue) {
					unset($value[$evalue]);
				}

				$this->result_array_r[] = array_values($value);
			}
		}
	}


	/**
	 *	Creates an array which contains published last columns in sql with their index
	 *	@return null
	 */
	private function createLastColumn()
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
	 *	Parses and compiles strings by using Blade Template System
	 *	@return string
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
	 *	Places item of extra columns into result_array by care of their order
	 *	@return null
	 */
	private function includeInArray($item,$array)
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
	 *	Datatables paging
	 *	@return null
	 */
	private function paging()
	{
		if(!is_null($this->input->get('iDisplayStart')) && $this->input->get('iDisplayLength') != -1)
		{
			$this->query->skip($this->input->get('iDisplayStart'))->take($this->input->get('iDisplayLength',10));
		}
	}

	/**
	 *	Datatable ordering
	 *	@return null
	 */
	private function ordering()
	{
		if(!is_null($this->input->get('iSortCol_0')))
		{
			$columns = $this->cleanColumns( $this->last_columns );

			for ( $i=0, $c=intval($this->input->get('iSortingCols')); $i<$c ; $i++ )
			{
				if ( $this->input->get('bSortable_'.intval($this->input->get('iSortCol_'.$i))) == "true" )
				{
					if(isset($columns[intval($this->input->get('iSortCol_'.$i))]))
					$this->query->orderBy($columns[intval($this->input->get('iSortCol_'.$i))],$this->input->get('sSortDir_'.$i));
				}
			}
		}
	}

	/**
	 * clean columns name
	 * @param array $cols
	 * @return array
	 */
	private function cleanColumns( $cols, $use_alias  = true )
	{
		$return = array();
		foreach ( $cols as  $i=> $col )
		{
			preg_match('#^(.*?)\s+as\s+(\S*?)$#si',$col,$matches);
			$return[$i] = empty($matches) ? $col : $matches[$use_alias?2:1];
		}

		return $return;
	}

	/**
	 *	Datatable filtering
	 *	@return null
	 */
	private function filtering()
	{
		$columns = $this->cleanColumns( $this->columns, false );
		$db_prefix = $this->getDatabasePrefix();
		$input = $this->input;
		$connection = $this->connection;

		if ($this->input->get('sSearch','') != '')
		{
			$this->query->where(function($query) use ($columns, $db_prefix, $input, $connection) {

				for ($i=0,$c=count($columns);$i<$c;$i++)
				{
					if ($input->get('bSearchable_'.$i) == "true")
					{
						$column = $columns[$i];

						if (stripos($column, ' AS ') !== false){
							$column = substr($column, stripos($column, ' AS ')+4);
						}

						$keyword = '%'.$input->get('sSearch').'%';

						if(Config::get('datatables.search.use_wildcards', false)) {
							$keyword = $copy_this->wildcardLikeString($input->get('sSearch'));
						}

						// Check if the database driver is PostgreSQL
						// If it is, cast the current column to TEXT datatype
						$cast_begin = null;
						$cast_end = null;
						if( $connection->getDriverName() === 'pgsql') {
							$cast_begin = "CAST(";
							$cast_end = " as TEXT)";
						}

						$column = $db_prefix . $column;
						if(Config::get('datatables.search.case_insensitive', false)) {
							$query->orwhere($connection->raw('LOWER('.$cast_begin.$column.$cast_end.')'), 'LIKE', strtolower($keyword));
						} else {
							$query->orwhere($connection->raw($cast_begin.$column.$cast_end), 'LIKE', $keyword);
						}
					}
				}
			});

		}

		$db_prefix = $this->getDatabasePrefix();

		for ($i=0,$c=count($columns);$i<$c;$i++)
		{
			if ($this->input->get('bSearchable_'.$i) == "true" && $this->input->get('sSearch_'.$i) != '')
			{
				$keyword = '%'.$this->input->get('sSearch_'.$i).'%';

				if(Config::get('datatables.search.use_wildcards', false)) {
					$keyword = $copy_this->wildcardLikeString($this->input->get('sSearch_'.$i));
				}

				if(Config::get('datatables.search.case_insensitive', false)) {
					$column = $db_prefix . $columns[$i];
					$this->query->where($this->connection->raw('LOWER('.$column.')'),'LIKE', strtolower($keyword));
				} else {
					$col = strstr($columns[$i],'(')?$this->connection->raw($columns[$i]):$columns[$i];
					$this->query->where($col, 'LIKE', $keyword);
				}
			}
		}
	}

	/**
	 *  Adds % wildcards to the given string
	 *  @return string
	 */
	public function wildcardLikeString($str, $lowercase = true) {
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
	 *  Returns current database prefix
	 *  @return string
	 */
	public function getDatabasePrefix() {
	    return Config::get('database.connections.'.Config::get('database.default').'.prefix', '');
	}

	/**
	 *	Counts current query
	 *  @param string $count variable to store to 'count_all' for iTotalRecords, 'display_all' for iTotalDisplayRecords
	 *	@return null
	 */
	private function count($count  = 'count_all')
	{
		// Get columns to temp var.
		if($this->query_type == 'eloquent') {
			$query = $this->query->getQuery();
		}
		else {
			$query = $this->query;
		}

        // if its a normal query ( no union ) replace the select with static text to improve performance
		$myQuery = clone $query;
		if( !preg_match( '/UNION/i', strtoupper($myQuery->toSql()) ) ){
			$myQuery->select( $this->connection->Raw("'1' as row_count") );
		}

		$this->$count = $this->connection->table($this->connection->raw('('.$myQuery->toSql().') count_row_table'))
				->setBindings($myQuery->getBindings())->remember(1)->count();
	}

	/**
	 * get column name from string
	 * @param  string $str
	 * @return string
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
	 * Render json response
	 * @return JsonReponse
	 */
	private function output()
	{
		$sColumns = array_merge_recursive($this->columns, $this->sColumns);

		$output = array(
			"sEcho" => intval($this->input->get('sEcho')),
			"iTotalRecords" => $this->count_all,
			"iTotalDisplayRecords" => $this->display_all,
			"aaData" => $this->result_array_r,
			"sColumns" => $sColumns
		);

		if(Config::get('app.debug', false)) {
			$output['aQueries'] = $this->connection->getQueryLog();
		}
		return Response::json($output);
	}

}
