<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\TestCase;

class EloquentJoinTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $response = $this->getJsonResponse();
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 60,
        ]);

        $this->assertArrayHasKey('name', $response->json()['data'][0]);
        $this->assertArrayHasKey('email', $response->json()['data'][0]);
        $this->assertArrayHasKey('title', $response->json()['data'][0]);
        $this->assertCount(60, $response->json()['data']);
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'email-19@example.com'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);

        $this->assertCount(3, $response->json()['data']);
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'name', 'name' => 'users.name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'users.email', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'title', 'name' => 'posts.title', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ];

        return $this->call('GET', '/eloquent/join', array_merge($data, $params));
    }

    /** @test */
    public function it_can_sort_using_the_relation_with_pagination()
    {
        $response = $this->getJsonResponse([
            'order' => [
                [
                    'column' => 1,
                    'dir' => 'desc',
                ],
            ],
            'length' => 10,
            'start' => 0,
            'draw' => 1,
        ]);

        $response->assertJson([
            'draw' => 1,
            'recordsTotal' => 60,
            'recordsFiltered' => 60,
        ]);

        $this->assertEquals('Email-9@example.com', $response->json()['data'][0]['email']);
        $this->assertCount(10, $response->json()['data']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/eloquent/join', function (DataTables $datatables) {
            $builder = Post::query()
                           ->join('users', 'users.id', '=', 'posts.user_id')
                           ->select('users.name', 'users.email', 'posts.title');

            return $datatables->eloquent($builder)->toJson();
        });
    }
}
