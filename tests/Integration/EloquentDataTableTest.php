<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Facades\DataTables as DatatablesFacade;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class EloquentDataTableTest extends TestCase
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
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
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
        $dataTable = DataTables::of(User::query());
        $response  = $dataTable->toJson();
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_facade()
    {
        $dataTable = DatatablesFacade::of(User::query());
        $response  = $dataTable->toJson();
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_facade_eloquent_method()
    {
        $dataTable = DatatablesFacade::eloquent(User::query());
        $response  = $dataTable->toJson();
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_ioc_container()
    {
        $dataTable = app('datatables')->eloquent(User::query());
        $response  = $dataTable->toJson();
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_ioc_container_factory()
    {
        $dataTable = app('datatables')->of(User::query());
        $response  = $dataTable->toJson();
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_returns_only_the_selected_columns_with_dotted_notation()
    {
        $json = $this->call('GET', '/eloquent/only')->json();
        $this->assertArrayNotHasKey('id', $json['data'][0]);
        $this->assertArrayHasKey('title', $json['data'][0]);
        $this->assertArrayNotHasKey('id', $json['data'][0]['user']);
        $this->assertArrayHasKey('name', $json['data'][0]['user']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/eloquent/users', function (DataTables $datatables) {
            return $datatables->eloquent(User::query())->toJson();
        });

        $this->app['router']->get('/eloquent/only', function (DataTables $datatables) {
            return $datatables->eloquent(Post::with('user'))
                              ->only(['title', 'user.name'])
                              ->toJson();
        });
    }
}
