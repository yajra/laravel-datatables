<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\TestCase;

class CustomOrderTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_can_order_with_custom_order()
    {
        $response = $this->getJsonResponse([
            'order' => [
                [
                    'column' => 0,
                    'dir' => 'asc',
                ],
            ],
        ]);

        $this->assertEquals(
            $response->json()['data'][0]['user']['id'],
            collect($response->json()['data'])->pluck('user.id')->max()
        );
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'user.id', 'name' => 'user.id', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'title', 'name' => 'posts.title', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'length' => 10,
            'start' => 0,
            'draw' => 1,
        ];

        return $this->call(
            'GET',
            '/relations/belongsTo',
            array_merge($data, $params)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/belongsTo', function (DataTables $datatables) {
            return $datatables->eloquent(Post::with('user')->select('posts.*'))
                              ->orderColumn('user.id', function ($query, $order) {
                                  $query->orderBy('users.id', $order == 'desc' ? 'asc' : 'desc');
                              })
                              ->toJson();
        });
    }
}
