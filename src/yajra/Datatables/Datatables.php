<?php namespace yajra\Datatables;

/**
 * Laravel Datatables Package
 * This Package is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net)
 *
 * @package    Laravel
 * @category   Package
 * @author     Arjay Angeles <aqangeles@gmail.com>
 */

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use yajra\Datatables\Engine\CollectionEngine;
use yajra\Datatables\Engine\EloquentEngine;
use yajra\Datatables\Engine\QueryBuilderEngine;

/**
 * Class Datatables
 *
 * @package yajra\Datatables
 */
class Datatables
{

    /**
     * Gets query and returns instance of class
     *
     * @param $builder
     * @return mixed
     */
    public static function of($builder)
    {

        if ($builder instanceof QueryBuilder) {
            $ins = new QueryBuilderEngine($builder);
        } else {
            $ins = $builder instanceof Collection ? new CollectionEngine($builder) : new EloquentEngine($builder);
        }

        return $ins;
    }

}
