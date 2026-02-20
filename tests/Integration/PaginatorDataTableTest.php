<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Facades\DataTables as DatatablesFacade;
use Yajra\DataTables\PaginatorDataTable;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class PaginatorDataTableTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_paginator_records_without_engine_exception()
    {
        $crawler = $this->call('GET', '/paginator/users');

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertCount(10, $crawler->json('data'));
    }

    #[Test]
    public function it_accepts_length_aware_paginator_using_of_factory()
    {
        $dataTable = DataTables::of(User::query()->paginate(10));
        $response = $dataTable->toJson();

        $this->assertInstanceOf(PaginatorDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(20, $response->getData(true)['recordsTotal']);
        $this->assertEquals(20, $response->getData(true)['recordsFiltered']);
    }

    #[Test]
    public function it_accepts_length_aware_paginator_using_facade()
    {
        $dataTable = DatatablesFacade::of(User::query()->paginate(10));
        $response = $dataTable->toJson();

        $this->assertInstanceOf(PaginatorDataTable::class, $dataTable);
        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/paginator/users', fn (DataTables $dataTables) => $dataTables->of(User::query()->paginate(10))->toJson());
    }
}
