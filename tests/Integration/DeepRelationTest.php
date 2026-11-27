<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\AnimalUser;
use Yajra\DataTables\Tests\Models\HumanUser;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class DeepRelationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $response = $this->getJsonResponse();
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 60,
        ]);

        $this->assertCount(60, $response->json()['data']);
    }

    #[Test]
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'email-19@example.com'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);

        $this->assertCount(3, $response->json()['data']);
    }

    #[Test]
    #[TestWith([Post::class, 'unlistedRelation.user.id', true])]
    #[TestWith([Post::class, 'unlistedRelation.user.id', false])]
    #[TestWith([User::class, 'user.unlistedRelation.size', true])]
    #[TestWith([User::class, 'user.unlistedRelation.size', false])]
    public function it_only_resolves_configured_relations(string $modelClass, string $column, bool $global): void
    {
        $calls = 0;
        $modelClass::resolveRelationUsing('unlistedRelation', static function (Post|User $model) use (&$calls) {
            $calls++;

            return $model->heart();
        });

        $this->getJsonResponse([
            'columns' => [[
                'data' => $column,
                'searchable' => 'true',
                'search' => ['value' => $global ? '' : 'heart-20'],
            ]],
            'search' => ['value' => $global ? 'heart-20' : ''],
        ]);

        $this->assertSame(0, $calls);
    }

    #[Test]
    public function it_resolves_an_allowed_nested_relation_on_its_own_model(): void
    {
        $this->app['router']->get('/relations/nested-heart', function (DataTables $datatables) {
            $post = new class extends Post
            {
                protected $table = 'posts';

                public function heart(): never
                {
                    throw new LogicException('Only the related user should resolve heart.');
                }
            };

            return $datatables->eloquent($post->newQuery()->with('user.heart'))->toJson();
        });

        $this->call('GET', '/relations/nested-heart', [
            'columns' => [['data' => 'user.heart.size', 'searchable' => 'true']],
            'search' => ['value' => 'heart-20'],
        ])->assertJson(['recordsTotal' => 60, 'recordsFiltered' => 3]);
    }

    #[Test]
    #[TestWith([HumanUser::class, 'email-19@example.com'])]
    #[TestWith([AnimalUser::class, 'email-20@example.com'])]
    public function it_searches_nested_relations_for_each_polymorphic_type(string $type, string $email): void
    {
        $this->app['router']->get('/relations/nested-morph', fn (DataTables $datatables) => $datatables
            ->eloquent(Post::with('user.user.users'))
            ->toJson());

        $response = $this->call('GET', '/relations/nested-morph', [
            'columns' => [['data' => 'user.user.users.email', 'searchable' => 'true']],
            'search' => ['value' => $email],
        ]);

        $response->assertJson(['recordsTotal' => 60, 'recordsFiltered' => 30]);
        $types = collect($response->json('data'))->pluck('user.user_type')->unique()->values()->all();
        $this->assertSame([$type], $types);
    }

    #[Test]
    public function it_searches_a_callback_loaded_relation_with_a_custom_filter(): void
    {
        $this->app['router']->get('/relations/custom-role-filter', function (DataTables $datatables) {
            $query = Post::with(['user' => fn ($user) => $user->with('roles')]);

            return $datatables->eloquent($query)
                ->filterColumn('user.roles.role', function ($posts, string $keyword) {
                    $posts->whereHas('user.roles', fn ($roles) => $roles->where('role', 'like', "%{$keyword}%"));
                })
                ->toJson();
        });

        $response = $this->call('GET', '/relations/custom-role-filter', [
            'columns' => [['data' => 'user.roles.role', 'searchable' => 'true']],
            'search' => ['value' => 'Administrator'],
        ]);
        $response->assertJson(['recordsTotal' => 60, 'recordsFiltered' => 30]);
    }

    #[Test]
    #[TestWith(['user.human_users.id'])]
    #[TestWith(['user.HUMAN_USERS.id'])]
    public function it_searches_only_the_related_type_matching_the_table_qualifier(string $column): void
    {
        $this->app['router']->get('/relations/qualified', fn (DataTables $datatables) => $datatables
            ->eloquent(User::with('user'))
            ->toJson());

        $response = $this->call('GET', '/relations/qualified', [
            'columns' => [['data' => $column, 'searchable' => 'true']],
            'search' => ['value' => '1'],
        ]);

        $response->assertJsonMissingPath('error');
        $response->assertJson(['recordsTotal' => 20, 'recordsFiltered' => 10]);
        $this->assertSame([HumanUser::class], collect($response->json('data'))->pluck('user_type')->unique()->values()->all());
    }

    #[Test]
    public function it_rejects_a_qualifier_that_does_not_match_the_related_table(): void
    {
        $this->app['router']->get('/relations/qualified', fn (DataTables $datatables) => $datatables
            ->eloquent(User::with('user'))
            ->toJson());

        $response = $this->call('GET', '/relations/qualified', [
            'columns' => [['data' => 'user.users.id', 'searchable' => 'true']],
            'search' => ['value' => '1'],
        ]);

        $response->assertJsonMissingPath('error');
        $response->assertJson(['recordsTotal' => 20, 'recordsFiltered' => 0]);
    }

    #[Test]
    public function it_preserves_post_ids_when_filtering_a_nested_column(): void
    {
        $response = $this->getJsonResponse([
            'columns' => [['data' => 'user.roles.role', 'searchable' => 'true', 'search' => ['value' => 'Administrator']]],
        ]);

        $response->assertJson(['recordsTotal' => 60, 'recordsFiltered' => 30]);
        $expected = Post::whereHas('user.roles', fn ($roles) => $roles->where('role', 'Administrator'))->pluck('id');
        $this->assertEqualsCanonicalizing($expected->all(), collect($response->json('data'))->pluck('id')->all());
    }

    #[Test]
    public function it_searches_a_schema_qualified_root_table_before_a_relation_with_the_schema_name(): void
    {
        Post::resolveRelationUsing('main', static fn (Post $post) => $post->user());

        $this->app['router']->get('/relations/root-qualified', fn (DataTables $datatables) => $datatables
            ->eloquent(Post::with('main')->withTrashed()->from('main.posts AS p'))
            ->toJson());

        $response = $this->call('GET', '/relations/root-qualified', [
            'columns' => [['data' => 'main.posts.title', 'searchable' => 'true']],
            'search' => ['value' => 'User-19 Post-1'],
        ]);

        $response->assertJsonMissingPath('error');
        $response->assertJson(['recordsTotal' => 60, 'recordsFiltered' => 1]);
    }

    #[Test]
    public function it_accepts_a_short_table_qualifier_for_a_schema_qualified_related_model(): void
    {
        $user = new class extends User
        {
            protected $table = 'main.users';
        };
        Post::resolveRelationUsing('schemaUser', static fn (Post $post) => $post->belongsTo($user::class, 'user_id'));

        $this->app['router']->get('/relations/schema-user', fn (DataTables $datatables) => $datatables
            ->eloquent(Post::with('schemaUser'))
            ->toJson());

        $response = $this->call('GET', '/relations/schema-user', [
            'columns' => [['data' => 'schemaUser.users.email', 'searchable' => 'true']],
            'search' => ['value' => 'email-19@example.com'],
        ]);

        $response->assertJsonMissingPath('error');
        $response->assertJson(['recordsTotal' => 60, 'recordsFiltered' => 3]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/deep', function (DataTables $datatables) {
            $query = Post::with('user.roles')->select('posts.*');

            return $datatables
                ->eloquent($query)
                ->toJson();
        });
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'user.roles.role', 'name' => 'user.roles.role', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'user.name', 'name' => 'user.name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'user.email', 'name' => 'user.email', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ];

        return $this->call('GET', '/relations/deep', array_merge($data, $params));
    }
}
