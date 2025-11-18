<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class HasManyDeepRelationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $response = $this->call('GET', '/relations/hasManyDeep');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertCount(20, $response->json()['data']);
    }

    #[Test]
    public function it_can_search_has_many_deep_relation()
    {
        $response = $this->call('GET', '/relations/hasManyDeepSearchRelation', [
            'columns' => [
                [
                    'data' => 'comments.content',
                    'searchable' => true,
                    'search' => [
                        'value' => 'Comment-1',
                    ],
                ],
            ],
        ]);

        // HasManyDeep can return multiple rows per user (one per comment)
        // So we expect at least some results, but the exact count depends on the join
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
        ]);

        $this->assertGreaterThanOrEqual(20, $response->json()['recordsFiltered']);
        $this->assertGreaterThanOrEqual(20, count($response->json()['data']));
    }

    #[Test]
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'Comment-1'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertCount(20, $response->json()['data']);
    }

    #[Test]
    public function it_can_order_by_has_many_deep_relation_column()
    {
        $response = $this->call('GET', '/relations/hasManyDeep', [
            'columns' => [
                ['data' => 'comments.content', 'name' => 'comments.content', 'searchable' => true, 'orderable' => true],
                ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true],
            ],
            'order' => [
                [
                    'column' => 0,
                    'dir' => 'asc',
                ],
            ],
        ]);

        // HasManyDeep can return multiple rows per user when ordering by related column
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
        ]);

        $this->assertGreaterThanOrEqual(20, $response->json()['recordsFiltered']);
        $this->assertGreaterThanOrEqual(20, count($response->json()['data']));
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true],
                ['data' => 'comments.content', 'name' => 'comments.content', 'searchable' => true, 'orderable' => true],
            ],
        ];

        return $this->call('GET', '/relations/hasManyDeep', array_merge($data, $params));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/hasManyDeep', fn (DataTables $datatables) => $datatables->eloquent(User::with('comments')->select('users.*'))->toJson());

        $this->app['router']->get('/relations/hasManyDeepSearchRelation', fn (DataTables $datatables) => $datatables->eloquent(User::with('comments'))->toJson());
    }
}

