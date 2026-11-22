<?php

namespace Yajra\DataTables\Cache;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Caches the reads DataTables performs, using the Laravel cache store.
 *
 * An entry is identified by everything that determines the result of the
 * read: the connection, the final SQL, the bindings sent to the database,
 * the values the cache is segmented by and the current generation of every
 * declared tag. Flushing a tag deletes its generation, so the entries
 * computed before become unreachable and expire with their TTL.
 *
 * The generations are read before the query runs: a slow read that
 * completes after a flush stores its result under the old
 * generation, where no subsequent read will look for it.
 */
class ResultCache
{
    /**
     * Version of the cached payload, part of every entry key.
     */
    public const int FORMAT = 1;

    /**
     * Minimum lifetime of a tag generation, in seconds.
     *
     * When a generation expires, the entries computed with it simply become
     * unreachable: it never affects the correctness of the results.
     */
    public const int TAG_TTL = 86400;

    /**
     * @param  (Closure(): mixed)|null  $transactions  Resolves the database transactions manager.
     */
    public function __construct(
        protected CacheFactory $cache,
        protected Config $config,
        protected DatabaseManager $db,
        protected ?Closure $transactions = null,
    ) {}

    /**
     * Whether caching was not switched off for the whole application.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->config->get('datatables.cache.enabled', true);
    }

    /**
     * Default TTL, in seconds, of the cached reads.
     */
    public function defaultTtl(): int
    {
        $ttl = $this->config->get('datatables.cache.ttl', 60);

        return is_numeric($ttl) ? (int) $ttl : 60;
    }

    /**
     * Get the cache store used by DataTables.
     */
    public function store(): Repository
    {
        $store = $this->config->get('datatables.cache.store');

        return $this->cache->store(is_string($store) && $store !== '' ? $store : null);
    }

    /**
     * Look up the cached result of the given read.
     *
     * A failure of the cache store is never thrown: it results in a bypass, like
     * any condition that prevents proving an entry valid, and the read is then
     * performed against the database.
     */
    public function lookup(CacheOptions $options, string $read, Builder $query): CacheLookup
    {
        // Compiling the query first applies its "before query" callbacks,
        // which may still add constraints, bindings or a lock.
        $sql = $query->toSql();
        $connection = $query->getConnection();
        $reason = $this->bypassReason($query, $connection);
        $fingerprint = is_null($reason) && $connection instanceof Connection ? $this->connectionFingerprint($connection) : null;

        if (is_null($fingerprint)) {
            return CacheLookup::bypass($read, $reason ?? 'unsupported_connection');
        }

        return $this->find($options, $read, [$fingerprint, $sql, $this->bindings($connection, $query)]);
    }

    /**
     * Get the reason why the given read cannot be cached, if any.
     */
    protected function bypassReason(Builder $query, ConnectionInterface $connection): ?string
    {
        return match (true) {
            ! $this->isEnabled() => 'disabled',
            ! $connection instanceof Connection => 'unsupported_connection',
            $this->inTransaction($connection) => 'transaction',
            ! is_null($query->lock) => 'locking_read',
            // The "after query" callbacks transform the rows after the database
            // returned them, e.g. according to the authenticated user: their
            // output does not depend on the query alone.
            (fn () => $this->afterQueryCallbacks !== [])->call($query) => 'after_query_callbacks',
            ! Payload::isScalar($this->bindings($connection, $query)) => 'unsupported_binding',
            default => null,
        };
    }

    /**
     * Get the bindings of a query, as sent to the database.
     *
     * @return list<mixed>
     */
    protected function bindings(ConnectionInterface $connection, Builder $query): array
    {
        return array_values($connection->prepareBindings($query->getBindings()));
    }

    /**
     * Find the entry of a read in the cache store.
     *
     * @param  array<int, mixed>  $query  The fingerprint of the connection, the SQL and the bindings of the read.
     */
    protected function find(CacheOptions $options, string $read, array $query): CacheLookup
    {
        try {
            $store = $this->store();
            $generations = $this->generations($store, $options);
            $key = is_null($generations) ? null : $this->entryKey([$read, ...$query, $options->by, $generations]);
            $entry = is_null($key) ? null : $store->get($key);
        } catch (Throwable $exception) {
            return CacheLookup::bypass($read, 'store_error', $exception);
        }

        if (is_null($key)) {
            return CacheLookup::bypass($read, 'tags_unavailable');
        }

        return $this->resolve($read, $key, $entry);
    }

    /**
     * Turn the value read from the cache store into the result of the lookup.
     */
    protected function resolve(string $read, string $key, mixed $entry): CacheLookup
    {
        if (is_null($entry)) {
            return CacheLookup::miss($read, $key);
        }

        if (! is_array($entry)
            || ($entry['format'] ?? null) !== self::FORMAT
            || ($entry['read'] ?? null) !== $read
            || ! array_key_exists('data', $entry)
        ) {
            return CacheLookup::miss($read, $key, 'invalid_entry');
        }

        return CacheLookup::hit($read, $key, $entry['data']);
    }

    /**
     * Store the result of a read that was missing from the cache.
     */
    public function put(CacheLookup $lookup, CacheOptions $options, mixed $data): CacheLookup
    {
        if (! $lookup->isStorable()) {
            return $lookup;
        }

        try {
            $reason = $this->write($lookup, $options, $data);
        } catch (Throwable $exception) {
            return $lookup->withReason('store_error', $exception);
        }

        return is_null($reason) ? $lookup : $lookup->withReason($reason);
    }

    /**
     * Store the entry of a read, or get the reason why it is not stored.
     */
    protected function write(CacheLookup $lookup, CacheOptions $options, mixed $data): ?string
    {
        // Without paging, e.g. for an export, a read returns every row of the
        // table: storing it would put the whole table in a single entry.
        $maxRows = $this->config->get('datatables.cache.max_rows', 1000);

        if (is_numeric($maxRows) && Payload::rowCount($data) > (int) $maxRows) {
            return 'too_many_rows';
        }

        $store = $this->store();

        if ($this->exceedsWriteLimit($store, $options)) {
            return 'write_limit';
        }

        $store->put((string) $lookup->key, [
            'format' => self::FORMAT,
            'read' => $lookup->read,
            'data' => $data,
        ], $options->ttl);

        return null;
    }

    /**
     * Flush every cached read that declared one of the given tags.
     *
     * When a database transaction is open, the flush runs once it is committed
     * and is discarded if it is rolled back. A failure of the cache store is
     * not swallowed, since the freshness could not be guaranteed.
     *
     * @param  string|array<array-key, string>  $tags
     */
    public function flush(string|array $tags): void
    {
        $keys = array_map($this->tagKey(...), CacheOptions::tags($tags));

        $this->db->connection()->afterCommit(fn () => $this->store()->deleteMultiple($keys));
    }

    /**
     * Count a write of the DataTable and check if it exceeds the configured limit.
     *
     * The searches, orders and pages of a request produce new entries, so a
     * client sending random values could otherwise fill the cache store and
     * evict unrelated data. Beyond the limit, the results are not stored.
     */
    protected function exceedsWriteLimit(Repository $store, CacheOptions $options): bool
    {
        $limit = $this->config->get('datatables.cache.max_writes_per_minute', 1000);

        if (! is_numeric($limit)) {
            return false;
        }

        $limiter = new RateLimiter($store);
        $key = $this->prefix().':writes:'.hash('sha256', serialize($options->tags));

        if ($limiter->tooManyAttempts($key, (int) $limit)) {
            return true;
        }

        $limiter->hit($key, 60);

        return false;
    }

    /**
     * Get the current generation of every tag, creating the missing ones.
     *
     * @return array<array-key, string>|null
     */
    protected function generations(Repository $store, CacheOptions $options): ?array
    {
        $keys = [];

        foreach ($options->tags as $tag) {
            $keys[$tag] = $this->tagKey($tag);
        }

        $current = $store->getMultiple(array_values($keys));
        $current = is_array($current) ? $current : iterator_to_array($current);
        $generations = [];

        foreach ($keys as $tag => $key) {
            $generation = $current[$key] ?? null;

            if (! is_string($generation) || $generation === '') {
                $generation = $this->createGeneration($store, $key, $options->ttl);
            }

            if (is_null($generation)) {
                return null;
            }

            $generations[$tag] = $generation;
        }

        return $generations;
    }

    /**
     * Create the generation of a tag, or get the one created concurrently.
     */
    protected function createGeneration(Repository $store, string $key, int $ttl): ?string
    {
        $generation = bin2hex(random_bytes(16));

        if ($store->add($key, $generation, max($ttl, self::TAG_TTL))) {
            return $generation;
        }

        $generation = $store->get($key);

        return is_string($generation) && $generation !== '' ? $generation : null;
    }

    /**
     * Check if a transaction is open on the connection, so its reads may be rolled back.
     *
     * The transactions considered are the ones that defer the "after commit"
     * callbacks, the same ones that defer a flush: the transaction
     * wrapping a test that uses RefreshDatabase is therefore ignored.
     */
    protected function inTransaction(Connection $connection): bool
    {
        if ($connection->transactionLevel() === 0) {
            return false;
        }

        $manager = is_null($this->transactions) ? null : ($this->transactions)();

        if (! $manager instanceof DatabaseTransactionsManager) {
            return true;
        }

        return $manager->callbackApplicableTransactions()
            ->contains(fn (DatabaseTransactionRecord $transaction) => $transaction->connection === $connection->getName());
    }

    /**
     * Identify the database a connection reads from, without its credentials.
     *
     * Returns null when the connection cannot be identified.
     */
    protected function connectionFingerprint(Connection $connection): ?string
    {
        $config = Arr::except((array) $connection->getConfig(), ['password', 'read.password', 'write.password']);

        try {
            return hash('sha256', serialize([
                $connection->getName(),
                $connection->getDriverName(),
                $connection->getDatabaseName(),
                $connection->getTablePrefix(),
                $config,
            ]));
        } catch (Throwable) {
            // The configuration holds values that cannot be serialized, e.g. closures.
            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    protected function entryKey(array $parts): string
    {
        return $this->prefix().':entry:'.hash('sha256', serialize([self::FORMAT, ...$parts]));
    }

    protected function tagKey(string $tag): string
    {
        return $this->prefix().':tag:'.hash('sha256', $tag);
    }

    protected function prefix(): string
    {
        $prefix = $this->config->get('datatables.cache.prefix', 'datatables');

        return is_string($prefix) && $prefix !== '' ? $prefix : 'datatables';
    }
}
