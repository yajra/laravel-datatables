<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class HasManyRelationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $response = $this->call('GET', '/relations/hasMany');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('posts', $response->json()['data'][0]);
        $this->assertCount(20, $response->json()['data']);
    }

    /** @test */
    public function it_returns_all_records_with_deleted_relations_when_called_with_withtrashed_parameter()
    {
        Post::find(1)->delete();

        $response = $this->call('GET', '/relations/hasManyWithTrashed');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('posts', $response->json()['data'][0]);
        $this->assertCount(3, $response->json()['data'][0]['posts']);
    }

    /** @test */
    public function it_returns_all_records_with_only_deleted_relations_when_called_with_onlytrashed_parameter()
    {
        Post::find(1)->delete();
        $response = $this->call('GET', '/relations/hasManyOnlyTrashed');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('posts', $response->json()['data'][0]);
        $this->assertCount(1, $response->json()['data'][0]['posts']);
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'User-19 Post-1'],
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
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'posts.title', 'name' => 'posts.title', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ];

        return $this->call('GET', '/relations/hasMany', array_merge($data, $params));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/hasMany', function (DataTables $datatables) {
            return $datatables->eloquent(User::with('posts')->select('users.*'))->toJson();
        });

        $this->app['router']->get('/relations/hasManyWithTrashed', function (DataTables $datatables) {
            return $datatables->eloquent(User::with(['posts' => function ($query) {
                $query->withTrashed();
            }])->select('users.*'))->toJson();
        });

        $this->app['router']->get('/relations/hasManyOnlyTrashed', function (DataTables $datatables) {
            return $datatables->eloquent(User::with(['posts' => function ($query) {
                $query->onlyTrashed();
            }])->select('users.*'))->toJson();
        });
    }
}
