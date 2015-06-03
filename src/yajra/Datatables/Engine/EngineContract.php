<?php namespace yajra\Datatables\Engine;


interface EngineContract
{

    /**
     * Set datatables results object and arrays
     */
    public function setResults();

    /**
     * Get results of query and convert to array
     *
     * @return array
     */
    public function getResults();

}
