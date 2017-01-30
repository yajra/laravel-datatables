<?php

namespace Yajra\Datatables\Tests\Integration;

use DB;
use Illuminate\Http\JsonResponse;
use Yajra\Datatables\Datatables;
use Yajra\Datatables\Engines\QueryBuilderEngine;
use Yajra\Datatables\Facades\Datatables as DatatablesFacade;
use Yajra\Datatables\Tests\TestCase;

class QueryBuilderEngineTest extends TestCase
{
    /** @test */
    public function it_returns_all_records_when_no_parameters_is_passed()
    {
        $crawler = $this->call('GET', '/queryBuilder/users');
        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);
    }

    /** @test */
    public function it_can_perform_global_search()
    {
        $crawler = $this->call('GET', '/queryBuilder/users', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'email', 'name' => 'email', 'searchable' => "true", 'orderable' => "true"],
            ],
            'search'  => ['value' => 'Record 19'],
        ]);

        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 1,
        ]);
    }

    /** @test */
    public function it_accepts_a_query_builder_using_of_factory()
    {
        $dataTable = Datatables::of(DB::table('users'
        ));
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(QueryBuilderEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_builder_using_facade()
    {
        $dataTable = DatatablesFacade::of(DB::table('users'
        ));
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(QueryBuilderEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_builder_using_facade_queryBuilder_method()
    {
        $dataTable = DatatablesFacade::queryBuilder(DB::table('users'
        ));
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(QueryBuilderEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_builder_using_ioc_container()
    {
        $dataTable = app('datatables')->queryBuilder(DB::table('users'));
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(QueryBuilderEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_builder_using_ioc_container_factory()
    {
        $dataTable = app('datatables')->of(DB::table('users'
        ));
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(QueryBuilderEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app['router']->get('/queryBuilder/users', function (Datatables $datatables) {
            return $datatables->queryBuilder(DB::table('users'))->make('true');
        });
    }
}
