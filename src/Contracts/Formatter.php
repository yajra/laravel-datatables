<?php

namespace Yajra\DataTables\Contracts;

interface Formatter
{
    /**
     * @param  array|\Illuminate\Database\Eloquent\Model|object  $row
     * @return string
     */
    public function format(mixed $value, $row);
}
