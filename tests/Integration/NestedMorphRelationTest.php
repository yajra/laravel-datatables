<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class NestedMorphRelationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_does_not_treat_a_nested_relation_as_a_morph_of_the_root_model()
    {
        // User::user() is a morph relation while Post::user() is not, so the
        // nested relation must be resolved against the post and not the user.
        $this->app['router']->get('/relations/nestedNotMorph', fn (DataTables $datatables) => $datatables
            ->eloquent(User::with('posts.user')->select('users.*'))
            ->toJson());

        $response = $this->call('GET', '/relations/nestedNotMorph', [
            'columns' => [
                [
                    'data' => 'posts.user.name',
                    'name' => 'posts.user.name',
                    'searchable' => 'true',
                    'orderable' => 'true',
                ],
            ],
            'search' => ['value' => 'Record-19'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 1,
        ]);

        $this->assertEquals('Record-19', $response->json()['data'][0]['name']);
    }

    #[Test]
    public function it_still_searches_a_nested_morph_relation()
    {
        $this->app['router']->get('/relations/nestedMorph', fn (DataTables $datatables) => $datatables
            ->eloquent(Post::with('user.user')->select('posts.*'))
            ->toJson());

        $response = $this->call('GET', '/relations/nestedMorph', [
            'columns' => [
                [
                    'data' => 'user.user.name',
                    'name' => 'user.user.name',
                    'searchable' => 'true',
                    'orderable' => 'true',
                ],
            ],
            'search' => ['value' => 'Human'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 30,
        ]);
    }
}
