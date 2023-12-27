<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class BelongsToManyRelationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $response = $this->call('GET', '/relations/belongsToMany');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('roles', $response->json()['data'][0]);
        $this->assertCount(20, $response->json()['data']);
    }

    /** @test */
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'Administrator'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 10,
        ]);

        $this->assertCount(10, $response->json()['data']);
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'roles', 'name' => 'roles.role', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ];

        return $this->call('GET', '/relations/belongsToMany', array_merge($data, $params));
    }

    /** @test */
    public function it_can_sort_using_the_relation_with_pagination()
    {
        $response = $this->getJsonResponse([
            'order' => [
                [
                    'column' => 2,
                    'dir' => 'desc',
                ],
            ],
            'length' => 10,
            'start' => 0,
            'draw' => 1,
        ]);

        $response->assertJson([
            'draw' => 1,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertCount(10, $response->json()['data']);

        $this->assertCount(2, $response->json()['data'][0]['roles']);
        $this->assertEquals('Administrator', $response->json()['data'][0]['roles'][0]['role']);
        $this->assertEquals('User', $response->json()['data'][0]['roles'][1]['role']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/belongsToMany', function (DataTables $datatables) {
            return $datatables->eloquent(User::with('roles')->select('users.*'))->toJson();
        });
    }
}
