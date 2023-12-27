<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class IgnoreGettersTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function it_return_the_default_value_when_attribute_is_null()
    {
        $user = User::create([
            'name' => 'foo',
            'email' => 'foo@bar.com',
            'color' => null,
        ]);

        $this->assertEquals('#000000', $user->color);
        $this->assertEquals('#000000', $user->refresh()->toArray()['color']);
    }

    /** @test */
    public function it_return_the_getter_value_without_ignore_getters()
    {
        $this->app['router']->get('/ignore-getters', function (DataTables $datatables) {
            return $datatables->eloquent(User::with('posts.user')->select('users.*'))->toJson();
        });

        $response = $this->call('GET', '/ignore-getters');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertNotNull($response->json()['data'][0]['posts']);
        // Assert the getter color is call on primary Model
        $this->assertNotNull($response->json()['data'][0]['color']);
        // Assert the getter color is call on relationships
        $this->assertNotNull($response->json()['data'][0]['posts'][0]['user']['color']);
        $this->assertCount(20, $response->json()['data']);
    }

    /** @test */
    public function it_ignore_the_getter_value_with_ignore_getters()
    {
        $this->app['router']->get('/ignore-getters', function (DataTables $datatables) {
            return $datatables->eloquent(User::with('posts.user')->select('users.*'))->ignoreGetters()->toJson();
        });

        $response = $this->call('GET', '/ignore-getters');
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertNotNull($response->json()['data'][0]['posts']);
        // Assert the getter color is not call on primary Model
        $this->assertNull($response->json()['data'][0]['color']);
        // Assert the getter color is not call on relationships
        $this->assertNull($response->json()['data'][0]['posts'][0]['user']['color']);
        $this->assertCount(20, $response->json()['data']);
    }
}
