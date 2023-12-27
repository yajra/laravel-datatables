<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Heart;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class HasOneRelationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $response = $this->call('GET', '/relations/hasOne');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('heart', $response->json()['data'][0]);
        $this->assertCount(20, $response->json()['data']);
    }

    /** @test */
    public function it_returns_all_records_with_the_deleted_relation_when_called_with_withtrashed_parameter()
    {
        Heart::find(1)->delete();

        $response = $this->call('GET', '/relations/hasOneWithTrashed');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('heart', $response->json()['data'][0]);
        $this->assertArrayHasKey('heart', $response->json()['data'][1]);
        $this->assertNotEmpty($response->json()['data'][0]['heart']);
        $this->assertNotEmpty($response->json()['data'][1]['heart']);
    }

    /** @test */
    public function it_returns_all_records_with_the_only_deleted_relation_when_called_with_onlytrashed_parameter()
    {
        Heart::find(1)->delete();

        $response = $this->call('GET', '/relations/hasOneOnlyTrashed');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('heart', $response->json()['data'][0]);
        $this->assertArrayHasKey('heart', $response->json()['data'][1]);
        $this->assertNotEmpty($response->json()['data'][0]['heart']);
        $this->assertEmpty($response->json()['data'][1]['heart']);
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'heart-19'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 1,
        ]);

        $this->assertCount(1, $response->json()['data']);
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'heart.size', 'name' => 'heart.size', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ];

        return $this->call('GET', '/relations/hasOne', array_merge($data, $params));
    }

    /** @test */
    public function it_can_sort_using_the_relation_with_pagination()
    {
        $response = $this->getJsonResponse([
            'order' => [
                [
                    'column' => 0,
                    'dir' => 'desc',
                ],
            ],
            'length' => 10,
            'start' => 0,
            'draw' => 1,
        ]);

        $response->assertJson([
            'draw' => 1,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertEquals('heart-9', $response->json()['data'][0]['heart']['size']);
        $this->assertCount(10, $response->json()['data']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/hasOne', function (DataTables $datatables) {
            return $datatables->eloquent(User::with('heart')->select('users.*'))->toJson();
        });

        $this->app['router']->get('/relations/hasOneWithTrashed', function (DataTables $datatables) {
            return $datatables->eloquent(User::with(['heart' => function ($query) {
                $query->withTrashed();
            }])->select('users.*'))->toJson();
        });

        $this->app['router']->get('/relations/hasOneOnlyTrashed', function (DataTables $datatables) {
            return $datatables->eloquent(User::with(['heart' => function ($query) {
                $query->onlyTrashed();
            }])->select('users.*'))->toJson();
        });
    }
}
