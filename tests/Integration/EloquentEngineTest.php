<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\DataTablesFactory;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Facades\Datatables as DatatablesFacade;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class EloquentEngineTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_when_no_parameters_is_passed()
    {
        $crawler = $this->call('GET', '/eloquent/users');
        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);
    }

    /** @test */
    public function it_can_perform_global_search()
    {
        $crawler = $this->call('GET', '/eloquent/users', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'email', 'name' => 'email', 'searchable' => "true", 'orderable' => "true"],
            ],
            'search'  => ['value' => 'Record-19'],
        ]);

        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 1,
        ]);
    }

    /** @test */
    public function it_accepts_a_model_using_of_factory()
    {
        $dataTable = DataTablesFactory::of(User::query());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_facade()
    {
        $dataTable = DatatablesFacade::of(User::query());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_facade_eloquent_method()
    {
        $dataTable = DatatablesFacade::eloquent(User::query());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_ioc_container()
    {
        $dataTable = app('datatables')->eloquent(User::query());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_ioc_container_factory()
    {
        $dataTable = app('datatables')->of(User::query());
        $response  = $dataTable->make(true);
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app['router']->get('/eloquent/users', function (DataTablesFactory $datatables) {
            return $datatables->eloquent(User::query())->make('true');
        });
    }
}
