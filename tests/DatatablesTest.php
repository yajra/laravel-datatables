<?php


use Rafaelqm\Datatables\Datatables;
use Rafaelqm\Datatables\Request;

class DatatablesTest extends PHPUnit_Framework_TestCase
{
    public function test_get_html_builder()
    {
        $datatables = $this->getDatatables();
        $html       = $datatables->getHtmlBuilder();

        $this->assertInstanceOf('Rafaelqm\Datatables\Html\Builder', $html);
    }

    public function test_get_request()
    {
        $datatables = $this->getDatatables();
        $request    = $datatables->getRequest();

        $this->assertInstanceOf('Rafaelqm\Datatables\Request', $request);
    }

    /**
     * @return \Rafaelqm\Datatables\Datatables
     */
    protected function getDatatables()
    {
        $datatables = new Datatables(Request::capture());

        return $datatables;
    }
}
