<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Facades\DataTables as DataTablesFacade;
use Yajra\DataTables\Tests\Models\AnimalUser;
use Yajra\DataTables\Tests\Models\HumanUser;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\Models\Role;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class EloquentDataTableCacheTest extends TestCase
{
    private const string ADMINS_URI = '/cache/eloquent/admins';

    private const string AUTHORS_URI = '/cache/eloquent/authors';

    private const string COLORED_URI = '/cache/eloquent/colored';

    private const string CONSTRAINED_URI = '/cache/eloquent/constrained';

    private const string OVERRIDING_BUILDER_URI = '/cache/eloquent/overriding-builder';

    private const string PLAIN_URI = '/cache/eloquent/plain';

    private const string POSTS_URI = '/cache/eloquent/posts';

    private const string SCOPED_URI = '/cache/eloquent/scoped';

    private const string SCOPES_BUILDER_URI = '/cache/eloquent/scopes-builder';

    private const string USERS_URI = '/cache/eloquent/users';

    /**
     * SQL statements executed during the last request.
     *
     * @var list<string>
     */
    protected array $queries = [];

    #[Test]
    public function it_reads_the_models_from_the_database_beyond_the_row_limit()
    {
        config()->set('datatables.cache.max_rows', 5);

        [$plain] = $this->fetch(self::PLAIN_URI);
        $this->fetch(self::USERS_URI);
        [$again] = $this->fetch(self::USERS_URI);

        $this->assertSame(['count:hit', 'rows:miss'], $this->statuses($again));
        $this->assertSame('too_many_rows', $again['cache'][1]['reason']);
        $this->assertSame($this->withoutDebug($plain), $this->withoutDebug($again));
    }

    #[Test]
    public function it_restores_the_models_their_casts_accessors_and_relations()
    {
        [$plain, $plainQueries] = $this->fetch(self::PLAIN_URI);
        [$miss] = $this->fetch(self::USERS_URI);
        [$hit, $hitQueries] = $this->fetch(self::USERS_URI);

        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($hit));
        $this->assertSame($this->withoutDebug($plain), $this->withoutDebug($miss));
        $this->assertSame($this->withoutDebug($plain), $this->withoutDebug($hit));
        $this->assertSame('#000000', $hit['data'][0]['color']);
        $this->assertSame(3, $hit['data'][0]['posts_count']);
        $this->assertCount(2, $hit['data'][0]['roles']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $hit['data'][0]['created_at']);

        // The eager loads still run, only the count and the main query are cached.
        $this->assertCount(4, $plainQueries);
        $this->assertCount(2, $hitQueries);
        $this->assertStringContainsString('from "posts"', $hitQueries[0]);
        $this->assertStringContainsString('from "roles"', $hitQueries[1]);
    }

    #[Test]
    public function it_applies_the_global_scopes_before_identifying_a_read()
    {
        ScopedUser::$type = HumanUser::class;
        [$humans] = $this->fetch(self::SCOPED_URI);

        ScopedUser::$type = AnimalUser::class;
        [$animals] = $this->fetch(self::SCOPED_URI);

        ScopedUser::$type = HumanUser::class;
        [$humansAgain] = $this->fetch(self::SCOPED_URI);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($animals));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($humansAgain));
        $this->assertSame(10, $humans['recordsTotal']);
        $this->assertSame(10, $animals['recordsTotal']);
        $this->assertSame([], array_intersect(array_column($humans['data'], 'id'), array_column($animals['data'], 'id')));
        $this->assertSame($this->withoutDebug($humans), $this->withoutDebug($humansAgain));
    }

    #[Test]
    public function it_keeps_the_records_of_each_owner_apart_and_follows_a_transfer()
    {
        [$first] = $this->fetch(self::POSTS_URI, ['user' => 1]);
        [$second] = $this->fetch(self::POSTS_URI, ['user' => 2]);

        $post = Post::query()->where('user_id', 1)->firstOrFail();
        $previousOwner = $post->user_id;
        $post->update(['user_id' => 2]);
        DataTablesFacade::flushCache(["posts:user:{$previousOwner}", 'posts:user:2']);

        [$firstAfter] = $this->fetch(self::POSTS_URI, ['user' => 1]);
        [$secondAfter] = $this->fetch(self::POSTS_URI, ['user' => 2]);

        $this->assertSame([3, 3], [$first['recordsTotal'], $second['recordsTotal']]);
        $this->assertSame([2, 4], [$firstAfter['recordsTotal'], $secondAfter['recordsTotal']]);
        $this->assertNotContains($post->id, array_column($firstAfter['data'], 'id'));
        $this->assertContains($post->id, array_column($secondAfter['data'], 'id'));
    }

    #[Test]
    public function it_flushes_the_previous_and_the_new_owner_from_the_saved_event()
    {
        Post::saved(fn (Post $post) => DataTablesFacade::flushCache(array_unique([
            "posts:user:{$post->getOriginal('user_id')}",
            "posts:user:{$post->user_id}",
        ])));

        $this->fetch(self::POSTS_URI, ['user' => 1]);
        $this->fetch(self::POSTS_URI, ['user' => 2]);

        Post::query()->where('user_id', 1)->firstOrFail()->update(['user_id' => 2]);

        [$first] = $this->fetch(self::POSTS_URI, ['user' => 1]);
        [$second] = $this->fetch(self::POSTS_URI, ['user' => 2]);

        $this->assertSame([2, 4], [$first['recordsTotal'], $second['recordsTotal']]);
    }

    #[Test]
    public function it_excludes_a_revoked_permission_once_its_tag_is_flushed()
    {
        [$before] = $this->fetch(self::ADMINS_URI);
        $this->assertContains(1, array_column($before['data'], 'id'));

        User::query()->findOrFail(1)->roles()->detach(Role::query()->where('role', 'Administrator')->firstOrFail());

        // A change that is not signaled is not detected: this is the documented contract.
        [$stale] = $this->fetch(self::ADMINS_URI);
        $this->assertContains(1, array_column($stale['data'], 'id'));

        DataTablesFacade::flushCache('role_user');
        [$after] = $this->fetch(self::ADMINS_URI);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($after));
        $this->assertNotContains(1, array_column($after['data'], 'id'));
        $this->assertSame($before['recordsTotal'] - 1, $after['recordsTotal']);
    }

    #[Test]
    public function it_can_be_flushed_from_model_events_respecting_rollbacks()
    {
        User::saved(fn () => DataTablesFacade::flushCache('users'));
        $this->fetch(self::USERS_URI);

        User::query()->findOrFail(1)->update(['name' => 'Renamed']);
        [$saved] = $this->fetch(self::USERS_URI);

        DB::beginTransaction();
        User::query()->findOrFail(2)->update(['name' => 'Rolled back']);
        DB::rollBack();
        [$rolledBack] = $this->fetch(self::USERS_URI);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($saved));
        $this->assertSame('Renamed', $saved['data'][0]['name']);
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($rolledBack));
        $this->assertSame('Record-2', $rolledBack['data'][1]['name']);
    }

    #[Test]
    public function it_moves_a_record_between_groups_when_a_filtering_field_changes()
    {
        User::query()->whereKey(1)->update(['color' => 'red']);
        [$red] = $this->fetch(self::COLORED_URI, ['color' => 'red']);
        [$blue] = $this->fetch(self::COLORED_URI, ['color' => 'blue']);

        User::query()->whereKey(1)->update(['color' => 'blue']);
        DataTablesFacade::flushCache('users');

        [$redAfter] = $this->fetch(self::COLORED_URI, ['color' => 'red']);
        [$blueAfter] = $this->fetch(self::COLORED_URI, ['color' => 'blue']);

        $this->assertSame([1, 0], [$red['recordsTotal'], $blue['recordsTotal']]);
        $this->assertSame([0, 1], [$redAfter['recordsTotal'], $blueAfter['recordsTotal']]);
    }

    #[Test]
    public function it_finds_a_record_inserted_into_a_cached_empty_result()
    {
        [$empty] = $this->fetch(self::COLORED_URI, ['color' => 'green']);
        [$stillEmpty] = $this->fetch(self::COLORED_URI, ['color' => 'green']);

        User::query()->create(['name' => 'New', 'email' => 'new@example.com', 'color' => 'green']);
        DataTablesFacade::flushCache('users');
        [$inserted] = $this->fetch(self::COLORED_URI, ['color' => 'green']);

        $this->assertSame(0, $empty['recordsTotal']);
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($stillEmpty));
        $this->assertSame(1, $inserted['recordsTotal']);
        $this->assertSame('New', $inserted['data'][0]['name']);
    }

    #[Test]
    public function it_reflects_a_change_of_a_joined_table_declared_as_a_tag()
    {
        [$before] = $this->fetch(self::AUTHORS_URI);

        User::query()->whereKey(1)->update(['name' => 'Renamed']);
        DataTablesFacade::flushCache('users');
        [$after] = $this->fetch(self::AUTHORS_URI);

        $this->assertSame('Record-1', $before['data'][0]['author']);
        $this->assertSame('Renamed', $after['data'][0]['author']);
    }

    #[Test]
    public function it_follows_mass_updates_and_soft_deletes_once_flushed()
    {
        $this->fetch(self::POSTS_URI, ['user' => 1]);

        Post::query()->where('user_id', 1)->update(['title' => 'Bulk']);
        DataTablesFacade::flushCache('posts:user:1');
        [$updated] = $this->fetch(self::POSTS_URI, ['user' => 1]);

        $post = Post::query()->where('user_id', 1)->firstOrFail();
        $post->delete();
        DataTablesFacade::flushCache('posts:user:1');
        [$deleted] = $this->fetch(self::POSTS_URI, ['user' => 1]);

        $post->restore();
        DataTablesFacade::flushCache('posts:user:1');
        [$restored] = $this->fetch(self::POSTS_URI, ['user' => 1]);

        $this->assertSame(['Bulk'], array_values(array_unique(array_column($updated['data'], 'title'))));
        $this->assertSame([3, 2, 3], [$updated['recordsTotal'], $deleted['recordsTotal'], $restored['recordsTotal']]);
    }

    #[Test]
    public function it_eager_loads_with_the_current_constraints_on_a_hit()
    {
        [$first] = $this->fetch(self::CONSTRAINED_URI, ['title' => 'Post-1']);
        [$second] = $this->fetch(self::CONSTRAINED_URI, ['title' => 'Post-2']);

        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($second));
        $this->assertSame('User-1 Post-1', $first['data'][0]['titles']);
        $this->assertSame('User-1 Post-2', $second['data'][0]['titles']);
    }

    #[Test]
    public function it_bypasses_the_rows_cache_of_a_builder_overriding_the_retrieval()
    {
        [$overriding] = $this->fetch(self::OVERRIDING_BUILDER_URI);
        [$overridingAgain] = $this->fetch(self::OVERRIDING_BUILDER_URI);
        $this->fetch(self::SCOPES_BUILDER_URI);
        [$scopes] = $this->fetch(self::SCOPES_BUILDER_URI);

        $this->assertSame(['count:miss', 'rows:bypass'], $this->statuses($overriding));
        $this->assertSame('unsupported_builder', $overriding['cache'][1]['reason']);
        $this->assertSame(['count:hit', 'rows:bypass'], $this->statuses($overridingAgain));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($scopes));
        $this->assertSame($this->withoutDebug($overriding), $this->withoutDebug($scopes));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(QueryExecuted::class, function (QueryExecuted $event) {
            $this->queries[] = $event->sql;
        });

        $router = $this->app['router'];

        $users = fn (DataTables $dataTables): EloquentDataTable => $dataTables
            ->eloquent(User::query()->with('posts', 'roles'))
            ->editColumn('created_at', fn (User $user) => $user->created_at->format('Y-m-d'))
            ->addColumn('posts_count', fn (User $user) => $user->posts->count());

        $router->get(self::PLAIN_URI, fn (DataTables $dataTables) => $users($dataTables)->toJson());

        $router->get(self::USERS_URI, fn (DataTables $dataTables) => $users($dataTables)->cache('users')->toJson());

        $router->get(self::SCOPED_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(ScopedUser::query())
            ->cache('users')
            ->toJson());

        $router->get(self::POSTS_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(Post::query()->where('user_id', request('user')))
            ->cache('posts:user:'.request('user'))
            ->toJson());

        $router->get(self::ADMINS_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(User::query()->whereHas('roles', fn ($query) => $query->where('role', 'Administrator')))
            ->cache(['users', 'role_user'])
            ->toJson());

        $router->get(self::COLORED_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(User::query()->where('color', request('color')))
            ->cache('users')
            ->toJson());

        $router->get(self::AUTHORS_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(Post::query()->join('users', 'users.id', '=', 'posts.user_id')->select('posts.*', 'users.name as author'))
            ->cache(['posts', 'users'])
            ->toJson());

        $router->get(self::CONSTRAINED_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(User::query()->with(['posts' => fn ($query) => $query->where('title', 'like', '%'.request('title'))]))
            ->addColumn('titles', fn (User $user) => $user->posts->pluck('title')->implode(','))
            ->cache('users')
            ->toJson());

        $router->get(self::OVERRIDING_BUILDER_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(OverridingBuilderUser::query())
            ->cache('users')
            ->toJson());

        $router->get(self::SCOPES_BUILDER_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(ScopesBuilderUser::query())
            ->cache('users')
            ->toJson());
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('cache.default', 'array');
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    protected function fetch(string $uri, array $parameters = []): array
    {
        $this->queries = [];

        $response = $this->call('GET', $uri, $parameters);
        $response->assertOk();

        return [$response->json(), $this->queries];
    }

    /**
     * @return list<string>
     */
    protected function statuses(array $response): array
    {
        return array_map(fn (array $read) => $read['read'].':'.$read['status'], $response['cache']);
    }

    protected function withoutDebug(array $response): array
    {
        unset($response['queries'], $response['input'], $response['cache']);

        return $response;
    }
}

class ScopedUser extends Model
{
    public static ?string $type = null;

    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('type', fn (Builder $query) => $query->where('user_type', static::$type));
    }
}

class OverridingBuilder extends Builder
{
    public function get($columns = ['*'])
    {
        return parent::get($columns);
    }
}

class ScopesBuilder extends Builder
{
    public function named(string $name): static
    {
        return $this->where('name', $name);
    }
}

class OverridingBuilderUser extends Model
{
    protected $table = 'users';

    public function newEloquentBuilder($query)
    {
        return new OverridingBuilder($query);
    }
}

class ScopesBuilderUser extends Model
{
    protected $table = 'users';

    public function newEloquentBuilder($query)
    {
        return new ScopesBuilder($query);
    }
}
