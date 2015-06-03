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
use League\Fractal\Resource\Collection as FractalCollection;

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
     * @param Collection $collection
     * @param $request
     */
    public function __construct(Collection $collection, $request)
    {
        $this->collection = $collection;
        $this->original_collection = $collection;
        $this->columns = array_keys($this->serialize((array) $collection->first()));

        parent::__construct($request);
    }

    /**
     * Serialize collection
     *
     * @param  mixed $collection
     * @return mixed|null
     */
    protected function serialize($collection)
    {
        return $collection instanceof Arrayable ? $collection->toArray() : $collection;
    }

    /**
     * @inheritdoc
     */
    public function getTotalRecords()
    {
        return $this->totalRecords = $this->collection->count();
    }

    /**
     * @inheritdoc
     */
    public function make($mDataSupport = false, $orderFirst = true)
    {
        return parent::make($mDataSupport, $orderFirst);
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function doFiltering()
    {
        $input = $this->input;
        $columns = $input['columns'];

        if ( ! empty($this->input['search']['value'])) {
            $this->collection = $this->collection->filter(/**
             * @param $row
             * @return bool
             */
            /**
             * @param $row
             * @return bool
             */
                function ($row) use ($columns, $input) {
                    $data = $this->serialize($row);
                    $found = [];
                    for ($i = 0, $c = count($columns); $i < $c; $i++) {
                        if ($columns[$i]['searchable'] != "true") {
                            continue;
                        }

                        $column = $this->getColumnIdentity($columns, $i);

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
     * @inheritdoc
     */
    public function doColumnSearch(array $columns)
    {
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($columns[$i]['searchable'] != "true" || $columns[$i]['search']['value'] == '') {
                continue;
            }

            $column = $this->getColumnIdentity($columns, $i);

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
     * @inheritdoc
     */
    public function getTotalFilteredRecords()
    {
        return $this->filteredRecords = $this->collection->count();
    }

    /**
     * @inheritdoc
     */
    public function setResults()
    {
        $this->result_object = $this->collection->all();
        $this->result_array = array_map(function ($object) {
            return $object instanceof Arrayable ? $object->toArray() : (array) $object;
        }, $this->getResults());
    }

    /**
     * @inheritdoc
     */
    public function getResults()
    {
        return $this->result_object;
    }

    /**
     * @inheritdoc
     */
    public function filter(Closure $callback)
    {
        $this->autoFilter = false;

        call_user_func($callback, $this);

        return $this;
    }

    /**
     * @inheritdoc
     */
    protected function showDebugger($output)
    {
        $output["input"] = $this->input;

        return $output;
    }

    /**
     * @inheritdoc
     */
    protected function paginate()
    {
        $this->collection = $this->collection->slice($this->input['start'],
            (int) $this->input['length'] > 0 ? $this->input['length'] : 10);
    }

}
