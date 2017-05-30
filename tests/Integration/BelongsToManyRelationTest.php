<?php

namespace Yajra\Datatables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\Datatables\Datatables;
use Yajra\Datatables\Tests\Models\User;
use Yajra\Datatables\Tests\TestCase;

class BelongsToManyRelationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $crawler = $this->call('GET', '/relations/belongsToMany');
        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('roles', $crawler->json()['data'][0]);
        $this->assertEquals(20, count($crawler->json()['data']));
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $crawler = $this->call('GET', '/relations/belongsToMany', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'email', 'name' => 'email', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'roles', 'name' => 'roles.role', 'searchable' => "true", 'orderable' => "true"],
            ],
            'search'  => ['value' => 'Administrator'],
        ]);

        $crawler->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 10,
        ]);

        $this->assertEquals(10, count($crawler->json()['data']));
    }

    /** @test */
    public function it_can_sort_using_the_relation_with_pagination()
    {
        $crawler = $this->call('GET', '/relations/belongsToMany', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'email', 'name' => 'email', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'roles', 'name' => 'roles.role', 'searchable' => "true", 'orderable' => "true"],
            ],
            'order'   => [
                [
                    'column' => 2,
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

        $this->assertEquals(10, count($crawler->json()['data']));

        $this->assertEquals(2, count($crawler->json()['data'][0]['roles']));
        $this->assertEquals('Administrator', $crawler->json()['data'][0]['roles'][0]['role']);
        $this->assertEquals('User', $crawler->json()['data'][0]['roles'][1]['role']);
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app['router']->get('/relations/belongsToMany', function (Datatables $datatables) {
            return $datatables->eloquent(User::with('roles')->select('users.*'))->make('true');
        });
    }
}
