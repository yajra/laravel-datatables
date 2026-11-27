<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class HasManyRelationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_escapes_relation_values()
    {
        Post::find(1)->update(['title' => '<a href="#">Allowed</a>']);

        $response = $this->call('GET', '/relations/hasMany');

        $titles = collect($response->json('data'))
            ->pluck('posts')
            ->flatten(1)
            ->pluck('title');

        $this->assertContains(e('<a href="#">Allowed</a>'), $titles);
        $this->assertNotContains('<a href="#">Allowed</a>', $titles);
    }

    #[Test]
    public function it_allows_raw_relation_values_on_exact_nested_paths()
    {
        Post::find(1)->update(['title' => '<a href="#">Allowed</a>']);

        $response = $this->call('GET', '/relations/hasManyRawFirstPostTitle');

        $this->assertSame('<a href="#">Allowed</a>', $response->json('data.0.posts.0.title'));
    }

    #[Test]
    public function it_does_not_allow_raw_relation_values_from_parent_relation_paths()
    {
        Post::find(1)->update(['title' => '<a href="#">Allowed</a>']);

        $response = $this->call('GET', '/relations/hasManyRawPostsRelation');

        $this->assertSame(e('<a href="#">Allowed</a>'), $response->json('data.0.posts.0.title'));
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

        $this->app['router']->get('/relations/hasMany', fn (DataTables $datatables) => $datatables->eloquent(User::with('posts')->select('users.*'))->toJson());

        $this->app['router']->get('/relations/hasManyRawFirstPostTitle', fn (DataTables $datatables) => $datatables->eloquent(User::with('posts')->select('users.*'))
            ->rawColumns(['posts.0.title'])
            ->toJson());

        $this->app['router']->get('/relations/hasManyRawPostsRelation', fn (DataTables $datatables) => $datatables->eloquent(User::with('posts')->select('users.*'))
            ->rawColumns(['posts'])
            ->toJson());

        $this->app['router']->get('/relations/hasManyWithTrashed', fn (DataTables $datatables) => $datatables->eloquent(User::with(['posts' => function ($query) {
            $query->withTrashed();
        }])->select('users.*'))->toJson());

        $this->app['router']->get('/relations/hasManyOnlyTrashed', fn (DataTables $datatables) => $datatables->eloquent(User::with(['posts' => function ($query) {
            $query->onlyTrashed();
        }])->select('users.*'))->toJson());
    }
}
