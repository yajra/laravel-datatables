<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Heart;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\TestCase;

class HasOneThroughTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $response = $this->call('GET', '/relations/hasOneThrough');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 60,
        ]);

        $this->assertArrayHasKey('heart', $response->json()['data'][0]);
        $this->assertCount(60, $response->json()['data']);
    }

    /** @test */
    public function it_can_search_has_one_through_relation()
    {
        $response = $this->call('GET', '/relations/hasOneThroughSearchRelation', [
            'columns' => [
                [
                    'data' => 'heart.size',
                    'searchable' => true,
                    'search' => [
                        'value' => 'heart-1',
                    ],
                ],
            ],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 33,
        ]);

        $this->assertArrayHasKey('heart', $response->json()['data'][0]);
        $this->assertCount(33, $response->json()['data']);
    }

    /** @test */
    public function it_returns_all_records_with_the_deleted_relation_when_called_with_withtrashed_parameter()
    {
        Heart::find(1)->delete();

        $response = $this->call('GET', '/relations/hasOneThroughWithTrashed');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 60,
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

        $response = $this->call('GET', '/relations/hasOneThroughOnlyTrashed');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 60,
        ]);

        $this->assertArrayHasKey('heart', $response->json()['data'][0]);
        $this->assertArrayHasKey('heart', $response->json()['data'][1]);
        $this->assertArrayHasKey('heart', $response->json()['data'][2]);
        $this->assertNotEmpty($response->json()['data'][0]['heart']);
        $this->assertNotEmpty($response->json()['data'][1]['heart']);
        $this->assertNotEmpty($response->json()['data'][2]['heart']);
        $this->assertEmpty($response->json()['data'][3]['heart']);
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'heart-19'],
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
                ['data' => 'title', 'name' => 'title', 'searchable' => true, 'orderable' => true],
                ['data' => 'heart.size', 'name' => 'heart.size', 'searchable' => true, 'orderable' => true],
            ],
        ];

        return $this->call('GET', '/relations/hasOneThrough', array_merge($data, $params));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/hasOneThrough', function (DataTables $datatables) {
            return $datatables->eloquent(Post::with('heart')->select('posts.*'))->toJson();
        });

        $this->app['router']->get('/relations/hasOneThroughSearchRelation', function (DataTables $datatables) {
            return $datatables->eloquent(Post::with('heart'))->addColumns(['hearts.size'])->toJson();
        });

        $this->app['router']->get('/relations/hasOneThroughWithTrashed', function (DataTables $datatables) {
            return $datatables->eloquent(Post::with(['heart' => function ($query) {
                $query->withTrashed();
            }])->select('posts.*'))->toJson();
        });

        $this->app['router']->get('/relations/hasOneThroughOnlyTrashed', function (DataTables $datatables) {
            return $datatables->eloquent(Post::with(['heart' => function ($query) {
                $query->onlyTrashed();
            }])->select('posts.*'))->toJson();
        });
    }
}
