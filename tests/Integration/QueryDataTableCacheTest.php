<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Yajra\DataTables\Cache\CacheOptions;
use Yajra\DataTables\Cache\ResultCache;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Facades\DataTables as DataTablesFacade;
use Yajra\DataTables\Tests\TestCase;

class QueryDataTableCacheTest extends TestCase
{
    /**
     * SQL statements executed during the last request.
     *
     * @var list<string>
     */
    protected array $queries = [];

    /**
     * Cache events dispatched since the test started.
     *
     * @var list<string>
     */
    protected array $cacheEvents = [];

    #[Test]
    public function it_does_not_touch_the_cache_when_caching_is_not_enabled()
    {
        [$first, $firstQueries] = $this->fetch('/cache/plain');
        [$second, $secondQueries] = $this->fetch('/cache/plain');

        $this->assertSame([], $this->cacheEvents);
        $this->assertArrayNotHasKey('cache', $first);
        $this->assertSame($this->withoutDebug($first), $this->withoutDebug($second));
        $this->assertSame($firstQueries, $secondQueries);
        $this->assertCount(2, $firstQueries);

        // A cached DataTable does not change the behavior of another one.
        $this->fetch('/cache/users');
        $this->cacheEvents = [];
        [$third, $thirdQueries] = $this->fetch('/cache/plain');

        $this->assertSame([], $this->cacheEvents);
        $this->assertSame($this->withoutDebug($first), $this->withoutDebug($third));
        $this->assertSame($firstQueries, $thirdQueries);
    }

    #[Test]
    public function it_serves_the_counts_and_the_rows_from_the_cache()
    {
        [$plain] = $this->fetch('/cache/plain');
        [$miss, $missQueries] = $this->fetch('/cache/users');
        [$hit, $hitQueries] = $this->fetch('/cache/users');

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($miss));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($hit));
        $this->assertCount(2, $missQueries);
        $this->assertSame([], $hitQueries);
        $this->assertSame($this->withoutDebug($plain), $this->withoutDebug($miss));
        $this->assertSame($this->withoutDebug($miss), $this->withoutDebug($hit));
        $this->assertSame(20, $hit['recordsTotal']);
    }

    #[Test]
    public function it_caches_the_filtered_count_of_a_search()
    {
        $search = $this->searchParameters('Record-19');

        [$miss, $missQueries] = $this->fetch('/cache/users', $search);
        [$hit, $hitQueries] = $this->fetch('/cache/users', $search);
        [$unfiltered] = $this->fetch('/cache/users');

        $this->assertCount(3, $missQueries);
        $this->assertSame([], $hitQueries);
        $this->assertSame(['count:hit', 'count:hit', 'rows:hit'], $this->statuses($hit));
        $this->assertSame(1, $hit['recordsFiltered']);
        $this->assertSame($this->withoutDebug($miss), $this->withoutDebug($hit));
        $this->assertSame(20, $unfiltered['recordsFiltered']);
    }

    #[Test]
    public function it_caches_complex_count_queries()
    {
        [$miss] = $this->fetch('/cache/grouped');
        [$hit, $hitQueries] = $this->fetch('/cache/grouped');

        $this->assertSame([], $hitQueries);
        $this->assertSame($this->withoutDebug($miss), $this->withoutDebug($hit));
        $this->assertSame(20, $hit['recordsTotal']);
    }

    #[Test]
    public function it_does_not_share_entries_between_different_bindings()
    {
        DB::table('users')->where('id', '<=', 5)->update(['color' => 'red']);

        [$red] = $this->fetch('/cache/users', ['color' => 'red']);
        [$blue] = $this->fetch('/cache/users', ['color' => 'blue']);
        [$redAgain] = $this->fetch('/cache/users', ['color' => 'red']);

        $this->assertSame(5, $red['recordsTotal']);
        $this->assertSame(0, $blue['recordsTotal']);
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($blue));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($redAgain));
        $this->assertSame($this->withoutDebug($red), $this->withoutDebug($redAgain));
    }

    #[Test]
    public function it_does_not_share_entries_between_connections_running_the_same_sql()
    {
        [$default] = $this->fetch('/cache/users');
        [$tenant] = $this->fetch('/cache/users', ['connection' => 'tenant']);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($tenant));
        $this->assertSame(20, $default['recordsTotal']);
        $this->assertSame(1, $tenant['recordsTotal']);
        $this->assertSame('Tenant user', $tenant['data'][0]['name']);
    }

    #[Test]
    public function it_does_not_share_entries_between_bindings_crafted_to_collide()
    {
        $cache = app(ResultCache::class);
        $options = CacheOptions::make('users', 60, ['user' => '1']);
        $key = fn ($query, $options) => $cache->lookup($options, 'rows', $query)->key;

        // Interpolating the bindings into the SQL would give the same string for both.
        $first = DB::table('users')->where('name', "a' and \"email\" = 'b")->where('email', 'c');
        $second = DB::table('users')->where('name', 'a')->where('email', "b' and \"email\" = 'c");

        $this->assertNotSame($key($first, $options), $key($second, $options));
        $this->assertNotSame(
            $key(DB::table('users')->whereRaw('email = ?', [null]), $options),
            $key(DB::table('users')->whereRaw('email = ?', ['']), $options)
        );
        $this->assertNotSame(
            $key(DB::table('users'), $options),
            $key(DB::table('users'), CacheOptions::make('users', 60, ['user' => 1]))
        );
    }

    #[Test]
    public function it_does_not_share_entries_between_by_values()
    {
        [$first] = $this->fetch('/cache/users', ['by' => ['schema' => 'tenant_a']]);
        [$second] = $this->fetch('/cache/users', ['by' => ['schema' => 'tenant_b']]);
        [$firstAgain] = $this->fetch('/cache/users', ['by' => ['schema' => 'tenant_a']]);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($first));
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($second));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($firstAgain));
    }

    #[Test]
    public function it_reads_the_database_again_once_the_ttl_is_over()
    {
        $this->fetch('/cache/users', ['ttl' => 60]);
        DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);

        $this->travel(59)->seconds();
        [$hit] = $this->fetch('/cache/users', ['ttl' => 60]);

        $this->travel(2)->seconds();
        [$expired, $queries] = $this->fetch('/cache/users', ['ttl' => 60]);

        $this->assertSame('Record-1', $hit['data'][0]['name']);
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($expired));
        $this->assertCount(2, $queries);
        $this->assertSame('Renamed', $expired['data'][0]['name']);
    }

    #[Test]
    public function it_reads_the_database_again_once_a_tag_is_flushed()
    {
        $this->fetch('/cache/users', ['tags' => ['users', 'team:1']]);
        DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);

        DataTablesFacade::flushCache('posts');
        [$unrelated] = $this->fetch('/cache/users', ['tags' => ['users', 'team:1']]);

        DataTablesFacade::flushCache('team:1');
        [$flushed] = $this->fetch('/cache/users', ['tags' => ['users', 'team:1']]);

        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($unrelated));
        $this->assertSame('Record-1', $unrelated['data'][0]['name']);
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($flushed));
        $this->assertSame('Renamed', $flushed['data'][0]['name']);
    }

    #[Test]
    public function it_only_flushes_the_entries_of_the_given_tag()
    {
        Cache::put('application-key', 'application-value', 600);
        $this->fetch('/cache/users', ['tags' => 'users:a']);
        $this->fetch('/cache/users', ['tags' => 'users:b', 'color' => 'red']);

        DataTablesFacade::flushCache('users:a');
        DataTablesFacade::flushCache('never-declared');

        [$a] = $this->fetch('/cache/users', ['tags' => 'users:a']);
        [$b] = $this->fetch('/cache/users', ['tags' => 'users:b', 'color' => 'red']);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($a));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($b));
        $this->assertSame('application-value', Cache::get('application-key'));
    }

    #[Test]
    public function it_does_not_reinstall_rows_read_before_a_concurrent_flush()
    {
        $flushed = false;
        Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (&$flushed) {
            // Simulate a write committed by another request while this read is running.
            if (! $flushed && str_starts_with($event->sql, 'select * from "users"')) {
                $flushed = true;
                DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);
                DataTablesFacade::flushCache('users');
            }
        });

        [$slow] = $this->fetch('/cache/users');
        [$next] = $this->fetch('/cache/users');

        $this->assertTrue($flushed);
        $this->assertSame('Record-1', $slow['data'][0]['name']);
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($next));
        $this->assertSame('Renamed', $next['data'][0]['name']);
    }

    #[Test]
    public function it_bypasses_the_cache_inside_a_transaction()
    {
        DB::beginTransaction();
        DB::table('users')->where('id', 1)->update(['name' => 'Uncommitted']);
        [$inside, $queries] = $this->fetch('/cache/users');
        DB::rollBack();

        [$after] = $this->fetch('/cache/users');

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($inside));
        $this->assertSame('transaction', $inside['cache'][0]['reason']);
        $this->assertCount(2, $queries);
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($after));
        $this->assertSame('Record-1', $after['data'][0]['name']);
    }

    #[Test]
    public function it_runs_the_flush_once_the_transaction_is_committed()
    {
        $this->fetch('/cache/users');
        $tagKey = 'datatables:tag:'.hash('sha256', 'users');

        DB::transaction(function () use ($tagKey) {
            DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);
            DataTablesFacade::flushCache('users');

            $this->assertNotNull(Cache::get($tagKey));
        });

        $this->assertNull(Cache::get($tagKey));
        [$committed] = $this->fetch('/cache/users');
        $this->assertSame('Renamed', $committed['data'][0]['name']);
    }

    #[Test]
    public function it_discards_the_flush_when_the_transaction_is_rolled_back()
    {
        $this->fetch('/cache/users');

        DB::beginTransaction();
        DB::table('users')->where('id', 1)->update(['name' => 'Rolled back']);
        DataTablesFacade::flushCache('users');
        DB::rollBack();

        [$after] = $this->fetch('/cache/users');

        $this->assertNotNull(Cache::get('datatables:tag:'.hash('sha256', 'users')));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($after));
        $this->assertSame('Record-1', $after['data'][0]['name']);
    }

    #[Test]
    public function it_requires_a_tag_a_positive_ttl_and_a_scalar_segment()
    {
        $dataTable = fn () => app(DataTables::class)->query(DB::table('users'));

        foreach ([
            fn () => $dataTable()->cache([]),
            fn () => $dataTable()->cache(''),
            fn () => $dataTable()->cache('users', 0),
            fn () => $dataTable()->cache('users', now()->subMinute()),
            fn () => $dataTable()->cache('users', by: ['user' => new \stdClass]),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('An invalid cache configuration was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function it_can_be_switched_off_for_the_whole_application()
    {
        config()->set('datatables.cache.enabled', false);

        [$first, $queries] = $this->fetch('/cache/users');
        [$second] = $this->fetch('/cache/users');

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($second));
        $this->assertSame('disabled', $second['cache'][0]['reason']);
        $this->assertCount(2, $queries);
        $this->assertSame([], $this->cacheEvents);
        $this->assertSame($this->withoutDebug($first), $this->withoutDebug($second));
    }

    #[Test]
    public function it_falls_back_to_the_database_when_the_cache_store_fails()
    {
        Cache::extend('failing', fn () => Cache::repository(new FailingStore));
        config()->set('cache.stores.failing', ['driver' => 'failing']);
        config()->set('datatables.cache.store', 'failing');
        Log::spy();

        [$plain] = $this->fetch('/cache/plain');
        [$response, $queries] = $this->fetch('/cache/users');

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($response));
        $this->assertSame('store_error', $response['cache'][0]['reason']);
        $this->assertCount(2, $queries);
        $this->assertSame($this->withoutDebug($plain), $this->withoutDebug($response));
        Log::shouldHaveReceived('warning')->twice();
    }

    #[Test]
    public function it_bypasses_a_store_that_is_not_defined_or_cannot_keep_a_tag()
    {
        config()->set('datatables.cache.store', 'undefined');
        [$undefined] = $this->fetch('/cache/users');

        config()->set('cache.stores.null', ['driver' => 'null']);
        config()->set('datatables.cache.store', 'null');
        [$null] = $this->fetch('/cache/users');

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($undefined));
        $this->assertSame('store_error', $undefined['cache'][0]['reason']);
        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($null));
        $this->assertSame('tags_unavailable', $null['cache'][0]['reason']);
        $this->assertSame(20, $null['recordsTotal']);
    }

    #[Test]
    public function it_reads_the_database_again_when_an_entry_is_invalid()
    {
        [$first] = $this->fetch('/cache/users');
        Cache::put($first['cache'][1]['key'], ['format' => 1, 'read' => 'rows', 'data' => 'corrupted'], 60);

        [$second, $queries] = $this->fetch('/cache/users');
        [$third] = $this->fetch('/cache/users');

        $this->assertSame(['count:hit', 'rows:miss'], $this->statuses($second));
        $this->assertSame('invalid_entry', $second['cache'][1]['reason']);
        $this->assertCount(1, $queries);
        $this->assertSame($this->withoutDebug($first), $this->withoutDebug($second));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($third));
    }

    #[Test]
    public function it_does_not_cache_rows_holding_values_that_are_not_scalar()
    {
        $cache = app(ResultCache::class);
        $stream = fopen('php://memory', 'r');

        $this->assertNull($cache->exportRows([(object) ['id' => 1, 'meta' => new \ArrayObject]]));
        $this->assertNull($cache->exportRows([(object) ['id' => 1, 'blob' => $stream]]));
        $this->assertNull($cache->exportRows([(object) ['id' => 1], ['id' => 2]]));
        $this->assertNull($cache->importRows(['objects' => true, 'rows' => [['meta' => ['nested']]]]));
        $this->assertEquals(
            [(object) ['id' => 1, 'name' => 'a', 'score' => 1.5, 'deleted_at' => null]],
            $cache->importRows($cache->exportRows([(object) ['id' => 1, 'name' => 'a', 'score' => 1.5, 'deleted_at' => null]]))
        );

        fclose($stream);
    }

    #[Test]
    public function it_bypasses_queries_transforming_their_rows_after_the_database()
    {
        // e.g. a callback hiding a column depending on the authenticated user
        [$first] = $this->fetch('/cache/after-query', ['redact' => 1]);
        [$second] = $this->fetch('/cache/after-query', ['redact' => 0]);

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($first));
        $this->assertSame('after_query_callbacks', $first['cache'][1]['reason']);
        $this->assertSame('hidden', $first['data'][0]['email']);
        $this->assertSame('Email-1@example.com', $second['data'][0]['email']);
    }

    #[Test]
    public function it_bypasses_the_cache_for_locking_reads()
    {
        [$response] = $this->fetch('/cache/locking');

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($response));
        $this->assertSame('locking_read', $response['cache'][0]['reason']);
    }

    #[Test]
    public function it_exposes_the_cache_report_only_in_debug_mode()
    {
        config()->set('app.debug', false);

        [$response] = $this->fetch('/cache/users');

        $this->assertArrayNotHasKey('cache', $response);
        $this->assertArrayNotHasKey('queries', $response);
    }

    #[Test]
    public function it_does_not_reveal_the_sql_the_bindings_or_the_tags_in_the_keys()
    {
        $this->fetch('/cache/users', $this->searchParameters('Record-19') + ['tags' => 'tenant:secret']);

        $keys = array_keys($this->arrayStorage());

        $this->assertNotEmpty($keys);
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^datatables:(entry|tag|writes):[0-9a-f]{64}(:timer)?$/', $key);
        }
    }

    #[Test]
    public function it_stops_storing_entries_beyond_the_write_limit()
    {
        config()->set('datatables.cache.max_writes_per_minute', 3);

        [$first] = $this->fetch('/cache/users');
        [$search] = $this->fetch('/cache/users', $this->searchParameters('Record-1'));
        [$searchAgain, $queries] = $this->fetch('/cache/users', $this->searchParameters('Record-1'));

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($first));
        $this->assertSame(['count:hit', 'count:miss', 'rows:miss'], $this->statuses($search));
        $this->assertSame('write_limit', $search['cache'][2]['reason']);
        $this->assertSame(['count:hit', 'count:hit', 'rows:miss'], $this->statuses($searchAgain));
        $this->assertCount(1, $queries);
        $this->assertSame($this->withoutDebug($search), $this->withoutDebug($searchAgain));

        $this->travel(61)->seconds();
        $this->fetch('/cache/users', $this->searchParameters('Record-1'));
        [$afterWindow] = $this->fetch('/cache/users', $this->searchParameters('Record-1'));

        $this->assertSame('rows:hit', $this->statuses($afterWindow)[2]);
    }

    #[Test]
    public function it_does_not_store_reads_returning_more_rows_than_the_limit()
    {
        config()->set('datatables.cache.max_rows', 5);
        $page = ['start' => 0, 'length' => 5];

        // Without paging, e.g. for an export, the read returns every row.
        [$all] = $this->fetch('/cache/users');
        [$allAgain] = $this->fetch('/cache/users');
        $this->fetch('/cache/users', $page);
        [$pageAgain] = $this->fetch('/cache/users', $page);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($all));
        $this->assertSame('too_many_rows', $all['cache'][1]['reason']);
        $this->assertSame(['count:hit', 'rows:miss'], $this->statuses($allAgain));
        $this->assertCount(20, $allAgain['data']);
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($pageAgain));
        $this->assertCount(5, $pageAgain['data']);

        config()->set('datatables.cache.max_rows', null);
        $this->fetch('/cache/users');
        [$unlimited] = $this->fetch('/cache/users');

        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($unlimited));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = DB::connection('tenant');
        $tenant->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->string('color')->nullable();
            $table->timestamps();
        });
        $tenant->table('users')->insert(['name' => 'Tenant user', 'email' => 'tenant@example.com']);

        Event::listen(QueryExecuted::class, function (QueryExecuted $event) {
            $this->queries[] = $event->sql;
        });

        Event::listen('Illuminate\Cache\Events\*', function (string $event) {
            $this->cacheEvents[] = $event;
        });

        $router = $this->app['router'];

        $router->get('/cache/plain', fn (DataTables $dataTables) => $dataTables->query(DB::table('users'))->toJson());

        $router->get('/cache/users', function (DataTables $dataTables) {
            $query = DB::connection(request('connection'))->table('users');

            if (request()->has('color')) {
                $query->where('color', request('color'));
            }

            return $dataTables->query($query)
                ->cache((array) request('tags', 'users'), (int) request('ttl', 60), (array) request('by', []))
                ->toJson();
        });

        $router->get('/cache/grouped', fn (DataTables $dataTables) => $dataTables
            ->query(DB::table('posts')->select('user_id', DB::raw('count(*) as total'))->groupBy('user_id'))
            ->cache('posts')
            ->toJson());

        $router->get('/cache/after-query', fn (DataTables $dataTables) => $dataTables
            ->query(DB::table('users')->afterQuery(fn ($rows) => request('redact') ? $rows->each(fn ($row) => $row->email = 'hidden') : $rows))
            ->cache('users')
            ->toJson());

        $router->get('/cache/locking', fn (DataTables $dataTables) => $dataTables
            ->query(DB::table('users')->lockForUpdate())
            ->cache('users')
            ->toJson());
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('database.connections.tenant', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
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

    protected function searchParameters(string $keyword): array
    {
        return [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'email', 'name' => 'email', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => $keyword],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayStorage(): array
    {
        $store = Cache::store()->getStore();
        $this->assertInstanceOf(ArrayStore::class, $store);

        return (fn () => $this->storage)->call($store);
    }
}

class FailingStore implements Store
{
    public function get($key)
    {
        throw new RuntimeException('Connection refused');
    }

    public function many(array $keys)
    {
        throw new RuntimeException('Connection refused');
    }

    public function put($key, $value, $seconds)
    {
        throw new RuntimeException('Connection refused');
    }

    public function putMany(array $values, $seconds)
    {
        throw new RuntimeException('Connection refused');
    }

    public function increment($key, $value = 1)
    {
        throw new RuntimeException('Connection refused');
    }

    public function decrement($key, $value = 1)
    {
        throw new RuntimeException('Connection refused');
    }

    public function forever($key, $value)
    {
        throw new RuntimeException('Connection refused');
    }

    public function touch($key, $seconds)
    {
        throw new RuntimeException('Connection refused');
    }

    public function forget($key)
    {
        throw new RuntimeException('Connection refused');
    }

    public function flush()
    {
        throw new RuntimeException('Connection refused');
    }

    public function getPrefix()
    {
        return '';
    }
}
