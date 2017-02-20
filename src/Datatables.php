<?php

namespace Yajra\Datatables;

use Yajra\Datatables\Html\Builder;

/**
 * Class Datatables.
 *
 * @package Yajra\Datatables
 * @author  Arjay Angeles <aqangeles@gmail.com>
 */
class Datatables
{
    /**
     * Datatables request object.
     *
     * @var \Yajra\Datatables\Request
     */
    protected $request;

    /**
     * HTML builder instance.
     *
     * @var \Yajra\Datatables\Html\Builder
     */
    protected $html;

    /**
     * Datatables constructor.
     *
     * @param \Yajra\Datatables\Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Gets query and returns instance of class.
     *
     * @param  mixed $builder
     * @return mixed
     * @throws \Exception
     */
    public static function of($builder)
    {
        $datatables = app('datatables');
        $config     = app('config');
        $engines    = $config->get('datatables.engines');
        $builders   = $config->get('datatables.builders');

        foreach ($builders as $class => $engine) {
            if ($builder instanceof $class) {
                $class = $engines[$engine];

                return new $class($builder, $datatables->getRequest());
            }
        }

        throw new \Exception('No available engine for ' . get_class($builder));
    }

    /**
     * Get request object.
     *
     * @return \Yajra\Datatables\Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Datatables using Query Builder.
     *
     * @param \Illuminate\Database\Query\Builder|mixed $builder
     * @return \Yajra\Datatables\Engines\QueryBuilderEngine
     */
    public function queryBuilder($builder)
    {
        return new Engines\QueryBuilderEngine($builder, $this->request);
    }

    /**
     * Datatables using Eloquent Builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder|mixed $builder
     * @return \Yajra\Datatables\Engines\EloquentEngine
     */
    public function eloquent($builder)
    {
        return new Engines\EloquentEngine($builder, $this->request);
    }

    /**
     * Datatables using Collection.
     *
     * @param \Illuminate\Support\Collection|mixed $builder
     * @return \Yajra\Datatables\Engines\CollectionEngine
     */
    public function collection($builder)
    {
        return new Engines\CollectionEngine($builder, $this->request);
    }

    /**
     * Process dataTables needed render output.
     *
     * @param array $tables
     * @param string $view
     * @param array $data
     * @param array $mergeData
     * @return \Illuminate\Http\JsonResponse|\Illuminate\View\View
     */
    public function renderMultiple(array $tables, $view, $data = [], $mergeData = [])
    {
        $tableId = $this->request->input('tableId');

        if ($this->request->ajax() && $this->request->wantsJson()) {
            return $this->getTableFromId($tables, $tableId)->ajax();
        }

        $action = $this->request->get('action');

        if (in_array($action, ['print', 'csv', 'excel', 'pdf'])) {
            if ($action == 'print') {
                return $this->getTableFromId($tables, $tableId)->printPreview();
            }

            return call_user_func_array([$this->getTableFromId($tables, $tableId), $action], []);
        }

        $tables = array_map(function($table) {
            if (!$table->hasCustomId()) {
                $table->setId($this->createCustomIdFor($table));
            }
            return $table->html();
        }, $tables);

        return view($view, $data, $mergeData)->with($tables);
    }

    private function createCustomIdFor($table)
    {
        return camel_case((new \ReflectionClass($table))->getShortName());
    }
    
    private function getTableFromId($tables, $tableId)
    {
        if (!array_key_exists($tableId, $tables)) {
            throw new \Exception("Table {$tableId} is not defined in multiple renderer.");
        }
        return $tables[$tableId];
    }

    /**
     * Get html builder instance.
     *
     * @return \Yajra\Datatables\Html\Builder
     * @throws \Exception
     */
    public function getHtmlBuilder()
    {
        if (! class_exists('\Yajra\Datatables\Html\Builder')) {
            throw new \Exception('Please install yajra/laravel-datatables-html to be able to use this function.');
        }

        return $this->html ?: $this->html = app('datatables.html');
    }
}
