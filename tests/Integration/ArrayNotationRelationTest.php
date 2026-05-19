<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class ArrayNotationRelationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_all_records_with_array_notation_column()
    {
        $response = $this->getJsonResponse();
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $data = $response->json()['data'];
        $this->assertCount(20, $data);

        foreach ($data as $row) {
            $this->assertArrayHasKey('roles', $row);
            $this->assertIsArray($row['roles']);
            $this->assertNotEmpty($row['roles']);
            foreach ($row['roles'] as $role) {
                $this->assertArrayHasKey('role', $role);
            }
        }

        $oddUser = $data[0];
        $evenUser = $data[1];
        $this->assertCount(2, $oddUser['roles']);
        $this->assertCount(1, $evenUser['roles']);
        $this->assertEquals('Administrator', $oddUser['roles'][0]['role']);
        $this->assertEquals('User', $oddUser['roles'][1]['role']);
        $this->assertEquals('User', $evenUser['roles'][0]['role']);
    }

    #[Test]
    public function it_can_perform_global_search_on_array_notation_column()
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

    #[Test]
    public function it_can_sort_using_array_notation_column()
    {
        $response = $this->getJsonResponse([
            'order' => [
                [
                    'column' => 0,
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

        $this->assertArrayHasKey('roles', $response->json()['data'][0]);
        $this->assertIsArray($response->json()['data'][0]['roles']);
        $this->assertNotEmpty($response->json()['data'][0]['roles']);
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'roles[, ].role', 'name' => 'roles.role', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ];

        return $this->call('GET', '/relations/arrayNotation', array_merge($data, $params));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/arrayNotation', fn (DataTables $datatables) => $datatables->eloquent(User::with('roles')->select('users.*'))->toJson());
    }
}
