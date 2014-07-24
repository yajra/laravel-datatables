<?php namespace Bllim\Datatables\Facade;

use Illuminate\Support\Facades\Facade;

class Datatables extends Facade {

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'datatables'; }

}
