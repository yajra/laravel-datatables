<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\TestCase;

class SnakeCaseRelationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_can_perform_global_search_on_a_snake_case_relation_name()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'Email-19@example.com'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);

        $this->assertCount(3, $response->json()['data']);
    }

    #[Test]
    public function it_can_perform_column_search_on_a_snake_case_relation_name()
    {
        $response = $this->getJsonResponse([
            'columns' => [
                [
                    'data' => 'post_user.email',
                    'name' => 'post_user.email',
                    'searchable' => 'true',
                    'orderable' => 'true',
                    'search' => ['value' => 'Email-19@example.com'],
                ],
            ],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);

        $this->assertCount(3, $response->json()['data']);
    }

    #[Test]
    public function it_can_sort_on_a_snake_case_relation_name()
    {
        $response = $this->getJsonResponse([
            'order' => [
                ['column' => 0, 'dir' => 'desc'],
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

        $this->assertEquals('Email-9@example.com', $response->json()['data'][0]['post_user']['email']);
    }

    #[Test]
    public function it_still_resolves_a_relation_that_is_named_in_snake_case()
    {
        $this->app['router']->get('/relations/snakeCaseDefined', fn (DataTables $datatables) => $datatables
            ->eloquent(Post::with('user')->select('posts.*'))
            ->toJson());

        $response = $this->call('GET', '/relations/snakeCaseDefined', [
            'columns' => [
                ['data' => 'user.email', 'name' => 'user.email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => 'Email-19@example.com'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);
    }

    #[Test]
    public function it_can_perform_global_search_on_a_nested_snake_case_relation_name()
    {
        $response = $this->call('GET', '/relations/nestedSnakeCase', [
            'columns' => [
                [
                    'data' => 'user.nested_filtered_heart.size',
                    'name' => 'user.nested_filtered_heart.size',
                    'searchable' => 'true',
                    'orderable' => 'true',
                ],
            ],
            'search' => ['value' => 'heart-2'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);
    }

    #[Test]
    public function it_can_perform_column_search_on_a_nested_snake_case_relation_name()
    {
        $response = $this->call('GET', '/relations/nestedSnakeCase', [
            'columns' => [
                [
                    'data' => 'user.nested_filtered_heart.size',
                    'name' => 'user.nested_filtered_heart.size',
                    'searchable' => 'true',
                    'orderable' => 'true',
                    'search' => ['value' => 'heart-2'],
                ],
            ],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);
    }

    #[Test]
    public function it_can_sort_on_a_nested_snake_case_relation_name()
    {
        $response = $this->call('GET', '/relations/nestedSnakeCase', [
            'columns' => [
                [
                    'data' => 'user.nested_filtered_heart.size',
                    'name' => 'user.nested_filtered_heart.size',
                    'searchable' => 'true',
                    'orderable' => 'true',
                ],
            ],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'start' => 0,
            'length' => 10,
            'draw' => 1,
        ]);

        $response->assertJson([
            'draw' => 1,
            'recordsTotal' => 60,
            'recordsFiltered' => 60,
        ]);

        $this->assertEquals('heart-3', $response->json()['data'][0]['user']['nested_filtered_heart']['size']);
    }

    #[Test]
    public function it_can_search_a_nested_snake_case_relation_that_is_not_eager_loaded()
    {
        $this->app['router']->get('/relations/nestedNotEagerLoaded', fn (DataTables $datatables) => $datatables
            ->eloquent(Post::with('user')->select('posts.*'))
            ->toJson());

        $response = $this->call('GET', '/relations/nestedNotEagerLoaded', [
            'columns' => [
                [
                    'data' => 'user.nested_filtered_heart.size',
                    'name' => 'user.nested_filtered_heart.size',
                    'searchable' => 'true',
                    'orderable' => 'true',
                ],
            ],
            'search' => ['value' => 'heart-2'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                [
                    'data' => 'post_user.email',
                    'name' => 'post_user.email',
                    'searchable' => 'true',
                    'orderable' => 'true',
                ],
            ],
        ];

        return $this->call('GET', '/relations/snakeCase', array_merge($data, $params));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/snakeCase', fn (DataTables $datatables) => $datatables
            ->eloquent(Post::with('postUser')->select('posts.*'))
            ->toJson());

        $this->app['router']->get('/relations/nestedSnakeCase', fn (DataTables $datatables) => $datatables
            ->eloquent(Post::with('user.nestedFilteredHeart')->select('posts.*'))
            ->toJson());
    }
}
