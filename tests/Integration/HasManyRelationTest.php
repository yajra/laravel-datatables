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
        $response = $this->call('GET', '/relations/hasMany');
        $response->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('posts', $response->json()['data'][0]);
        $this->assertEquals(20, count($response->json()['data']));
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'User-19 Post-1'],
        ]);

        $response->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 1,
        ]);
        $this->assertEquals(1, count($response->json()['data']));
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'email', 'name' => 'email', 'searchable' => "true", 'orderable' => "true"],
                ['data' => 'posts.title', 'name' => 'posts.title', 'searchable' => "true", 'orderable' => "true"],
            ],
        ];

        return $this->call('GET', '/relations/hasMany', array_merge($data, $params));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app['router']->get('/relations/hasMany', function (Datatables $datatables) {
            return $datatables->eloquent(User::with('posts')->select('users.*'))->make('true');
        });
    }
}
