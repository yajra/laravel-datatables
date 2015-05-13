<?php namespace yajra\Datatables\Engine;

/**
 * Laravel Datatables Collection Engine
 *
 * @package    Laravel
 * @category   Package
 * @author     Arjay Angeles <aqangeles@gmail.com>
 */

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CollectionEngine extends BaseEngine implements EngineContract
{

    /**
     * Collection object
     *
     * @var Collection
     */
    public $collection;

    /**
     * Collection object
     *
     * @var Collection
     */
    public $original_collection;

    /**
     * Read Input into $this->input according to jquery.dataTables.js version
     *
     * @param Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
        $this->original_collection = $collection;
        $this->columns = array_keys($this->serialize((array) $collection->first()));

        parent::__construct();

        return $this;
    }

    /**
     * Get total records
     *
     * @return int
     */
    public function getTotalRecords()
    {
        return $this->totalRecords = $this->collection->count();
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
            $this->collection = $this->collection->filter(function ($row) use ($columns, $input) {
                $data = $this->serialize($row);
                $found = [];
                for ($i = 0, $c = count($columns); $i < $c; $i++) {
                    if ($columns[$i]['searchable'] != "true") {
                        continue;
                    }

                    if ( ! empty($columns[$i]['name'])) {
                        $column = $columns[$i]['name'];
                    } else {
                        $column = $this->columns[$i];
                    }

                    $keyword = $input['search']['value'];

                    if ( ! array_key_exists($column, $data)) {
                        continue;
                    }

                    if ($this->isCaseInsensitive()) {
                        if (Str::contains(Str::lower($data[$column]), Str::lower($keyword))) {
                            $found[] = true;
                        }
                    } else {
                        if (Str::contains($data[$column], $keyword)) {
                            $found[] = true;
                        }
                    }
                }

                if (count($found)) {
                    return true;
                }

                return false;
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
            if ($columns[$i]['searchable'] != "true" or $columns[$i]['search']['value'] == '') {
                continue;
            }

            if ( ! empty($columns[$i]['name'])) {
                $column = $columns[$i]['name'];
            } else {
                $column = $this->columns[$i];
            }

            $keyword = $columns[$i]['search']['value'];

            $this->collection = $this->collection->filter(function ($row) use ($column, $keyword) {
                $data = $this->serialize($row);
                $found = [];

                if ($this->isCaseInsensitive()) {
                    if (strpos(Str::lower($data[$column]), Str::lower($keyword)) !== false) {
                        $found[] = true;
                    }
                } else {
                    if (strpos($data[$column], $keyword) !== false) {
                        $found[] = true;
                    }
                }

                if (count($found)) {
                    return true;
                }

                return false;
            });
        }
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

        // perform ordering before filtering
        $this->doOrdering();

        // check if auto filtering was overridden
        if ($this->autoFilter) {
            $this->doFiltering();
        }

        $this->getTotalFilteredRecords();
        $this->doPaging();

        $this->setResults();
        $this->initColumns();
        $this->regulateArray();

        return $this->output();
    }

    /**
     * Get filtered records
     *
     * @return int
     */
    public function getTotalFilteredRecords()
    {
        return $this->filteredRecords = $this->collection->count();
    }

    /**
     * Datatables paging
     *
     * @return null
     */
    public function doPaging()
    {
        if ( ! is_null($this->input['start']) && ! is_null($this->input['length']) && $this->input['length'] != -1) {
            $this->collection = $this->collection->slice($this->input['start'],
                (int) $this->input['length'] > 0 ? $this->input['length'] : 10);
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
            $columns = $this->columns;

            for ($i = 0, $c = count($this->input['order']); $i < $c; $i++) {
                $order_col = (int) $this->input['order'][$i]['column'];
                $order_dir = $this->input['order'][$i]['dir'];
                $column = $this->input['columns'][$order_col];

                if ($column['orderable'] <> "true") {
                    continue;
                }

                if ( ! empty($column['name'])) {
                    $this->collection->sortBy(function ($row) use ($column) {
                        return $row[$column['name']];
                    });
                } elseif (isset($columns[$order_col])) {
                    $this->collection->sortBy(function ($row) use ($columns, $order_col) {
                        return $row[$columns[$order_col]];
                    });
                }

                if ($order_dir == 'desc') {
                    $this->collection = $this->collection->reverse();
                }
            }
        }
    }

    /**
     * Gets results from prepared query
     */
    public function setResults()
    {
        $this->result_object = $this->collection->all();
        $this->result_array = array_map(function ($object) {
            return $object instanceof Arrayable ? $object->toArray() : (array) $object;
        }, $this->result_object);
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function filter(Closure $callback)
    {
        $this->autoFilter = false;

        call_user_func($callback, $this);

        return $this;
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
     * @param  mixed $collection
     * @return mixed|null
     */
    protected function serialize($collection)
    {
        return $collection instanceof Arrayable ? $collection->toArray() : $collection;
    }

}
