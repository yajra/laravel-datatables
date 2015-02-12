<?php namespace yajra\Datatables;

/**
* Laravel Datatables Package
*
* This Package is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net)
*
* @package    Laravel
* @category   Package
* @author     Arjay Angeles <aqangeles@gmail.com>
*/

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Closure;

class Datatables {
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

	protected	$totalRecords = 0;
	protected	$filteredRecords = 0;

	protected	$result_object;
	protected	$result_array = array();
	protected	$result_array_r = array();

	protected 	$mDataSupport;
	protected 	$autoFilter = true;

	protected 	$new_version = false;

	public function __construct()
	{
		$request = new Request($_GET, $_POST);

		if ($this->new_version = $request->has('draw'))
		{
            // version 1.10+
			$this->input = $request->input();
		}
		else
		{
            // version < 1.10
			$this->input['draw'] = $request->input('sEcho','');
			$this->input['start'] = $request->input('iDisplayStart');
			$this->input['length'] = $request->input('iDisplayLength');
			$this->input['search'] = array(
				'value' => $request->input('sSearch',''),
				'regex' => $request->input('bRegex',''),
				);
			$this->input['_'] = $request->input('_','');

			$columns = explode(',',$request->input('sColumns',''));
			$this->input['columns'] = array();
			for ($i=0;$i<$request->input('iColumns',0);$i++)
			{
				$arr = array();
				$arr['name'] = isset($columns[$i]) ? $columns[$i] : '';
				$arr['searchable'] = $request->input('bSearchable_'.$i,'');
				$arr['search'] = array();
				$arr['search']['value'] = $request->input('sSearch_'.$i,'');
				$arr['search']['regex'] = $request->input('bRegex_'.$i,'');
				$arr['orderable'] = $request->input('bSortable_'.$i,'');
				$this->input['columns'][] = $arr;
			}

			$this->input['order'] = array();
			for ($i=0; $i<$request->input('iSortingCols',0); $i++)
			{
				$arr = array();
				$arr['column'] = $request->input('iSortCol_'.$i,'');
				$arr['dir'] = $request->input('sSortDir_'.$i,'');
				$this->input['order'][] = $arr;
			}
		}
	}


	/**
	 *	Gets query and returns instance of class
	 *	@return null
	 */
	public static function of($query)
	{
		$ins = new static;
		$ins->saveQuery($query);

		// set connection and query variable
		if ($ins->query_type == 'eloquent')
		{
			$ins->connection = $ins->query->getModel()->getConnection();
			$ins->query = $query;
		}
		else
		{
			$ins->connection = $query->getConnection();
			$ins->query = $query;
		}

		$ins->createLastColumn();

		$ins->getTotalRecords(); //Total records

		return $ins;
	}


	/**
	 *	Organizes works
	 *
	 *	@return null
	 */
	public function make($mDataSupport=false)
	{
		// set mData support flag
		$this->mDataSupport = $mDataSupport;

		// check if auto filtering was overidden
		if ($this->autoFilter)
		{
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
	 *	Gets results from prepared query
	 *	@return null
	 */
	private function getResult()
	{
		$this->result_object = $this->query->get();
		if ($this->query_type == 'eloquent')
		{
			$this->result_array = array_map(function($object) { return (array) $object; }, $this->result_object->toArray());
		}
		else
		{
			$this->result_array = array_map(function($object) { return (array) $object; }, $this->result_object);
		}
	}


	/**
	 * alias for addColumn for backward compatibility
	 * @param string  $name
	 * @param string  $content
	 * @param int $order
	 * @return Datatables
	 */
	public function add_column($name, $content, $order = false)
	{
		return $this->addColumn($name, $content, $order = false);
	}


	/**
	 * Add column in collection
	 * @param string  $name
	 * @param string  $content
	 * @param int $order
	 * @return Datatables
	 */
	public function addColumn($name, $content, $order = false)
	{
		$this->sColumns[] = $name;

		$this->extra_columns[] = array('name' => $name, 'content' => $content, 'order' => $order);
		return $this;
	}


	/**
	 * alias for editColumn for backward compatibility
	 * @param  string    $name
	 * @param  string $content
	 * @return Datatables
	 */
	public function edit_column($name, $content)
	{
		return $this->editColumn($name, $content);
	}


	/**
	 * edit column's content
	 * @param  string    $name
	 * @param  string $content
	 * @return Datatables
	 */
	public function editColumn($name, $content)
	{
		$this->edit_columns[] = array('name' => $name, 'content' => $content);
		return $this;
	}


	/**
	 * alias for removeColumn for backward compatibility
	 * @return Datatables
	 */
	public function remove_column()
	{
		$names = func_get_args();
		$this->excess_columns = array_merge($this->excess_columns, $names);
		return $this;
	}


	/**
	 * remove column from collection
	 * @return Datatables
	 */
	public function removeColumn()
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
		$this->removeDBDriverColumns();
	}


	/**
	 * Use data columns
	 * @return array
	 */
	public function useDataColumns()
	{
		$query = clone $this->query;
		if ($this->query_type == 'eloquent')
		{
			$this->columns = array_keys((array) $query->getQuery()->first());
		}
		else
		{
			$this->columns = array_keys((array) $query->first());
		}
		return $this->removeDBDriverColumns();
	}


	/**
	 * remove DB driver specific columns
	 * @return array
	 */
	public function removeDBDriverColumns()
	{
		// unset db driver specific columns
		foreach ($this->columns as $key => $value)
		{
			if (in_array($value, array('rn','row_num'))) unset ($this->columns[$key]);
		}
		return $this->columns = array_values($this->columns);
	}


	/**
	 *	Places extra columns
	 *	@return null
	 */
	private function initColumns()
	{
		foreach ($this->result_array as $rkey => &$rvalue)
		{

			// Convert data array to object value
			$data = array();
			foreach ($rvalue as $key => $value)
			{
				if ( is_object($this->result_object[$rkey]) )
				{
					$data[$key] = $this->result_object[$rkey]->$key;
				}
				else
				{
					$data[$key] = $value;
				}
			}

			// Process add columns
			foreach ($this->extra_columns as $key => $value)
			{
				if (is_string($value['content'])):
					$value['content'] = $this->blader($value['content'], $data);
				elseif (is_callable($value['content'])):
					$value['content'] = $value['content']($this->result_object[$rkey]);
				endif;

				$rvalue = $this->includeInArray($value, $rvalue);
			}

			// Process edit columns
			foreach ($this->edit_columns as $key => $value)
			{
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
	 *	Converts result_array number indexed array and consider excess columns
	 *	@return null
	 */
	private function regulateArray()
	{
		if ($this->mDataSupport) {
			$this->result_array_r = $this->result_array;
		} else {
			foreach ($this->result_array as $key => $value)
			{
				foreach ($this->excess_columns as $evalue)
				{
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

		foreach ($this->extra_columns as $key => $value)
		{
			if ($value['order'] === false) continue;
			$extra_columns_indexes[] = $value['order'];
		}

		for ($i=0,$c=count($this->columns);$i<$c;$i++) {

			if (in_array($this->getColumnName($this->columns[$i]), $this->excess_columns))
			{
				continue;
			}

			if (in_array($count, $extra_columns_indexes))
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
	private function blader($str, $data = array())
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
	private function includeInArray($item, $array)
	{
		if ($item['order'] === false)
		{
			return array_merge($array,array($item['name']=>$item['content']));
		}
		else
		{
			$count = 0;
			$last = $array;
			$first = array();
			foreach ($array as $key => $value)
			{
				if ($count == $item['order'])
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
	private function doPaging()
	{
		if (! is_null($this->input['start']) && ! is_null($this->input['length']))
		{
			$this->query->skip($this->input['start'])->take((int)$this->input['length']>0 ? $this->input['length'] : 10);
		}
	}


	/**
	 *	Datatable ordering
	 *	@return null
	 */
	private function doOrdering()
	{
		if (array_key_exists('order', $this->input) && count($this->input['order'])>0)
		{
			$columns = $this->cleanColumns( $this->last_columns );

			for ($i=0, $c=count($this->input['order']); $i<$c ; $i++)
			{
				$order_col = (int)$this->input['order'][$i]['column'];
				$order_dir = $this->input['order'][$i]['dir'];
				if ($this->new_version)
				{
					$column = $this->input['columns'][$order_col];
					if ($column['orderable'] == "true")
					{
						if (!empty($column['name']))
						{
							$this->query->orderBy($column['name'],$order_dir);
						}
						elseif (isset($columns[$order_col]))
						{
							$this->query->orderBy($columns[$order_col],$order_dir);
						}
					}
				}
				else
				{
					if (isset($columns[$order_col]))
					{
	                    if ($this->input['columns'][$order_col]['orderable'] == "true")
	                    {
	                        $this->query->orderBy($columns[$order_col],$order_dir);
	                    }
	                }
	            }
			}
		}
	}


	/**
	 * clean columns name
	 * @param array $cols
	 * @return array
	 */
	private function cleanColumns($cols, $use_alias  = true)
	{
		$return = array();
		foreach ($cols as  $i=> $col)
		{
			preg_match('#^(.*?)\s+as\s+(\S*?)$#si',$col,$matches);
			$return[$i] = empty($matches) ? $col : $matches[$use_alias?2:1];
		}

		return $return;
	}


	/**
	 * set auto filter off and run your own filter
	 * @param Closure
	 * @return Datatables
	 */
	public function filter(Closure $callback)
	{
		$this->autoFilter = false;

		$query = $this->query;
		call_user_func($callback, $query);

		return $this;
	}


	/**
	 *	Datatable filtering
	 *	@return null
	 */
	private function doFiltering()
	{
		$columns = $this->cleanColumns($this->columns, false);
		if ($this->mDataSupport)
		{
			$columns = $this->useDataColumns();
		}

		$db_prefix = $this->getDatabasePrefix();
		$input = $this->input;
		$connection = $this->connection;

		if ( $this->input['search']['value'] != '' )
		{
			$this->query->where(function($query) use ($columns, $db_prefix, $input, $connection)
			{
				for ($i=0,$c=count($input['columns']);$i<$c;$i++)
				{
					if ( $input['columns'][$i]['searchable'] == "true" && isset($columns[$i]) )
					{
						$column = $columns[$i];

						if (stripos($column, ' AS ') !== false)
						{
							$column = substr($column, stripos($column, ' AS ')+4);
						}

						// if column name was set on DT, use it instead
						if (!empty($input['columns'][$i]['name']))
						{
							$column = $input['columns'][$i]['name'];
						}

						$keyword = '%'.$input['search']['value'].'%';
						if (Config::get('datatables.search.use_wildcards'))
						{
							$keyword = $copy_this->wildcardLikeString($input['search']['value']);
						}

						// Check if the database driver is PostgreSQL
						// If it is, cast the current column to TEXT datatype
						$cast_begin = null;
						$cast_end = null;
						if ( $connection->getDriverName() === 'pgsql')
						{
							$cast_begin = "CAST(";
							$cast_end = " as TEXT)";
						}

						$column = $db_prefix . $column;
						if (Config::get('datatables.search.case_insensitive', false))
						{
							$query->orWhere($connection->raw('LOWER('.$cast_begin.$column.$cast_end.')'), 'LIKE', strtolower($keyword));
						}
						else
						{
							$query->orWhere($connection->raw($cast_begin.$column.$cast_end), 'LIKE', $keyword);
						}
					}
				}
			});

		}

		// column search
        for ($i=0, $c=count($this->input['columns']); $i<$c; $i++)
		{
			if ($this->input['columns'][$i]['searchable'] == "true" && $this->input['columns'][$i]['search']['value'] != '')
			{
				$keyword = '%'.$this->input['columns'][$i]['search']['value'].'%';

				if (Config::get('datatables.search.use_wildcards', false))
				{
					$keyword = $copy_this->wildcardLikeString($this->input['columns'][$i]['search']['value']);
				}

				if (Config::get('datatables.search.case_insensitive', false))
				{
					$column = $db_prefix . $columns[$i];
					$this->query->where($this->connection->raw('LOWER('.$column.')'),'LIKE',strtolower($keyword));
				}
				else
				{
					$col = strstr($columns[$i],'(') ? $this->connection->raw($columns[$i]) : $columns[$i];
					$this->query->where($col, 'LIKE', $keyword);
				}
			}
		}
	}


	/**
	 *  Adds % wildcards to the given string
	 *  @return string
	 */
	public function wildcardLikeString($str, $lowercase = true)
	{
		$wild = '%';
		$length = strlen($str);
		if ($length)
		{
			for ($i=0; $i < $length; $i++)
			{
				$wild .= $str[$i].'%';
			}
		}
		if ($lowercase) $wild = strtolower($wild);
		return $wild;
	}


	/**
	 *  Returns current database prefix
	 *  @return string
	 */
	public function getDatabasePrefix()
	{
		return Config::get('database.connections.'.Config::get('database.default').'.prefix', '');
	}


	/**
	 *	Counts current query
	 *  @param string $count variable to store to 'count_all' for iTotalRecords, 'display_all' for iTotalDisplayRecords
	 *	@return null
	 */
	private function count()
	{
		$query = $this->query;

        // if its a normal query ( no union ) replace the select with static text to improve performance
		$myQuery = clone $query;
		if ( !preg_match( '/UNION/i', strtoupper($myQuery->toSql()) ) )
		{
			$myQuery->select( $this->connection->Raw("'1' as row_count") );
		}

		return $this->connection->table($this->connection->raw('('.$myQuery->toSql().') count_row_table'))
			->setBindings($myQuery->getBindings())->count();
	}


	/**
	 * get filtered records
	 * @return int
	 */
	private function getFilteredRecords()
	{
		return $this->filteredRecords = $this->count();
	}


	/**
	 * get total records
	 * @return int
	 */
	private function getTotalRecords()
	{
		return $this->totalRecords = $this->count();
	}


	/**
	 * get column name from string
	 * @param  string $str
	 * @return string
	 */
	private function getColumnName($str)
	{
		preg_match('#^(\S*?)\s+as\s+(\S*?)$#si',$str,$matches);

		if (! empty($matches))
		{
			return $matches[2];
		}
		elseif (strpos($str,'.'))
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
		if ($this->new_version)
		{
			$output = array(
				"draw" => (int) $this->input['draw'],
				"recordsTotal" => $this->totalRecords,
				"recordsFiltered" => $this->filteredRecords,
				"data" => $this->result_array_r,
				);
		}
		else
		{
			$sColumns = array_merge_recursive($this->columns, $this->sColumns);
			$output = array(
				"sEcho" => (int) $this->input['draw'],
				"iTotalRecords" => $this->totalRecords,
				"iTotalDisplayRecords" => $this->filteredRecords,
				"aaData" => $this->result_array_r,
				"sColumns" => $sColumns
				);
		}

		if (Config::get('app.debug', false))
		{
			$output['aQueries'] = $this->connection->getQueryLog();
		}
		return Response::json($output);
	}

}
