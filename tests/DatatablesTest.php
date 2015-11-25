<?php


use Yajra\Datatables\Datatables;
use Yajra\Datatables\Request;

class DatatablesTest extends PHPUnit_Framework_TestCase
{
    public function test_get_html_builder()
    {
        $datatables = $this->getDatatables();
        $html       = $datatables->getHtmlBuilder();

        $this->assertInstanceOf('Yajra\Datatables\Html\Builder', $html);
    }

    public function test_get_request()
    {
        $datatables = $this->getDatatables();
        $request    = $datatables->getRequest();

        $this->assertInstanceOf('Yajra\Datatables\Request', $request);
    }

    /**
     * @return \Yajra\Datatables\Datatables
     */
    protected function getDatatables()
    {
        $datatables = new Datatables(Request::capture());

        return $datatables;
    }
}
