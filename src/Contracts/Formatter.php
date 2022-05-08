<?php

namespace Yajra\DataTables\Contracts;

interface Formatter
{
    /**
     * @param  mixed  $value
     * @param  mixed  $row
     * @return string
     */
    public function format($value, $row);
}
