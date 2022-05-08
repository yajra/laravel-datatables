<?php

namespace Yajra\DataTables\Contracts;

interface Formatter
{
    /**
     * @param  mixed  $value
     * @param  array|\Illuminate\Database\Eloquent\Model|object  $row
     * @return string
     */
    public function format($value, $row);
}
