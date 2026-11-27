<?php

namespace Yajra\DataTables\Contracts;

use Illuminate\Database\Eloquent\Model;

interface Formatter
{
    /**
     * @param  array|Model|object  $row
     * @return string
     */
    public function format(mixed $value, $row);
}
