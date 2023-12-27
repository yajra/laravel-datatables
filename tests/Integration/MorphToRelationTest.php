<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\HumanUser;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

/**
 * Class MorphToRelationTest.
 */
class MorphToRelationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $response = $this->call('GET', '/relations/morphTo');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('user', $response->json()['data'][0]);
        $this->assertCount(20, $response->json()['data']);
    }

    /** @test */
    public function it_returns_all_records_with_the_deleted_relation_when_called_with_withtrashed_parameter()
    {
        HumanUser::find(1)->delete();

        $response = $this->call('GET', '/relations/morphToWithTrashed');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('user', $response->json()['data'][0]);
        $this->assertArrayHasKey('user', $response->json()['data'][1]);
        $this->assertNotEmpty($response->json()['data'][0]['user']);
        $this->assertNotEmpty($response->json()['data'][1]['user']);
    }

    /** @test */
    public function it_returns_all_records_with_the_only_deleted_relation_when_called_with_onlytrashed_parameter()
    {
        HumanUser::find(1)->delete();

        $response = $this->call('GET', '/relations/morphToOnlyTrashed');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('user', $response->json()['data'][0]);
        $this->assertArrayHasKey('user', $response->json()['data'][1]);
        $this->assertNotEmpty($response->json()['data'][0]['user']);
        $this->assertEmpty($response->json()['data'][1]['user']);
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'Animal'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 10,
        ]);

        $this->assertCount(10, $response->json()['data']);
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'user.name', 'name' => 'user.name', 'searchable' => 'true', 'orderable' => 'false'],
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ];

        return $this->call('GET', '/relations/morphTo', array_merge($data, $params));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/morphTo', function (DataTables $datatables) {
            return $datatables->eloquent(User::with('user')->select('users.*'))->toJson();
        });

        $this->app['router']->get('/relations/morphToWithTrashed', function (DataTables $datatables) {
            return $datatables->eloquent(User::with(['user' => function ($query) {
                $query->withTrashed();
            }])->select('users.*'))->toJson();
        });

        $this->app['router']->get('/relations/morphToOnlyTrashed', function (DataTables $datatables) {
            return $datatables->eloquent(User::with(['user' => function ($query) {
                $query->onlyTrashed();
            }])->select('users.*'))->toJson();
        });
    }
}
