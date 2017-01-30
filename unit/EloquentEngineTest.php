<?php

use Yajra\Datatables\Datatables;
use Yajra\Datatables\Engines\EloquentEngine;
use Yajra\Datatables\Request;

class EloquentEngineTest extends PHPUnit_Framework_TestCase
{
    /** @test */
    public function it_will_return_eloquent_engine_via_factory()
    {
        $engine = Datatables::of(UserModelStub::query());
        $this->assertInstanceOf(EloquentEngine::class, $engine);
    }

    /** @test */
    public function it_will_return_eloquent_engine()
    {
        $dataTable = $this->createDataTable();
        $engine    = $dataTable->eloquent(UserModelStub::query());
        $this->assertInstanceOf(EloquentEngine::class, $engine);
    }

    /**
     * @return \Yajra\Datatables\Datatables
     */
    protected function createDataTable()
    {
        $datatables = new Datatables(Request::capture());

        return $datatables;
    }
}
