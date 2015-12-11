<?php


use yajra\Datatables\Datatables;
use yajra\Datatables\Request;

class DatatablesTest extends PHPUnit_Framework_TestCase
{

    public function test_get_html_builder()
    {
        $datatables = $this->getDatatables();
        $html       = $datatables->getHtmlBuilder();

        $this->assertInstanceOf('yajra\Datatables\Html\Builder', $html);
    }

    public function test_get_request()
    {
        $datatables = $this->getDatatables();
        $request = $datatables->getRequest();

        $this->assertInstanceOf('yajra\Datatables\Request', $request);
    }

    /**
     * @return \yajra\Datatables\Datatables
     */
    protected function getDatatables()
    {
        $datatables = new Datatables(Request::capture());

        return $datatables;
    }
}
