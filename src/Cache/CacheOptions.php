<?php

namespace Yajra\DataTables\Cache;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Cache settings of a DataTable, validated when cache() is called.
 */
final readonly class CacheOptions
{
    /**
     * @param  list<string>  $tags
     * @param  array<array-key, mixed>  $by
     */
    private function __construct(
        public array $tags,
        public int $ttl,
        public array $by,
        public bool $rows,
    ) {}

    /**
     * @param  string|array<array-key, string>  $tags
     * @param  array<array-key, mixed>  $by
     * @param  bool  $rows  Whether the rows are cached, or only the counts.
     */
    public static function make(string|array $tags, DateTimeInterface|DateInterval|int $ttl, array $by = [], bool $rows = true): self
    {
        $seconds = self::seconds($ttl);

        if ($seconds < 1) {
            throw new InvalidArgumentException('The DataTables cache TTL must be at least one second.');
        }

        self::assertScalarTree($by);

        return new self(self::tags($tags), $seconds, $by, $rows);
    }

    /**
     * Normalize the given tags into a sorted list of unique names.
     *
     * @param  string|array<array-key, string>  $tags
     * @return list<string>
     */
    public static function tags(string|array $tags): array
    {
        $tags = is_array($tags) ? $tags : [$tags];

        foreach ($tags as $tag) {
            if (! is_string($tag) || trim($tag) === '') {
                throw new InvalidArgumentException('A DataTables cache tag must be a non-empty string.');
            }
        }

        if ($tags === []) {
            throw new InvalidArgumentException('Caching a DataTable requires at least one tag to flush it.');
        }

        $tags = array_values(array_unique($tags));
        sort($tags);

        return $tags;
    }

    private static function seconds(DateTimeInterface|DateInterval|int $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            $now = new DateTimeImmutable;

            return $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }

        if ($ttl instanceof DateTimeInterface) {
            return $ttl->getTimestamp() - time();
        }

        return $ttl;
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private static function assertScalarTree(array $values): void
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                self::assertScalarTree($value);
            } elseif (! is_null($value) && ! is_scalar($value)) {
                throw new InvalidArgumentException('The values the DataTables cache is segmented by may only be scalar values, null or arrays of them.');
            }
        }
    }
}
