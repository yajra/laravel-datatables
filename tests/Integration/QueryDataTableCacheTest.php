<?php

namespace Yajra\DataTables\Tests\Integration;

use DateInterval;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Yajra\DataTables\Cache\CacheLookup;
use Yajra\DataTables\Cache\CacheOptions;
use Yajra\DataTables\Cache\Payload;
use Yajra\DataTables\Cache\ResultCache;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Exceptions\CacheFlushException;
use Yajra\DataTables\Facades\DataTables as DataTablesFacade;
use Yajra\DataTables\Tests\TestCase;

class QueryDataTableCacheTest extends TestCase
{
    private const string AFTER_QUERY_URI = '/cache/after-query';

    private const string GROUPED_URI = '/cache/grouped';

    private const string RENAMED_TENANT = 'Renamed tenant';

    private const string LOCKING_URI = '/cache/locking';

    private const string PLAIN_URI = '/cache/plain';

    private const string USERS_URI = '/cache/users';

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
        [$first, $firstQueries] = $this->fetch(self::PLAIN_URI);
        [$second, $secondQueries] = $this->fetch(self::PLAIN_URI);

        $this->assertSame([], $this->cacheEvents);
        $this->assertArrayNotHasKey('cache', $first);
        $this->assertSame($this->withoutDebug($first), $this->withoutDebug($second));
        $this->assertSame($firstQueries, $secondQueries);
        $this->assertCount(2, $firstQueries);

        // A cached DataTable does not change the behavior of another one.
        $this->fetch(self::USERS_URI);
        $this->cacheEvents = [];
        [$third, $thirdQueries] = $this->fetch(self::PLAIN_URI);

        $this->assertSame([], $this->cacheEvents);
        $this->assertSame($this->withoutDebug($first), $this->withoutDebug($third));
        $this->assertSame($firstQueries, $thirdQueries);
    }

    #[Test]
    public function it_serves_the_counts_and_the_rows_from_the_cache()
    {
        [$plain] = $this->fetch(self::PLAIN_URI);
        [$miss, $missQueries] = $this->fetch(self::USERS_URI);
        [$hit, $hitQueries] = $this->fetch(self::USERS_URI);

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

        [$miss, $missQueries] = $this->fetch(self::USERS_URI, $search);
        [$hit, $hitQueries] = $this->fetch(self::USERS_URI, $search);
        [$unfiltered] = $this->fetch(self::USERS_URI);

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
        [$miss] = $this->fetch(self::GROUPED_URI);
        [$hit, $hitQueries] = $this->fetch(self::GROUPED_URI);

        $this->assertSame([], $hitQueries);
        $this->assertSame($this->withoutDebug($miss), $this->withoutDebug($hit));
        $this->assertSame(20, $hit['recordsTotal']);
    }

    #[Test]
    public function it_does_not_share_entries_between_different_bindings()
    {
        DB::table('users')->where('id', '<=', 5)->update(['color' => 'red']);

        [$red] = $this->fetch(self::USERS_URI, ['color' => 'red']);
        [$blue] = $this->fetch(self::USERS_URI, ['color' => 'blue']);
        [$redAgain] = $this->fetch(self::USERS_URI, ['color' => 'red']);

        $this->assertSame(5, $red['recordsTotal']);
        $this->assertSame(0, $blue['recordsTotal']);
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($blue));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($redAgain));
        $this->assertSame($this->withoutDebug($red), $this->withoutDebug($redAgain));
    }

    #[Test]
    public function it_does_not_share_entries_between_connections_running_the_same_sql()
    {
        [$default] = $this->fetch(self::USERS_URI);
        [$tenant] = $this->fetch(self::USERS_URI, ['connection' => 'tenant']);

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
        [$first] = $this->fetch(self::USERS_URI, ['by' => ['schema' => 'tenant_a']]);
        [$second] = $this->fetch(self::USERS_URI, ['by' => ['schema' => 'tenant_b']]);
        [$firstAgain] = $this->fetch(self::USERS_URI, ['by' => ['schema' => 'tenant_a']]);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($first));
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($second));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($firstAgain));
    }

    #[Test]
    public function it_reads_the_database_again_once_the_ttl_is_over()
    {
        $this->fetch(self::USERS_URI, ['ttl' => 60]);
        DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);

        $this->travel(59)->seconds();
        [$hit] = $this->fetch(self::USERS_URI, ['ttl' => 60]);

        $this->travel(2)->seconds();
        [$expired, $queries] = $this->fetch(self::USERS_URI, ['ttl' => 60]);

        $this->assertSame('Record-1', $hit['data'][0]['name']);
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($expired));
        $this->assertCount(2, $queries);
        $this->assertSame('Renamed', $expired['data'][0]['name']);
    }

    #[Test]
    public function it_reads_the_database_again_once_a_tag_is_flushed()
    {
        $this->fetch(self::USERS_URI, ['tags' => ['users', 'team:1']]);
        DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);

        DataTablesFacade::flushCache('posts');
        [$unrelated] = $this->fetch(self::USERS_URI, ['tags' => ['users', 'team:1']]);

        DataTablesFacade::flushCache('team:1');
        [$flushed] = $this->fetch(self::USERS_URI, ['tags' => ['users', 'team:1']]);

        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($unrelated));
        $this->assertSame('Record-1', $unrelated['data'][0]['name']);
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($flushed));
        $this->assertSame('Renamed', $flushed['data'][0]['name']);
    }

    #[Test]
    public function it_only_flushes_the_entries_of_the_given_tag()
    {
        Cache::put('application-key', 'application-value', 600);
        $this->fetch(self::USERS_URI, ['tags' => 'users:a']);
        $this->fetch(self::USERS_URI, ['tags' => 'users:b', 'color' => 'red']);

        DataTablesFacade::flushCache('users:a');
        DataTablesFacade::flushCache('never-declared');

        [$a] = $this->fetch(self::USERS_URI, ['tags' => 'users:a']);
        [$b] = $this->fetch(self::USERS_URI, ['tags' => 'users:b', 'color' => 'red']);

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

        [$slow] = $this->fetch(self::USERS_URI);
        [$next] = $this->fetch(self::USERS_URI);

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
        [$inside, $queries] = $this->fetch(self::USERS_URI);
        DB::rollBack();

        [$after] = $this->fetch(self::USERS_URI);

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($inside));
        $this->assertSame('transaction', $inside['cache'][0]['reason']);
        $this->assertCount(2, $queries);
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($after));
        $this->assertSame('Record-1', $after['data'][0]['name']);
    }

    #[Test]
    public function it_runs_the_flush_once_the_transaction_is_committed()
    {
        $this->fetch(self::USERS_URI);
        $tagKey = 'datatables:tag:'.hash('sha256', 'users');

        DB::transaction(function () use ($tagKey) {
            DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);
            DataTablesFacade::flushCache('users');

            $this->assertNotNull(Cache::get($tagKey));
        });

        $this->assertNull(Cache::get($tagKey));
        [$committed] = $this->fetch(self::USERS_URI);
        $this->assertSame('Renamed', $committed['data'][0]['name']);
    }

    #[Test]
    public function it_discards_the_flush_when_the_transaction_is_rolled_back()
    {
        $this->fetch(self::USERS_URI);

        DB::beginTransaction();
        DB::table('users')->where('id', 1)->update(['name' => 'Rolled back']);
        DataTablesFacade::flushCache('users');
        DB::rollBack();

        [$after] = $this->fetch(self::USERS_URI);

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

        [$first, $queries] = $this->fetch(self::USERS_URI);
        [$second] = $this->fetch(self::USERS_URI);

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

        [$plain] = $this->fetch(self::PLAIN_URI);
        [$response, $queries] = $this->fetch(self::USERS_URI);

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
        [$undefined] = $this->fetch(self::USERS_URI);

        config()->set('cache.stores.null', ['driver' => 'null']);
        config()->set('datatables.cache.store', 'null');
        [$null] = $this->fetch(self::USERS_URI);

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($undefined));
        $this->assertSame('store_error', $undefined['cache'][0]['reason']);
        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($null));
        $this->assertSame('tags_unavailable', $null['cache'][0]['reason']);
        $this->assertSame(20, $null['recordsTotal']);
    }

    #[Test]
    public function it_reads_the_database_again_when_an_entry_is_invalid()
    {
        [$first] = $this->fetch(self::USERS_URI);
        Cache::put($first['cache'][1]['key'], ['format' => 1, 'read' => 'rows', 'data' => 'corrupted'], 60);

        [$second, $queries] = $this->fetch(self::USERS_URI);
        [$third] = $this->fetch(self::USERS_URI);

        $this->assertSame(['count:hit', 'rows:miss'], $this->statuses($second));
        $this->assertSame('invalid_entry', $second['cache'][1]['reason']);
        $this->assertCount(1, $queries);
        $this->assertSame($this->withoutDebug($first), $this->withoutDebug($second));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($third));
    }

    #[Test]
    public function it_does_not_cache_rows_holding_values_that_are_not_scalar()
    {
        $stream = fopen('php://memory', 'r');

        $this->assertNull(Payload::exportRows([(object) ['id' => 1, 'meta' => new \ArrayObject]]));
        $this->assertNull(Payload::exportRows([(object) ['id' => 1, 'blob' => $stream]]));
        $this->assertNull(Payload::exportRows([(object) ['id' => 1], ['id' => 2]]));
        $this->assertNull(Payload::importRows(['objects' => true, 'rows' => [['meta' => ['nested']]]]));
        $this->assertEquals(
            [(object) ['id' => 1, 'name' => 'a', 'score' => 1.5, 'deleted_at' => null]],
            Payload::importRows(Payload::exportRows([(object) ['id' => 1, 'name' => 'a', 'score' => 1.5, 'deleted_at' => null]]))
        );

        fclose($stream);
    }

    #[Test]
    public function it_bypasses_queries_transforming_their_rows_after_the_database()
    {
        // e.g. a callback hiding a column depending on the authenticated user
        [$first] = $this->fetch(self::AFTER_QUERY_URI, ['redact' => 1]);
        [$second] = $this->fetch(self::AFTER_QUERY_URI, ['redact' => 0]);

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($first));
        $this->assertSame('after_query_callbacks', $first['cache'][1]['reason']);
        $this->assertSame('hidden', $first['data'][0]['email']);
        $this->assertSame('Email-1@example.com', $second['data'][0]['email']);
    }

    #[Test]
    public function it_bypasses_the_cache_for_locking_reads()
    {
        [$response] = $this->fetch(self::LOCKING_URI);

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($response));
        $this->assertSame('locking_read', $response['cache'][0]['reason']);
    }

    #[Test]
    public function it_exposes_the_cache_report_only_in_debug_mode()
    {
        config()->set('app.debug', false);

        [$response] = $this->fetch(self::USERS_URI);

        $this->assertArrayNotHasKey('cache', $response);
        $this->assertArrayNotHasKey('queries', $response);
    }

    #[Test]
    public function it_does_not_reveal_the_sql_the_bindings_or_the_tags_in_the_keys()
    {
        $this->fetch(self::USERS_URI, $this->searchParameters('Record-19') + ['tags' => 'tenant:secret']);

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

        [$first] = $this->fetch(self::USERS_URI);
        [$search] = $this->fetch(self::USERS_URI, $this->searchParameters('Record-1'));
        [$searchAgain, $queries] = $this->fetch(self::USERS_URI, $this->searchParameters('Record-1'));

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($first));
        $this->assertSame(['count:hit', 'count:miss', 'rows:miss'], $this->statuses($search));
        $this->assertSame('write_limit', $search['cache'][2]['reason']);
        $this->assertSame(['count:hit', 'count:hit', 'rows:miss'], $this->statuses($searchAgain));
        $this->assertCount(1, $queries);
        $this->assertSame($this->withoutDebug($search), $this->withoutDebug($searchAgain));

        $this->travel(61)->seconds();
        $this->fetch(self::USERS_URI, $this->searchParameters('Record-1'));
        [$afterWindow] = $this->fetch(self::USERS_URI, $this->searchParameters('Record-1'));

        $this->assertSame('rows:hit', $this->statuses($afterWindow)[2]);

        config()->set('datatables.cache.max_writes_per_minute', null);
        $this->fetch(self::USERS_URI, $this->searchParameters('Record-2'));
        [$unlimited] = $this->fetch(self::USERS_URI, $this->searchParameters('Record-2'));

        $this->assertSame(['count:hit', 'count:hit', 'rows:hit'], $this->statuses($unlimited));
    }

    #[Test]
    public function it_does_not_store_reads_returning_more_rows_than_the_limit()
    {
        config()->set('datatables.cache.max_rows', 5);
        $page = ['start' => 0, 'length' => 5];

        // Without paging, e.g. for an export, the read returns every row.
        [$all] = $this->fetch(self::USERS_URI);
        [$allAgain] = $this->fetch(self::USERS_URI);
        $this->fetch(self::USERS_URI, $page);
        [$pageAgain] = $this->fetch(self::USERS_URI, $page);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($all));
        $this->assertSame('too_many_rows', $all['cache'][1]['reason']);
        $this->assertSame(['count:hit', 'rows:miss'], $this->statuses($allAgain));
        $this->assertCount(20, $allAgain['data']);
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($pageAgain));
        $this->assertCount(5, $pageAgain['data']);

        config()->set('datatables.cache.max_rows', null);
        $this->fetch(self::USERS_URI);
        [$unlimited] = $this->fetch(self::USERS_URI);

        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($unlimited));
    }

    #[Test]
    public function it_reads_the_database_again_when_an_entry_has_another_format()
    {
        [$first] = $this->fetch(self::USERS_URI);

        foreach (['corrupted', ['format' => 0, 'read' => 'rows', 'data' => []], ['format' => 1, 'read' => 'count', 'data' => 3]] as $entry) {
            Cache::put($first['cache'][1]['key'], $entry, 60);

            [$response] = $this->fetch(self::USERS_URI);

            $this->assertSame(['count:hit', 'rows:miss'], $this->statuses($response));
            $this->assertSame('invalid_entry', $response['cache'][1]['reason']);
            $this->assertSame($this->withoutDebug($first), $this->withoutDebug($response));
        }
    }

    #[Test]
    public function it_returns_the_database_rows_when_the_cache_store_cannot_be_written()
    {
        Cache::extend('read-only', fn () => Cache::repository(new ReadOnlyStore));
        config()->set('cache.stores.read-only', ['driver' => 'read-only']);
        config()->set('datatables.cache.store', 'read-only');
        Log::spy();

        [$plain] = $this->fetch(self::PLAIN_URI);
        [$response, $queries] = $this->fetch(self::USERS_URI);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($response));
        $this->assertSame('store_error', $response['cache'][0]['reason']);
        $this->assertSame('store_error', $response['cache'][1]['reason']);
        $this->assertCount(2, $queries);
        $this->assertSame($this->withoutDebug($plain), $this->withoutDebug($response));
        Log::shouldHaveReceived('warning')->twice();
    }

    #[Test]
    public function it_bypasses_a_connection_that_cannot_be_identified()
    {
        config()->set('database.connections.unidentifiable', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'resolver' => fn () => null,
        ]);

        $lookup = app(ResultCache::class)->lookup(CacheOptions::make('users', 60), 'rows', DB::connection('unidentifiable')->table('users'));

        $this->assertSame('bypass', $lookup->status);
        $this->assertSame('unsupported_connection', $lookup->reason);
    }

    #[Test]
    public function it_accepts_intervals_and_nested_segments()
    {
        $options = CacheOptions::make(['users', 'posts', 'users'], new DateInterval('PT2M'), ['tenant' => ['id' => 1, 'schema' => null]]);

        $this->assertSame(['posts', 'users'], $options->tags);
        $this->assertSame(120, $options->ttl);
        $this->assertSame(['tenant' => ['id' => 1, 'schema' => null]], $options->by);

        $this->expectException(InvalidArgumentException::class);
        CacheOptions::make('users', 60, ['tenant' => ['id' => new \stdClass]]);
    }

    #[Test]
    public function it_keeps_the_flush_of_a_transaction_when_another_connection_rolls_back()
    {
        $this->fetch(self::USERS_URI);

        DB::beginTransaction();
        DB::connection('tenant')->beginTransaction();
        DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);
        DataTablesFacade::flushCache('users');
        DB::connection('tenant')->rollBack();
        DB::commit();

        [$after] = $this->fetch(self::USERS_URI);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($after));
        $this->assertSame('Renamed', $after['data'][0]['name']);
    }

    #[Test]
    public function it_runs_the_flush_after_the_commit_of_the_given_connection()
    {
        $this->fetch(self::USERS_URI, ['connection' => 'tenant']);

        DB::connection('tenant')->beginTransaction();
        DB::beginTransaction();
        DB::connection('tenant')->table('users')->where('id', 1)->update(['name' => self::RENAMED_TENANT]);
        DataTablesFacade::flushCache('users', connection: 'tenant');
        DB::rollBack();

        $this->assertNotNull(Cache::get('datatables:tag:'.hash('sha256', 'users')));

        DB::connection('tenant')->commit();

        [$after] = $this->fetch(self::USERS_URI, ['connection' => 'tenant']);

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($after));
        $this->assertSame(self::RENAMED_TENANT, $after['data'][0]['name']);
    }

    #[Test]
    public function it_defers_the_flush_to_the_last_transaction_when_no_connection_is_given()
    {
        $this->fetch(self::USERS_URI, ['connection' => 'tenant']);
        $tagKey = 'datatables:tag:'.hash('sha256', 'users');

        DB::connection('tenant')->transaction(function () use ($tagKey) {
            DB::connection('tenant')->table('users')->where('id', 1)->update(['name' => self::RENAMED_TENANT]);
            DataTablesFacade::flushCache('users');

            $this->assertNotNull(Cache::get($tagKey));
        });

        $this->assertNull(Cache::get($tagKey));
        [$after] = $this->fetch(self::USERS_URI, ['connection' => 'tenant']);
        $this->assertSame(self::RENAMED_TENANT, $after['data'][0]['name']);
    }

    #[Test]
    public function it_flushes_immediately_outside_a_transaction_of_the_given_connection()
    {
        $this->fetch(self::USERS_URI);
        $tagKey = 'datatables:tag:'.hash('sha256', 'users');

        DB::transaction(fn () => DataTablesFacade::flushCache('users', connection: 'tenant'));

        $this->assertNull(Cache::get($tagKey));
    }

    #[Test]
    public function it_defers_the_flush_without_a_transactions_manager()
    {
        $cache = new ResultCache(app('cache'), app('config'), app('db'));
        $this->fetch(self::USERS_URI);
        $tagKey = 'datatables:tag:'.hash('sha256', 'users');

        DB::transaction(function () use ($cache, $tagKey) {
            $cache->flush('users');

            $this->assertNotNull(Cache::get($tagKey));
        });

        $this->assertNull(Cache::get($tagKey));
    }

    #[Test]
    public function it_throws_when_a_flush_leaves_a_generation_in_the_store()
    {
        Cache::extend('undeletable', fn () => Cache::repository(new UndeletableStore));
        config()->set('cache.stores.undeletable', ['driver' => 'undeletable']);
        config()->set('datatables.cache.store', 'undeletable');

        // A tag that was never read has no generation: flushing it is safe.
        DataTablesFacade::flushCache('users');

        $this->fetch(self::USERS_URI);
        DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);

        try {
            DataTablesFacade::flushCache('users');
            $this->fail('A flush that left the generation of its tag was reported as a success.');
        } catch (CacheFlushException) {
            [$after] = $this->fetch(self::USERS_URI);

            $this->assertSame('Record-1', $after['data'][0]['name']);
        }
    }

    #[Test]
    public function it_bypasses_the_cache_for_reads_routed_to_the_primary()
    {
        $replica = tempnam(sys_get_temp_dir(), 'replica');
        $primary = tempnam(sys_get_temp_dir(), 'primary');

        foreach (['replica' => [$replica, 'Replica'], 'primary' => [$primary, 'Record-1']] as $name => [$file, $value]) {
            config()->set("database.connections.{$name}", ['driver' => 'sqlite', 'database' => $file, 'prefix' => '']);
            DB::connection($name)->getSchemaBuilder()->create('users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('email');
                $table->string('color')->nullable();
                $table->timestamps();
            });
            DB::connection($name)->table('users')->insert(['name' => $value, 'email' => 'user@example.com']);
        }

        config()->set('database.connections.replicated', [
            'driver' => 'sqlite',
            'read' => ['database' => $replica],
            'write' => ['database' => $primary],
            'sticky' => true,
            'prefix' => '',
        ]);

        try {
            $this->fetch(self::USERS_URI, ['connection' => 'replicated']);
            [$replicaHit] = $this->fetch(self::USERS_URI, ['connection' => 'replicated']);
            [$writePdo] = $this->fetch(self::USERS_URI, ['connection' => 'replicated', 'primary' => 1]);

            DB::connection('replicated')->table('users')->where('id', 1)->update(['name' => 'Written']);
            [$sticky] = $this->fetch(self::USERS_URI, ['connection' => 'replicated']);

            $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($replicaHit));
            $this->assertSame('Replica', $replicaHit['data'][0]['name']);
            $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($writePdo));
            $this->assertSame('primary_read', $writePdo['cache'][1]['reason']);
            $this->assertSame('Record-1', $writePdo['data'][0]['name']);
            $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($sticky));
            $this->assertSame('Written', $sticky['data'][0]['name']);
        } finally {
            DB::purge('replicated');
            DB::purge('replica');
            DB::purge('primary');
            unlink($replica);
            unlink($primary);
        }
    }

    #[Test]
    public function it_bypasses_the_cache_for_custom_fetch_modes()
    {
        $query = fn () => DB::table('users')->select('id', 'name')->orderBy('id');
        $rows = fn (bool $numeric) => app(DataTables::class)
            ->query($numeric ? $query()->fetchUsing(PDO::FETCH_NUM) : $query())
            ->cache('users')
            ->results();

        $rows(false);
        $numeric = $rows(true);

        $this->assertSame([1, 'Record-1'], $numeric->first());
        $this->assertEquals($query()->fetchUsing(PDO::FETCH_NUM)->get(), $numeric);
        $this->assertSame(
            'custom_fetch_mode',
            app(ResultCache::class)->lookup(CacheOptions::make('users', 60), 'rows', $query()->fetchUsing(PDO::FETCH_NUM))->reason
        );
    }

    #[Test]
    public function it_can_cache_the_counts_only()
    {
        [$first] = $this->fetch(self::USERS_URI, ['counts_only' => 1]);
        DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);
        [$second, $queries] = $this->fetch(self::USERS_URI, ['counts_only' => 1]);

        $this->assertSame(['count:miss'], $this->statuses($first));
        $this->assertSame(['count:hit'], $this->statuses($second));
        $this->assertCount(1, $queries);
        $this->assertSame(20, $second['recordsTotal']);
        $this->assertSame('Renamed', $second['data'][0]['name']);
    }

    #[Test]
    public function it_bypasses_queries_bound_to_values_that_are_not_scalar()
    {
        $cache = app(ResultCache::class);
        $options = CacheOptions::make('users', 60);

        $stringable = $cache->lookup($options, 'rows', DB::table('users')->whereRaw('name = ?', [Str::of('Record-1')]));
        $date = $cache->lookup($options, 'rows', DB::table('users')->where('created_at', '<', now()));

        $this->assertSame('unsupported_binding', $stringable->reason);
        $this->assertSame('miss', $date->status);
    }

    #[Test]
    public function it_only_stores_the_reads_missing_from_the_cache()
    {
        $cache = app(ResultCache::class);
        $options = CacheOptions::make('users', 60);
        $bypass = CacheLookup::bypass('rows', 'transaction');

        $this->assertSame($bypass, $cache->put($bypass, $options, ['objects' => true, 'rows' => []]));
        $this->assertSame([], $this->arrayStorage());
    }

    #[Test]
    public function it_bypasses_every_open_transaction_without_a_transactions_manager()
    {
        $cache = new ResultCache(app('cache'), app('config'), app('db'));

        $lookup = DB::transaction(fn () => $cache->lookup(CacheOptions::make('users', 60), 'rows', DB::table('users')));

        $this->assertSame('transaction', $lookup->reason);
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

        $router->get(self::PLAIN_URI, fn (DataTables $dataTables) => $dataTables->query(DB::table('users'))->toJson());

        $router->get(self::USERS_URI, function (DataTables $dataTables) {
            $query = DB::connection(request('connection'))->table('users');

            if (request()->has('color')) {
                $query->where('color', request('color'));
            }

            if (request()->boolean('primary')) {
                $query->useWritePdo();
            }

            return $dataTables->query($query)
                ->cache((array) request('tags', 'users'), (int) request('ttl', 60), (array) request('by', []), rows: ! request()->boolean('counts_only'))
                ->toJson();
        });

        $router->get(self::GROUPED_URI, fn (DataTables $dataTables) => $dataTables
            ->query(DB::table('posts')->select('user_id', DB::raw('count(*) as total'))->groupBy('user_id'))
            ->cache('posts')
            ->toJson());

        $router->get(self::AFTER_QUERY_URI, fn (DataTables $dataTables) => $dataTables
            ->query(DB::table('users')->afterQuery(fn ($rows) => request('redact') ? $rows->each(fn ($row) => $row->email = 'hidden') : $rows))
            ->cache('users')
            ->toJson());

        $router->get(self::LOCKING_URI, fn (DataTables $dataTables) => $dataTables
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

class StoreUnavailableException extends RuntimeException
{
    protected $message = 'Connection refused';
}

class UndeletableStore extends ArrayStore
{
    public function forget($key)
    {
        return false;
    }
}

class ReadOnlyStore extends ArrayStore
{
    public function put($key, $value, $seconds)
    {
        if (str_contains($key, ':entry:')) {
            throw new StoreUnavailableException;
        }

        return parent::put($key, $value, $seconds);
    }
}

class FailingStore implements Store
{
    public function get($key)
    {
        throw new StoreUnavailableException;
    }

    public function many(array $keys)
    {
        throw new StoreUnavailableException;
    }

    public function put($key, $value, $seconds)
    {
        throw new StoreUnavailableException;
    }

    public function putMany(array $values, $seconds)
    {
        throw new StoreUnavailableException;
    }

    public function increment($key, $value = 1)
    {
        throw new StoreUnavailableException;
    }

    public function decrement($key, $value = 1)
    {
        throw new StoreUnavailableException;
    }

    public function forever($key, $value)
    {
        throw new StoreUnavailableException;
    }

    public function touch($key, $seconds)
    {
        throw new StoreUnavailableException;
    }

    public function forget($key)
    {
        throw new StoreUnavailableException;
    }

    public function flush()
    {
        throw new StoreUnavailableException;
    }

    public function getPrefix()
    {
        return '';
    }
}
