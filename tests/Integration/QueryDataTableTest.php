<?php

namespace Yajra\DataTables\Tests\Integration;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Facades\DataTables as DatatablesFacade;
use Yajra\DataTables\QueryDataTable;
use Yajra\DataTables\Tests\Formatters\DateFormatter;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class QueryDataTableTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_can_set_total_records()
    {
        $crawler = $this->call('GET', '/set-total-records');
        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 10,
            'recordsFiltered' => 10,
        ]);
    }

    /** @test */
    public function it_can_set_zero_total_records()
    {
        $crawler = $this->call('GET', '/zero-total-records');
        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
        ]);
    }

    /** @test */
    public function it_can_set_total_filtered_records()
    {
        $crawler = $this->call('GET', '/set-filtered-records');
        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 10,
        ]);
    }

    /** @test */
    public function it_returns_all_records_when_no_parameters_is_passed()
    {
        $crawler = $this->call('GET', '/query/users');
        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);
    }

    /** @test */
    public function it_can_perform_global_search()
    {
        $crawler = $this->call('GET', '/query/users', [
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
    public function it_can_skip_total_records_count_query()
    {
        $crawler = $this->call('GET', '/query/simple', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => 'Record-19'],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 0,
            'recordsFiltered' => 1,
        ]);
    }

    /** @test */
    public function it_can_perform_multiple_term_global_search()
    {
        $crawler = $this->call('GET', '/query/users', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => 'Record-19 Email-19'],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 1,
        ]);
    }

    /** @test */
    public function it_accepts_a_query_using_of_factory()
    {
        $dataTable = DataTables::of(DB::table('users'));
        $response = $dataTable->toJson();
        $this->assertInstanceOf(QueryDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_using_facade()
    {
        $dataTable = DatatablesFacade::of(DB::table('users'));
        $response = $dataTable->toJson();
        $this->assertInstanceOf(QueryDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_using_facade_query_method()
    {
        $dataTable = DatatablesFacade::query(DB::table('users'));
        $response = $dataTable->toJson();
        $this->assertInstanceOf(QueryDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_using_ioc_container()
    {
        $dataTable = app('datatables')->query(DB::table('users'));
        $response = $dataTable->toJson();
        $this->assertInstanceOf(QueryDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_using_ioc_container_factory()
    {
        $dataTable = app('datatables')->of(DB::table('users'));
        $response = $dataTable->toJson();
        $this->assertInstanceOf(QueryDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_does_not_allow_search_on_added_columns()
    {
        $crawler = $this->call('GET', '/query/addColumn', [
            'columns' => [
                ['data' => 'foo', 'name' => 'foo', 'searchable' => 'true', 'orderable' => 'true'],
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
    public function it_returns_only_the_selected_columns()
    {
        $json = $this->call('GET', '/query/only')->json();
        $this->assertArrayNotHasKey('id', $json['data'][0]);
        $this->assertArrayHasKey('name', $json['data'][0]);
    }

    /** @test */
    public function it_edit_only_the_selected_columns_after_using_editOnlySelectedColumns()
    {
        $json = $this->call('GET', '/query/edit-columns', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ])->json();

        $this->assertEquals('edited', $json['data'][0]['name']);
        $this->assertEquals('edited', $json['data'][0]['id']);
        $this->assertNotEquals('edited', $json['data'][0]['email']);
    }

    /** @test */
    public function it_does_not_allow_raw_html_on_added_columns()
    {
        $json = $this->call('GET', '/query/xss-add')->json();
        $this->assertNotEquals('<a href="#">Allowed</a>', $json['data'][0]['foo']);
        $this->assertNotEquals('<a href="#">Allowed</a>', $json['data'][0]['bar']);
    }

    /** @test */
    public function it_does_not_allow_raw_html_on_edited_columns()
    {
        $json = $this->call('GET', '/query/xss-edit')->json();
        $this->assertNotEquals('<a href="#">Allowed</a>', $json['data'][0]['name']);
        $this->assertNotEquals('<a href="#">Allowed</a>', $json['data'][0]['email']);
    }

    /** @test */
    public function it_allows_raw_html_on_specified_columns()
    {
        $json = $this->call('GET', '/query/xss-raw')->json();
        $this->assertNotEquals('<a href="#">Allowed</a>', $json['data'][0]['foo']);
        $this->assertEquals('<a href="#">Allowed</a>', $json['data'][0]['name']);
        $this->assertEquals('<a href="#">Allowed</a>', $json['data'][0]['email']);
    }

    /** @test */
    public function it_can_return_auto_index_column()
    {
        $crawler = $this->call('GET', '/query/indexColumn', [
            'columns' => [
                ['data' => 'DT_RowIndex', 'name' => 'index', 'searchable' => 'false', 'orderable' => 'false'],
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

        $this->assertArrayHasKey('DT_RowIndex', $crawler->json()['data'][0]);
    }

    /** @test */
    public function it_allows_search_on_added_column_with_custom_filter_handler()
    {
        $crawler = $this->call('GET', '/query/filterColumn', [
            'columns' => [
                ['data' => 'foo', 'name' => 'foo', 'searchable' => 'true', 'orderable' => 'true'],
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

        $queries = $crawler->json()['queries'];
        $this->assertStringContainsString('"1" = ?', $queries[1]['query']);
    }

    /** @test */
    public function it_returns_search_panes_options()
    {
        $crawler = $this->call('GET', '/query/search-panes');

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
            'searchPanes' => [
                'options' => [
                    'id' => [],
                ],
            ],
        ]);

        $options = $crawler->json()['searchPanes']['options'];

        $this->assertEquals(count($options['id']), 20);
    }

    /** @test */
    public function it_performs_search_using_search_panes()
    {
        $crawler = $this->call('GET', '/query/search-panes', [
            'searchPanes' => [
                'id' => [1, 2],
            ],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 2,
        ]);
    }

    /** @test */
    public function it_allows_column_search_added_column_with_custom_filter_handler()
    {
        $crawler = $this->call('GET', '/query/blacklisted-filter', [
            'columns' => [
                [
                    'data' => 'foo',
                    'name' => 'foo',
                    'searchable' => 'true',
                    'orderable' => 'true',
                    'search' => ['value' => 'Record-1'],
                ],
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => ''],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 1,
        ]);
    }

    /** @test */
    public function it_can_return_formatted_columns()
    {
        $crawler = $this->call('GET', '/query/formatColumn');

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $user = DB::table('users')->find(1);
        $data = $crawler->json('data')[0];

        $this->assertTrue(isset($data['created_at']));
        $this->assertTrue(isset($data['created_at_formatted']));

        $this->assertEquals($user->created_at, $data['created_at']);
        $this->assertEquals(Carbon::parse($user->created_at)->format('Y-m-d'), $data['created_at_formatted']);
    }

    /** @test */
    public function it_can_return_added_column_with_dependency_injection()
    {
        $crawler = $this->call('GET', '/closure-di');

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $user = DB::table('users')->find(1);
        $data = $crawler->json('data')[0];

        $this->assertTrue(isset($data['name']));
        $this->assertTrue(isset($data['name_di']));

        $this->assertEquals($user->name.'_di', $data['name_di']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app['router'];

        $router->get('/query/users', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))->toJson();
        });

        $router->get('/query/formatColumn', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->formatColumn('created_at', new DateFormatter('Y-m-d'))
                             ->toJson();
        });

        $router->get('/query/simple', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))->skipTotalRecords()->toJson();
        });

        $router->get('/query/addColumn', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('foo', 'bar')
                             ->toJson();
        });

        $router->get('/query/indexColumn', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addIndexColumn()
                             ->toJson();
        });

        $router->get('/query/filterColumn', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('foo', 'bar')
                             ->filterColumn('foo', function (Builder $builder, $keyword) {
                                 $builder->where('1', $keyword);
                             })
                             ->toJson();
        });

        $router->get('/query/blacklisted-filter', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('foo', 'bar')
                             ->filterColumn('foo', function (Builder $builder, $keyword) {
                                 $builder->where('name', $keyword);
                             })
                             ->blacklist(['foo'])
                             ->toJson();
        });

        $router->get('/query/only', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('foo', 'bar')
                             ->only(['name'])
                             ->toJson();
        });

        $router->get('/query/edit-columns', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->editColumn('id', function () {
                                 return 'edited';
                             })
                             ->editOnlySelectedColumns()
                             ->editColumn('name', function () {
                                 return 'edited';
                             })
                             ->editColumn('email', function () {
                                 return 'edited';
                             })
                             ->toJson();
        });

        $router->get('/query/xss-add', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('foo', '<a href="#">Allowed</a>')
                             ->addColumn('bar', function () {
                                 return '<a href="#">Allowed</a>';
                             })
                             ->toJson();
        });

        $router->get('/query/xss-edit', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->editColumn('name', '<a href="#">Allowed</a>')
                             ->editColumn('email', function () {
                                 return '<a href="#">Allowed</a>';
                             })
                             ->toJson();
        });

        $router->get('/query/xss-raw', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('foo', '<a href="#">Allowed</a>')
                             ->editColumn('name', '<a href="#">Allowed</a>')
                             ->editColumn('email', function () {
                                 return '<a href="#">Allowed</a>';
                             })
                             ->rawColumns(['name', 'email'])
                             ->toJson();
        });

        $router->get('/query/search-panes', function (DataTables $dataTable) {
            $options = User::select('id as value', 'name as label')->get();

            return $dataTable->query(DB::table('users'))
                             ->searchPane('id', $options)
                             ->toJson();
        });

        $router->get('/set-total-records', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->setTotalRecords(10)
                             ->toJson();
        });

        $router->get('/zero-total-records', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->setTotalRecords(0)
                             ->toJson();
        });

        $router->get('/set-filtered-records', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->setFilteredRecords(10)
                             ->toJson();
        });

        $router->get('/closure-di', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('name_di', function ($user, User $u) {
                                 return $u->newQuery()->find($user->id)->name.'_di';
                             })
                             ->toJson();
        });
    }
}
