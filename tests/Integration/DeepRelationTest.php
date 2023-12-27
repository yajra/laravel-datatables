<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\TestCase;

class DeepRelationTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/deep', function (DataTables $datatables) {
            $query = Post::with('user.roles')->select('posts.*');

            return $datatables
                ->eloquent($query)
                ->toJson();
        });
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'user.roles.role', 'name' => 'user.roles.role', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'user.name', 'name' => 'user.name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'user.email', 'name' => 'user.email', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ];

        return $this->call('GET', '/relations/deep', array_merge($data, $params));
    }
}
