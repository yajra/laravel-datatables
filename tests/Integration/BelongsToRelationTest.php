<?php

namespace Yajra\Datatables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\Datatables\Datatables;
use Yajra\Datatables\Tests\Models\Post;
use Yajra\Datatables\Tests\TestCase;

class BelongsToRelationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $crawler = $this->call('GET', '/relations/belongsTo');
        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 60,
            'recordsFiltered' => 60,
        ]);

        $this->assertArrayHasKey('user', $crawler->json()['data'][0]);
        $this->assertEquals(60, count($crawler->json()['data']));
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $crawler = $this->call('GET', '/relations/belongsTo', [
            'columns' => [
                ['data' => 'user.name', 'name' => 'user.name', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'user.email', 'name' => 'user.email', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'title', 'name' => 'posts.title', 'searchable' => "true", 'orderable' => "true"],
            ],
            'search'  => ['value' => 'email-19@example.com'],
        ]);

        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 60,
            'recordsFiltered' => 3,
        ]);

        $this->assertEquals(3, count($crawler->json()['data']));
    }

    /** @test */
    public function it_can_sort_using_the_relation_with_pagination()
    {
        $crawler = $this->call('GET', '/relations/belongsTo', [
            'columns' => [
                ['data' => 'user.name', 'name' => 'user.name', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'user.email', 'name' => 'user.email', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'title', 'name' => 'posts.title', 'searchable' => "true", 'orderable' => "true"],
            ],
            'order'   => [
                [
                    'column' => 1,
                    'dir'    => 'desc',
                ],
            ],
            'length'  => 10,
            'start'   => 0,
            'draw'    => 1,
        ]);

        $crawler->assertJson([
            'draw'            => 1,
            'recordsTotal'    => 60,
            'recordsFiltered' => 60,
        ]);

        $this->assertEquals('Email-9@example.com', $crawler->json()['data'][0]['user']['email']);
        $this->assertEquals(10, count($crawler->json()['data']));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app['router']->get('/relations/belongsTo', function (Datatables $datatables) {
            return $datatables->eloquent(Post::with('user')->select('posts.*'))->make('true');
        });
    }
}
