<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Mockery as m;
use Yajra\Datatables\Datatables;
use Yajra\Datatables\Engines\CollectionEngine;
use Yajra\Datatables\Engines\EloquentEngine;
use Yajra\Datatables\Engines\QueryBuilderEngine;
use Yajra\Datatables\Request;

class DatatablesTest extends PHPUnit_Framework_TestCase
{
    /** @test */
    public function it_will_return_the_request_instance()
    {
        $datatables = $this->createDataTable();
        $request    = $datatables->getRequest();

        $this->assertInstanceOf('Yajra\Datatables\Request', $request);
    }

    /** @test */
    public function it_expects_thrown_exception()
    {
        $this->expectException(\Exception::class);
        $datatables = $this->createDataTable();
        $datatables->getHtmlBuilder();
    }

    /**
     * @return \Yajra\Datatables\Datatables
     */
    protected function createDataTable()
    {
        $datatables = new Datatables(Request::capture());

        return $datatables;
    }

    /** @test */
    public function it_will_return_query_builder_engine()
    {
        $dataTable  = $this->createDataTable();
        $connection = $this->createMock(Connection::class);
        $engine     = $dataTable->queryBuilder(new QueryBuilder($connection));
        $this->assertInstanceOf(QueryBuilderEngine::class, $engine);
    }

    /** @test */
    public function it_will_return_eloquent_engine()
    {
        $dataTable = $this->createDataTable();
        $engine    = $dataTable->eloquent(UserModelStub::query());
        $this->assertInstanceOf(EloquentEngine::class, $engine);
    }

    /** @test */
    public function it_will_return_collection_engine()
    {
        $dataTable = $this->createDataTable();
        $engine    = $dataTable->collection(collect([]));
        $this->assertInstanceOf(CollectionEngine::class, $engine);
    }
}

class UserModelStub extends Model
{
    public function getConnection()
    {
        $connection = m::mock(Connection::class);
        $connection->shouldReceive('getQueryGrammar')->andReturn(new Grammar);
        $connection->shouldReceive('getPostProcessor')->andReturn(new Processor);
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        $connection->shouldReceive('getDriverName')->andReturn('');

        return $connection;
    }
}
