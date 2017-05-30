<?php

namespace Yajra\Datatables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\Datatables\Datatables;
use Yajra\Datatables\Tests\Models\User;
use Yajra\Datatables\Tests\TestCase;

class HasManyRelationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $crawler = $this->call('GET', '/relations/hasMany');
        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('posts', $crawler->json()['data'][0]);
        $this->assertEquals(20, count($crawler->json()['data']));
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $crawler = $this->call('GET', '/relations/hasMany', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'email', 'name' => 'email', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'posts.title', 'name' => 'posts.title', 'searchable' => "true", 'orderable' => "true"],
            ],
            'search'  => ['value' => 'User-19 Post-1'],
        ]);

        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 1,
        ]);
        $this->assertEquals(1, count($crawler->json()['data']));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app['router']->get('/relations/hasMany', function (Datatables $datatables) {
            return $datatables->eloquent(User::with('posts')->select('users.*'))->make('true');
        });
    }
}
