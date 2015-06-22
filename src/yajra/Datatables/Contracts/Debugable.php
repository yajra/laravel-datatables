<?php

namespace yajra\Datatables\Contracts;

use Closure;

interface Debugable
{
    /**
     * Append debug parameters on output.
     *
     * @param  array $output
     * @return array
     */
    public function showDebugger(array $output);
}
