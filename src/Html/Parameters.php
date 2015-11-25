<?php

namespace Yajra\Datatables\Html;

use Illuminate\Support\Fluent;

/**
 * Class Parameters
 *
 * @package Yajra\Datatables\Html
 * @see     https://datatables.net/reference/option/ for possible columns option
 */
class Parameters extends Fluent
{
    /**
     * @var array
     */
    protected $attributes = [
        'serverSide' => true,
        'processing' => true,
        'ajax'       => '',
        'columns'    => []
    ];
}
