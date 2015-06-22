<?php

namespace yajra\Datatables\Contracts;

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
