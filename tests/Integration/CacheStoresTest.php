<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Throwable;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Facades\DataTables as DataTablesFacade;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

/**
 * Runs the cache against real Laravel cache stores, none of them supporting
 * an operation the others lack: no tags, no locks and no key enumeration.
 *
 * The Redis tests only run when DATATABLES_TEST_REDIS_HOST is set, optionally
 * with DATATABLES_TEST_REDIS_PORT and DATATABLES_TEST_REDIS_DATABASE (15 by
 * default). They only delete the keys they wrote, under a random prefix.
 */
class CacheStoresTest extends TestCase
{
    protected string $prefix;

    protected string $directory;

    public static function stores(): array
    {
        return [
            'array' => ['array'],
            'file' => ['file'],
            'database' => ['database'],
            'redis' => ['redis'],
        ];
    }

    #[Test]
    #[DataProvider('stores')]
    public function it_caches_and_flushes_the_reads(string $store)
    {
        $this->useStore($store);

        [$miss, $missQueries] = $this->fetch();
        [$hit, $hitQueries] = $this->fetch();

        User::query()->whereKey(1)->update(['name' => 'Renamed']);
        DataTablesFacade::flushCache('users');
        [$flushed] = $this->fetch();

        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($miss));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($hit));
        $this->assertCount(3, $missQueries);
        $this->assertCount(1, $hitQueries, 'only the eager load runs on a hit');
        $this->assertSame($this->withoutDebug($miss), $this->withoutDebug($hit));
        $this->assertSame(['count:miss', 'rows:miss'], $this->statuses($flushed));
        $this->assertSame('Renamed', $flushed['data'][0]['name']);
    }

    #[Test]
    #[DataProvider('stores')]
    public function it_does_not_reinstall_rows_read_before_a_concurrent_flush(string $store)
    {
        $this->useStore($store);

        $flushed = false;
        Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (&$flushed) {
            if (! $flushed && str_starts_with($event->sql, 'select * from "users"')) {
                $flushed = true;
                DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);
                DataTablesFacade::flushCache('users');
            }
        });

        [$slow] = $this->fetch();
        [$next] = $this->fetch();

        $this->assertSame('Record-1', $slow['data'][0]['name']);
        $this->assertSame('Renamed', $next['data'][0]['name']);
    }

    #[Test]
    public function it_uses_the_ttl_and_keeps_the_data_out_of_the_redis_keys()
    {
        $this->useStore('redis');

        $this->fetch(['search' => ['value' => 'Record-19'], 'columns' => [
            ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
        ]]);

        $redis = Cache::store('redis')->getStore()->connection();
        $keys = $redis->keys($this->prefix.'*');

        $this->assertNotEmpty($keys);
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^'.preg_quote($this->prefix, '/').'datatables:(entry|tag|writes):[0-9a-f]{64}(:timer)?$/', $key);
            $ttl = $redis->ttl($key);
            match (true) {
                str_contains($key, ':entry:') => $this->assertTrue($ttl > 0 && $ttl <= 120, "entry TTL is $ttl"),
                str_contains($key, ':writes:') => $this->assertTrue($ttl > 0 && $ttl <= 60, "write counter TTL is $ttl"),
                default => $this->assertTrue($ttl > 120 && $ttl <= 86400, "tag TTL is $ttl"),
            };
        }
    }

    #[Test]
    public function it_reads_the_database_when_redis_is_unreachable()
    {
        $this->useStore('redis');
        config()->set('database.redis.unreachable', ['host' => '127.0.0.1', 'port' => 1, 'database' => 0, 'timeout' => 0.5]);
        config()->set('cache.stores.redis.connection', 'unreachable');
        Cache::forgetDriver('redis');
        Log::spy();

        [$response, $queries] = $this->fetch();

        $this->assertSame(['count:bypass', 'rows:bypass'], $this->statuses($response));
        $this->assertSame('store_error', $response['cache'][0]['reason']);
        $this->assertCount(3, $queries);
        $this->assertSame(20, $response['recordsTotal']);
        Log::shouldHaveReceived('warning')->twice();

        $this->expectException(Throwable::class);
        DataTablesFacade::flushCache('users');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/cache/stores/users', fn (DataTables $dataTables) => $dataTables
            ->eloquent(User::query()->with('posts'))
            ->cache('users', 120)
            ->toJson());
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }

        if (config('cache.stores.redis.connection') === 'cache_tests') {
            try {
                $redis = Cache::store('redis')->getStore()->connection();
                foreach ($redis->keys($this->prefix.'*') as $key) {
                    $redis->del($key);
                }
            } catch (Throwable) {
                // Redis was not reachable.
            }
        }

        parent::tearDown();
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        // Deny the unserialization of any class, like a hardened application would.
        $app['config']->set('cache.serializable_classes', false);
        $app['config']->set('cache.prefix', $this->prefix = 'datatables-tests-'.Str::random(8).':');
        $app['config']->set('cache.stores.array', ['driver' => 'array', 'serialize' => true]);
        $app['config']->set('cache.stores.file', ['driver' => 'file', 'path' => $this->directory = sys_get_temp_dir().'/datatables-cache-tests-'.Str::random(8)]);
        $app['config']->set('database.connections.cache', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.stores.database', ['driver' => 'database', 'connection' => 'cache', 'table' => 'cache']);
        $app['config']->set('database.redis.client', 'phpredis');
        $app['config']->set('database.redis.options.prefix', '');
        $app['config']->set('database.redis.cache_tests', [
            'host' => env('DATATABLES_TEST_REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('DATATABLES_TEST_REDIS_PORT', 6379),
            'database' => (int) env('DATATABLES_TEST_REDIS_DATABASE', 15),
            'timeout' => 1,
        ]);
        $app['config']->set('cache.stores.redis', ['driver' => 'redis', 'connection' => 'cache_tests']);
    }

    protected function useStore(string $store): void
    {
        if ($store === 'redis') {
            if (! env('DATATABLES_TEST_REDIS_HOST')) {
                $this->markTestSkipped('Set DATATABLES_TEST_REDIS_HOST to run the Redis tests.');
            }

            if (! extension_loaded('redis')) {
                $this->markTestSkipped('The phpredis extension is required.');
            }

            try {
                Cache::store('redis')->getStore()->connection()->ping();
            } catch (Throwable) {
                $this->markTestSkipped('Redis is not reachable.');
            }
        }

        if ($store === 'database') {
            DB::connection('cache')->getSchemaBuilder()->create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->bigInteger('expiration')->index();
            });
        }

        config()->set('datatables.cache.store', $store);
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    protected function fetch(array $parameters = []): array
    {
        $queries = [];
        $listener = function (QueryExecuted $event) use (&$queries) {
            if ($event->connectionName !== 'cache') {
                $queries[] = $event->sql;
            }
        };
        Event::listen(QueryExecuted::class, $listener);

        $response = $this->call('GET', '/cache/stores/users', $parameters);
        $response->assertOk();

        Event::forget(QueryExecuted::class);

        return [$response->json(), $queries];
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
