<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Heart;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class MixedCaseRelationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_can_sort_on_a_path_mixing_a_literal_snake_case_and_a_camel_case_relation()
    {
        $this->app['router']->get('/relations/mixedCase', fn (DataTables $datatables) => $datatables
            ->eloquent(Post::with('post_user.nestedFilteredHeart')->select('posts.*'))
            ->toJson());

        $response = $this->call('GET', '/relations/mixedCase', [
            'columns' => [
                [
                    'data' => 'post_user.nested_filtered_heart.size',
                    'name' => 'post_user.nested_filtered_heart.size',
                    'searchable' => 'true',
                    'orderable' => 'true',
                ],
            ],
            'order' => [['column' => 0, 'dir' => 'desc']],
            'start' => 0,
            'length' => 10,
            'draw' => 1,
        ]);

        $response->assertJson([
            'draw' => 1,
            'recordsTotal' => 60,
            'recordsFiltered' => 60,
        ]);

        $this->assertEquals(
            'heart-3',
            $response->json()['data'][0]['post_user']['nested_filtered_heart']['size']
        );
    }

    #[Test]
    public function it_can_search_a_relation_registered_with_resolve_relation_using()
    {
        User::resolveRelationUsing(
            'dynamicHeartRelation',
            fn (User $user): HasOne => $user->hasOne(Heart::class),
        );

        $this->app['router']->get('/relations/dynamic', fn (DataTables $datatables) => $datatables
            ->eloquent(Post::with('post_user.dynamicHeartRelation')->select('posts.*'))
            ->toJson());

        $response = $this->call('GET', '/relations/dynamic', [
            'columns' => [
                [
                    'data' => 'post_user.dynamic_heart_relation.size',
                    'name' => 'post_user.dynamic_heart_relation.size',
                    'searchable' => 'true',
                    'orderable' => 'true',
                ],
            ],
            // Matches a single heart, as "heart-2" would also match "heart-20".
            'search' => ['value' => 'heart-20'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);
    }
}
