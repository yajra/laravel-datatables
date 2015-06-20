<?php

namespace yajra\Datatables\Html;

use Illuminate\Support\Fluent;

/**
 * Class Column
 *
 * @package yajra\Datatables\Html
 * @see     https://datatables.net/reference/option/ for possible columns option
 */
class Column extends Fluent
{
    /**
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        $attributes['orderable']  = isset($attributes['orderable']) ? $attributes['orderable'] : true;
        $attributes['searchable'] = isset($attributes['searchable']) ? $attributes['searchable'] : true;

        parent::__construct($attributes);
    }
}
