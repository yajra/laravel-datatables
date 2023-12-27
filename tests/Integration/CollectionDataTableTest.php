<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Facades\DataTables as DatatablesFacade;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class CollectionDataTableTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_when_no_parameters_is_passed()
    {
        $crawler = $this->call('GET', '/collection/users');
        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);
    }

    /** @test */
    public function it_returns_zero_filtered_records_on_empty_collection()
    {
        $crawler = $this->call('GET', '/collection/empty');
        $crawler->assertExactJson([
            'data' => [],
            'draw' => 0,
            'input' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
        ]);
    }

    /** @test */
    public function it_can_perform_global_search()
    {
        $crawler = $this->call('GET', '/collection/users', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => 'Record 19'],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 1,
        ]);
    }

    /** @test */
    public function it_accepts_a_model_collection_using_of_factory()
    {
        $dataTable = DataTables::of(User::all());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(CollectionDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_collection_using_of_factory()
    {
        $dataTable = DataTables::of(collect());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(CollectionDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_collection_using_facade()
    {
        $dataTable = DatatablesFacade::of(User::all());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(CollectionDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_collection_using_facade()
    {
        $dataTable = DatatablesFacade::of(collect());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(CollectionDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_accepts_a_model_using_ioc_container()
    {
        $dataTable = app('datatables')->collection(User::all());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(CollectionDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_can_sort_case_insensitive_strings()
    {
        config()->set('app.debug', false);
        request()->merge([
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'order' => [['column' => 0, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'draw' => 1,
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
        $response = $dataTable->toJson();

        $this->assertEquals([
            'draw' => 1,
            'recordsTotal' => 6,
            'recordsFiltered' => 6,
            'data' => [
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
    public function it_can_sort_numeric_strings()
    {
        config()->set('app.debug', false);
        request()->merge([
            'columns' => [
                ['data' => 'amount', 'name' => 'amount', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'order' => [['column' => 0, 'dir' => 'asc']],
            'start' => 0,
            'length' => 10,
            'draw' => 1,
        ]);

        $collection = collect([
            ['amount' => '12'],
            ['amount' => '7'],
            ['amount' => '-8'],
            ['amount' => '0'],
            ['amount' => '-3'],
            ['amount' => '8'],
        ]);

        $dataTable = app('datatables')->collection($collection);
        /** @var JsonResponse $response */
        $response = $dataTable->toJson();

        $this->assertEquals([
            'draw' => 1,
            'recordsTotal' => 6,
            'recordsFiltered' => 6,
            'data' => [
                ['amount' => '-8'],
                ['amount' => '-3'],
                ['amount' => '0'],
                ['amount' => '7'],
                ['amount' => '8'],
                ['amount' => '12'],
            ],
        ], $response->getData(true));
    }

    /** @test */
    public function it_accepts_a_model_using_ioc_container_factory()
    {
        $dataTable = app('datatables')->of(User::all());
        $response = $dataTable->toJson();
        $this->assertInstanceOf(CollectionDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    /** @test */
    public function it_can_search_on_added_columns()
    {
        config()->set('app.debug', false);
        request()->merge([
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'foo',  'name' => 'foo', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'order' => [['column' => 0, 'dir' => 'asc']],
            'start' => 0,
            'search' => [
                'value' => 'bar aaa',
            ],
            'length' => 10,
            'draw' => 1,
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
        $response = $dataTable->addColumn('foo', 'bar {{$name}}')->toJson();

        $this->assertEquals([
            'draw' => 1,
            'recordsTotal' => 6,
            'recordsFiltered' => 1,
            'data' => [
                ['name' => 'aaa', 'foo' => 'bar aaa'],
            ],
        ], $response->getData(true));
    }

    /** @test */
    public function it_accepts_array_data_source()
    {
        $source = [
            ['id' => 1, 'name' => 'foo'],
            ['id' => 2, 'name' => 'bar'],
        ];
        $dataTable = app('datatables')->of($source);
        $response = $dataTable->toJson();
        $this->assertInstanceOf(CollectionDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/collection/users', function (DataTables $datatables) {
            return $datatables->collection(User::all())->toJson();
        });

        $this->app['router']->get('/collection/empty', function (DataTables $datatables) {
            return $datatables->collection([])->toJson();
        });
    }
}
