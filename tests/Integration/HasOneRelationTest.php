<?php

namespace Yajra\Datatables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\Datatables\Datatables;
use Yajra\Datatables\Tests\Models\User;
use Yajra\Datatables\Tests\TestCase;

class HasOneRelationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $crawler = $this->call('GET', '/relations/hasOne');
        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('heart', $crawler->json()['data'][0]);
        $this->assertEquals(20, count($crawler->json()['data']));
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $crawler = $this->call('GET', '/relations/hasOne', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'email', 'name' => 'email', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'heart.size', 'name' => 'heart.size', 'searchable' => "true", 'orderable' => "true"],
            ],
            'search'  => ['value' => 'heart-19'],
        ]);

        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 1,
        ]);

        $this->assertEquals(1, count($crawler->json()['data']));
    }

    /** @test */
    public function it_can_sort_using_the_relation_with_pagination()
    {
        $crawler = $this->call('GET', '/relations/hasOne', [
            'columns' => [
                ['data' => 'heart.size', 'name' => 'heart.size', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'name', 'name' => 'name', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'email', 'name' => 'email', 'searchable' => "true", 'orderable' => "true"],
            ],
            'order'   => [
                [
                    'column' => 0,
                    'dir'    => 'desc',
                ],
            ],
            'length'  => 10,
            'start'   => 0,
            'draw'    => 1,
        ]);

        $crawler->assertJson([
            'draw'            => 1,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertEquals('heart-9', $crawler->json()['data'][0]['heart']['size']);
        $this->assertEquals(10, count($crawler->json()['data']));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app['router']->get('/relations/hasOne', function (Datatables $datatables) {
            return $datatables->eloquent(User::with('heart')->select('users.*'))->make('true');
        });
    }
}
