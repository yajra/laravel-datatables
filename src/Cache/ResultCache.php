<?php

namespace Yajra\DataTables\Cache;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use stdClass;
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
        if (! $this->isEnabled()) {
            return CacheLookup::bypass($read, 'disabled');
        }

        $connection = $query->getConnection();

        if (! $connection instanceof Connection) {
            return CacheLookup::bypass($read, 'unsupported_connection');
        }

        if ($this->inTransaction($connection)) {
            return CacheLookup::bypass($read, 'transaction');
        }

        // Compiling the query first applies its "before query" callbacks,
        // which may still add constraints and bindings.
        $sql = $query->toSql();

        if (! is_null($query->lock)) {
            return CacheLookup::bypass($read, 'locking_read');
        }

        // The "after query" callbacks transform the rows after the database
        // returned them, e.g. according to the authenticated user: their
        // output does not depend on the query alone.
        if ((fn () => $this->afterQueryCallbacks !== [])->call($query)) {
            return CacheLookup::bypass($read, 'after_query_callbacks');
        }

        $bindings = array_values($connection->prepareBindings($query->getBindings()));

        foreach ($bindings as $binding) {
            if (! is_null($binding) && ! is_scalar($binding)) {
                return CacheLookup::bypass($read, 'unsupported_binding');
            }
        }

        try {
            $connectionId = $this->connectionFingerprint($connection);
        } catch (Throwable) {
            return CacheLookup::bypass($read, 'unsupported_connection');
        }

        try {
            $store = $this->store();
            $generations = $this->generations($store, $options);

            if (is_null($generations)) {
                return CacheLookup::bypass($read, 'tags_unavailable');
            }

            $key = $this->entryKey([$read, $connectionId, $sql, $bindings, $options->by, $generations]);
            $entry = $store->get($key);
        } catch (Throwable $exception) {
            return CacheLookup::bypass($read, 'store_error', $exception);
        }

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

        if ($this->exceedsMaxRows($data)) {
            return $lookup->withReason('too_many_rows');
        }

        try {
            $store = $this->store();

            if ($this->exceedsWriteLimit($store, $options)) {
                return $lookup->withReason('write_limit');
            }

            $store->put((string) $lookup->key, [
                'format' => self::FORMAT,
                'read' => $lookup->read,
                'data' => $data,
            ], $options->ttl);
        } catch (Throwable $exception) {
            return $lookup->withReason('store_error', $exception);
        }

        return $lookup;
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
     * Convert the rows of a query into a cacheable payload.
     *
     * Returns null when a row holds anything else than scalar values, e.g. a
     * stream or an object built by an "after query" callback.
     *
     * @param  iterable<array-key, mixed>  $rows
     * @return array{objects: bool, rows: list<array<array-key, scalar|null>>}|null
     */
    public function exportRows(iterable $rows): ?array
    {
        $objects = null;
        $export = [];

        foreach ($rows as $row) {
            $isObject = is_object($row) && $row::class === stdClass::class;

            if (! $isObject && ! is_array($row)) {
                return null;
            }

            if (! is_null($objects) && $objects !== $isObject) {
                return null;
            }

            $objects = $isObject;
            $values = (array) $row;

            if (! $this->isScalarRow($values)) {
                return null;
            }

            $export[] = $values;
        }

        return ['objects' => $objects ?? true, 'rows' => $export];
    }

    /**
     * Restore the rows of a cached payload, or null if the payload is invalid.
     *
     * @return list<stdClass|array<array-key, scalar|null>>|null
     */
    public function importRows(mixed $data): ?array
    {
        if (! is_array($data)
            || ! is_bool($data['objects'] ?? null)
            || ! is_array($data['rows'] ?? null)
            || ! array_is_list($data['rows'])
        ) {
            return null;
        }

        $rows = [];

        foreach ($data['rows'] as $row) {
            if (! is_array($row) || ! $this->isScalarRow($row)) {
                return null;
            }

            $rows[] = $data['objects'] ? (object) $row : $row;
        }

        return $rows;
    }

    /**
     * Restore a cached count, or null if the payload is invalid.
     */
    public function importCount(mixed $data): ?int
    {
        return is_int($data) && $data >= 0 ? $data : null;
    }

    /**
     * Check if the payload holds more rows than a single entry may store.
     *
     * Without paging, e.g. for an export, a read returns every row of the
     * table: storing it would put the whole table in a single entry.
     */
    protected function exceedsMaxRows(mixed $data): bool
    {
        $limit = $this->config->get('datatables.cache.max_rows', 1000);

        return is_numeric($limit)
            && is_array($data)
            && is_array($data['rows'] ?? null)
            && count($data['rows']) > (int) $limit;
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
     */
    protected function connectionFingerprint(Connection $connection): string
    {
        $config = Arr::except((array) $connection->getConfig(), ['password', 'read.password', 'write.password']);

        return hash('sha256', serialize([
            $connection->getName(),
            $connection->getDriverName(),
            $connection->getDatabaseName(),
            $connection->getTablePrefix(),
            $config,
        ]));
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

    /**
     * @param  array<array-key, mixed>  $values
     */
    protected function isScalarRow(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_null($value) && ! is_scalar($value)) {
                return false;
            }
        }

        return true;
    }
}
