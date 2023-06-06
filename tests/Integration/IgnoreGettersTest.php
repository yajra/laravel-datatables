<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class IgnoreGettersTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        app('config')->set('datatables.ignore_getters', true);

        $response = $this->call('GET', '/ignore-getters');
        $response->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertArrayHasKey('posts', $response->json()['data'][0]);
        $this->assertCount(20, $response->json()['data']);
    }

    /** @test */
    function it_return_the_getter_value_without_ignore_getters_config()
    {
        $response = $this->call('GET', '/ignore-getters');
        $response->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);
        $this->assertNotNull($response->json()['data'][0]['color']);
        $this->assertCount(20, $response->json()['data']);
    }

     /** @test */
    function it_ignore_the_getter_value_with_ignore_getters_config()
    {
        app('config')->set('datatables.ignore_getters', true);

        $response = $this->call('GET', '/ignore-getters');
        $response->assertJson([
            'draw'            => 0,
            'recordsTotal'    => 20,
            'recordsFiltered' => 20,
        ]);
        $this->assertNull($response->json()['data'][0]['color']);
        $this->assertCount(20, $response->json()['data']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/ignore-getters', function (DataTables $datatables) {
            return $datatables->eloquent(User::with('posts')->select('users.*'))->toJson();
        });
    }
}
