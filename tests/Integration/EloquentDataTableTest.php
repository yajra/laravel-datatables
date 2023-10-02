<?php

namespace Yajra\DataTables\Tests\Integration;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Facades\DataTables as DatatablesFacade;
use Yajra\DataTables\Tests\Formatters\DateFormatter;
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
            'draw' => 0,
            'recordsTotal' => 20,
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
            'search' => ['value' => 'Record-19'],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 1,
        ]);
    }

    /** @test */
    public function it_accepts_a_model_using_of_factory()
    {
        $dataTable = DataTables::of(User::query());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_facade()
    {
        $dataTable = DatatablesFacade::of(User::query());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_facade_eloquent_method()
    {
        $dataTable = DatatablesFacade::eloquent(User::query());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_ioc_container()
    {
        $dataTable = app('datatables')->eloquent(User::query());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_ioc_container_factory()
    {
        $dataTable = app('datatables')->of(User::query());
        $response = $dataTable->toJson();
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

    /** @test */
    public function it_can_return_formatted_columns()
    {
        $crawler = $this->call('GET', '/eloquent/formatColumn');

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $user = User::find(1);
        $data = $crawler->json('data')[0];

        $this->assertTrue(isset($data['created_at']));
        $this->assertTrue(isset($data['created_at_formatted']));

        $this->assertEquals(Carbon::parse($user->created_at)->format('Y-m-d'), $data['created_at_formatted']);
    }

    /** @test */
    public function it_can_return_formatted_column_using_closure()
    {
        $crawler = $this->call('GET', '/eloquent/formatColumn-closure');

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $user = User::find(1);
        $data = $crawler->json('data')[0];

        $this->assertTrue(isset($data['created_at']));
        $this->assertTrue(isset($data['created_at_formatted']));

        $this->assertEquals(Carbon::parse($user->created_at)->format('Y-m-d'), $data['created_at_formatted']);
    }

    /** @test */
    public function it_can_return_formatted_column_on_invalid_formatter()
    {
        $crawler = $this->call('GET', '/eloquent/formatColumn-fallback');

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $user = User::find(1);
        $data = $crawler->json('data')[0];

        $this->assertTrue(isset($data['created_at']));
        $this->assertTrue(isset($data['created_at_formatted']));

        $this->assertEquals($user->created_at, $data['created_at_formatted']);
    }

    /** @test */
    public function it_accepts_a_relation()
    {
        $user = User::first();

        $dataTable = app('datatables')->eloquent($user->posts());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(EloquentDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app['router'];
        $router->get('/eloquent/users', function (DataTables $datatables) {
            return $datatables->eloquent(User::query())->toJson();
        });

        $router->get('/eloquent/only', function (DataTables $datatables) {
            return $datatables->eloquent(Post::with('user'))
                              ->only(['title', 'user.name'])
                              ->toJson();
        });

        $router->get('/eloquent/formatColumn', function (DataTables $dataTable) {
            return $dataTable->eloquent(User::query())
                             ->formatColumn('created_at', new DateFormatter('Y-m-d'))
                             ->toJson();
        });

        $router->get('/eloquent/formatColumn-closure', function (DataTables $dataTable) {
            return $dataTable->eloquent(User::query())
                             ->formatColumn('created_at', fn ($value, $row) => Carbon::parse($value)->format('Y-m-d'))
                             ->toJson();
        });

        $router->get('/eloquent/formatColumn-fallback', function (DataTables $dataTable) {
            return $dataTable->eloquent(User::query())
                             ->formatColumn('created_at', 'InvalidFormatter::class')
                             ->toJson();
        });
    }
}
