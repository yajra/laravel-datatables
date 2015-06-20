<?php

namespace yajra\Datatables\Engine;

/**
 * Laravel Datatables Collection Engine
 *
 * @package  Laravel
 * @category Package
 * @author   Arjay Angeles <aqangeles@gmail.com>
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
     * @param Collection $collection
     * @param $request
     */
    public function __construct(Collection $collection, $request)
    {
        $this->collection          = $collection;
        $this->original_collection = $collection;
        $this->columns             = array_keys($this->serialize((array) $collection->first()));

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
            for ($i = 0, $c = count($this->input['order']); $i < $c; $i++) {
                $order_col = (int) $this->input['order'][$i]['column'];
                $order_dir = $this->input['order'][$i]['dir'];
                if (! $this->isColumnOrderable($this->input['columns'][$order_col])) {
                    continue;
                }
                $column           = $this->getOrderColumnName($order_col);
                $this->collection = $this->collection->sortBy(
                    function ($row) use ($column) {
                        return $row[$column];
                    }
                );

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
        $columns          = $this->input['columns'];
        $this->collection = $this->collection->filter(
            function ($row) use ($columns) {
                $data = $this->serialize($row);
                $found = [];

                for ($i = 0, $c = count($columns); $i < $c; $i++) {
                    if ($columns[$i]['searchable'] != "true") {
                        continue;
                    }

                    $column = $this->getColumnIdentity($columns, $i);
                    $keyword = $this->input['search']['value'];

                    if (! $this->columnExists($column, $data)) {
                        continue;
                    }

                    if ($this->isCaseInsensitive()) {
                        $found[] = Str::contains(Str::lower($data[$column]), Str::lower($keyword));
                    } else {
                        $found[] = Str::contains($data[$column], $keyword);
                    }
                }

                return in_array(true, $found);
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function doColumnSearch()
    {
        $columns = $this->input['columns'];
        for ($i = 0, $c = count($columns); $i < $c; $i++) {
            if ($columns[$i]['searchable'] != "true" || $columns[$i]['search']['value'] == '') {
                continue;
            }

            $column = $this->getColumnIdentity($columns, $i);

            $keyword = $columns[$i]['search']['value'];

            $this->collection = $this->collection->filter(
                function ($row) use ($column, $keyword) {
                    $data = $this->serialize($row);

                    if ($this->isCaseInsensitive()) {
                        return strpos(Str::lower($data[$column]), Str::lower($keyword)) !== false;
                    } else {
                        return strpos($data[$column], $keyword) !== false;
                    }
                }
            );
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
    public function getResults()
    {
        $this->result_object = $this->collection->all();

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
        $this->collection = $this->collection->slice(
            $this->input['start'],
            (int) $this->input['length'] > 0 ? $this->input['length'] : 10
        );
    }

    /**
     * Check if column name exists in collection keys
     *
     * @param  string $column
     * @param  array  $data
     * @return bool
     */
    private function columnExists($column, array $data)
    {
        return array_key_exists($column, $data);
    }
}
