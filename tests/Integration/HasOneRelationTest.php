<?php

namespace Yajra\DataTables\Tests\Integration;

use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\TestCase;
use Yajra\DataTables\Tests\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class HasOneRelationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $response = $this->call('GET', '/relations/hasOne');
        $response->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('heart', $response->json()['data'][0]);
        $this->assertEquals(20, count($response->json()['data']));
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'heart-19'],
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
                ['data' => 'heart.size', 'name' => 'heart.size', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ];

        return $this->call('GET', '/relations/hasOne', array_merge($data, $params));
    }

    /** @test */
    public function it_can_sort_using_the_relation_with_pagination()
    {
        $response = $this->getJsonResponse([
            'order' => [
                [
                    'column' => 0,
                    'dir'    => 'desc',
                ],
            ],
            'length' => 10,
            'start'  => 0,
            'draw'   => 1,
        ]);

        $response->assertJson([
            'draw'            => 1,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertEquals('heart-9', $response->json()['data'][0]['heart']['size']);
        $this->assertEquals(10, count($response->json()['data']));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->app['router']->get('/relations/hasOne', function (DataTables $datatables) {
            return $datatables->eloquent(User::with('heart')->select('users.*'))->make('true');
        });
    }
}
