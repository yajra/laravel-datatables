<?php

namespace Yajra\Datatables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Yajra\Datatables\Datatables;
use Yajra\Datatables\Engines\CollectionEngine;
use Yajra\Datatables\Facades\Datatables as DatatablesFacade;
use Yajra\Datatables\Tests\Models\User;
use Yajra\Datatables\Tests\TestCase;

class CollectionEngineTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_when_no_parameters_is_passed()
    {
        $crawler = $this->call('GET', '/collection/users');
        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);
    }

    /** @test */
    public function it_can_perform_global_search()
    {
        $crawler = $this->call('GET', '/collection/users', [
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
    public function it_accepts_a_model_collection_using_of_factory()
    {
        $dataTable = Datatables::of(User::all());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(CollectionEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_collection_using_of_factory()
    {
        $dataTable = Datatables::of(collect());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(CollectionEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_collection_using_facade()
    {
        $dataTable = DatatablesFacade::of(User::all());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(CollectionEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_collection_using_facade()
    {
        $dataTable = DatatablesFacade::of(collect());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(CollectionEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_ioc_container()
    {
        $dataTable = app('datatables')->collection(User::all());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(CollectionEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_can_sort_case_insensitive_strings()
    {
        config()->set('app.debug', false);
        request()->merge([
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => "true", 'orderable' => "true"],
            ],
            'order'   => [["column" => 0, "dir" => "asc"]],
            'start'   => 0,
            'length'  => 10,
            'draw'    => 1,
        ]);

        $collection = collect([
            ['name' => 'ABC'],
            ['name' => 'BCD'],
            ['name' => 'ZXY'],
            ['name' => 'aaa'],
            ['name' => 'bbb'],
            ['name' => 'zzz'],
        ]);

        $dataTable = app('datatables')->collection($collection);
        /** @var JsonResponse $response */
        $response = $dataTable->make('true');

        $this->assertEquals([
            'draw'            => 1,
            'recordsTotal'    => 6,
            'recordsFiltered' => 6,
            'data'            => [
                ['name' => 'aaa'],
                ['name' => 'ABC'],
                ['name' => 'bbb'],
                ['name' => 'BCD'],
                ['name' => 'ZXY'],
                ['name' => 'zzz'],
            ],
        ], $response->getData(true));
    }

    /** @test */
    public function it_accepts_a_model_using_ioc_container_factory()
    {
        $dataTable = app('datatables')->of(User::all());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(CollectionEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_array_data_source()
    {
        $source = [
            ['id' => 1, 'name' => 'foo'],
            ['id' => 2, 'name' => 'bar'],
        ];
        $dataTable = app('datatables')->of($source);
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(CollectionEngine::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app['router']->get('/collection/users', function (Datatables $datatables) {
            return $datatables->collection(User::all())->make('true');
        });
    }
}
