<?php

namespace Yajra\DataTables\Tests\Integration;

use Yajra\DataTables\DataTables;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\QueryDataTable;
use Yajra\DataTables\Tests\TestCase;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\Facades\DataTables as DatatablesFacade;

class QueryDataTableTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_when_no_parameters_is_passed()
    {
        $crawler = $this->call('GET', '/query/users');
        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
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
            'draw'            => 0,
            'recordsTotal'    => 20,
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
            'draw'            => 0,
            'recordsTotal'    => 1,
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
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 1,
        ]);
    }

    /** @test */
    public function it_accepts_a_query_using_of_factory()
    {
        $dataTable = DataTables::of(DB::table('users'));
        $response  = $dataTable->toJson();
        $this->assertInstanceOf(QueryDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_using_facade()
    {
        $dataTable = DatatablesFacade::of(DB::table('users'));
        $response  = $dataTable->toJson();
        $this->assertInstanceOf(QueryDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_using_facade_query_method()
    {
        $dataTable = DatatablesFacade::query(DB::table('users'));
        $response  = $dataTable->toJson();
        $this->assertInstanceOf(QueryDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_using_deprecated_facade_query_builder_method()
    {
        $dataTable = DatatablesFacade::queryBuilder(DB::table('users'));
        $response  = $dataTable->toJson();
        $this->assertInstanceOf(QueryDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_using_ioc_container()
    {
        $dataTable = app('datatables')->query(DB::table('users'));
        $response  = $dataTable->toJson();
        $this->assertInstanceOf(QueryDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_query_using_ioc_container_factory()
    {
        $dataTable = app('datatables')->of(DB::table('users'));
        $response  = $dataTable->toJson();
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
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 1,
        ]);
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
            'draw'            => 0,
            'recordsTotal'    => 20,
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
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 1,
        ]);

        $queries = $crawler->json()['queries'];
        $this->assertContains('"1" = ?', $queries[1]['query']);
    }

    protected function setUp()
    {
        parent::setUp();

        $route = $this->app['router'];

        $route->get('/query/users', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))->toJson();
        });

        $route->get('/query/simple', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))->skipTotalRecords()->toJson();
        });

        $route->get('/query/addColumn', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('foo', 'bar')
                             ->toJson();
        });

        $route->get('/query/indexColumn', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addIndexColumn()
                             ->toJson();
        });

        $route->get('/query/filterColumn', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('foo', 'bar')
                             ->filterColumn('foo', function (Builder $builder, $keyword) {
                                 $builder->where('1', $keyword);
                             })
                             ->toJson();
        });

        $route->get('/query/xss-add', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('foo', '<a href="#">Allowed</a>')
                             ->addColumn('bar', function () {
                                 return '<a href="#">Allowed</a>';
                             })
                             ->toJson();
        });

        $route->get('/query/xss-edit', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->editColumn('name', '<a href="#">Allowed</a>')
                             ->editColumn('email', function () {
                                 return '<a href="#">Allowed</a>';
                             })
                             ->toJson();
        });

        $route->get('/query/xss-raw', function (DataTables $dataTable) {
            return $dataTable->query(DB::table('users'))
                             ->addColumn('foo', '<a href="#">Allowed</a>')
                             ->editColumn('name', '<a href="#">Allowed</a>')
                             ->editColumn('email', function () {
                                 return '<a href="#">Allowed</a>';
                             })
                             ->rawColumns(['name', 'email'])
                             ->toJson();
        });
    }
}
