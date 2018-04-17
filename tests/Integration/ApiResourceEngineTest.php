<?php

namespace Yajra\DataTables\Tests\Integration;

use Yajra\DataTables\DataTables;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Tests\TestCase;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\ApiResourceDataTable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\Tests\Http\Resources\UserResource;
use Yajra\DataTables\Facades\DataTables as DatatablesFacade;

class ApiResourceEngineTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_when_no_parameters_is_passed()
    {
        $crawler = $this->call('GET', '/resource/users');
        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);
    }

    /** @test */
    public function it_only_returns_records_in_structure_defined_in_resource()
    {
        $crawler = $this->call('GET', '/resource/users');
        $crawler->assertJsonStructure([
            'draw',
            'recordsTotal',
            'recordsFiltered',
            'data' => [
                [
                    'email',
                    'name',
                ],
            ],
        ]);
    }

    /** @test */
    public function it_can_perform_global_search()
    {
        $crawler = $this->call('GET', '/resource/users', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => 'Record 19'],
        ]);

        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 1,
        ]);
    }

    /** @test */
    public function it_accepts_a_resource_using_of_factory()
    {
        $dataTable = DataTables::of(UserResource::collection(User::all()));
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(ApiResourceDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_resource_using_facade()
    {
        $dataTable = DatatablesFacade::of(UserResource::collection(User::all()));
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(ApiResourceDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_pagination_resource()
    {
        $dataTable = DataTables::of(UserResource::collection(User::paginate(10)));
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(ApiResourceDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_returns_only_paginated_records()
    {
        $crawler = $this->call('GET', '/resource/users_p');
        $crawler->assertJson([
             'draw'            => 0,
             'recordsTotal'    => 10,
             'recordsFiltered' => 10,
         ]);
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app['router']->get('/resource/users', function (DataTables $datatables) {
            return $datatables->resource(UserResource::collection(User::all()))->make('true');
        });

        $this->app['router']->get('/resource/users_p', function (DataTables $datatables) {
            return $datatables->resource(UserResource::collection(User::paginate(10)))->make('true');
        });
    }
}
