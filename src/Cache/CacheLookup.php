<?php

namespace Yajra\DataTables\Cache;

use Throwable;

/**
 * Outcome of looking up a cached query read.
 */
final readonly class CacheLookup
{
    public const string HIT = 'hit';

    public const string MISS = 'miss';

    public const string BYPASS = 'bypass';

    private function __construct(
        public string $read,
        public string $status,
        public ?string $key = null,
        public mixed $data = null,
        public ?string $reason = null,
        public ?Throwable $exception = null,
    ) {}

    public static function hit(string $read, string $key, mixed $data): self
    {
        return new self($read, self::HIT, $key, $data);
    }

    public static function miss(string $read, string $key, ?string $reason = null): self
    {
        return new self($read, self::MISS, $key, reason: $reason);
    }

    public static function bypass(string $read, string $reason, ?Throwable $exception = null): self
    {
        return new self($read, self::BYPASS, reason: $reason, exception: $exception);
    }

    /**
     * Whether the result of the read may be written to the cache.
     */
    public function isStorable(): bool
    {
        return $this->status === self::MISS;
    }

    /**
     * Turn a hit whose entry could not be restored into a miss.
     */
    public function asInvalidEntry(): self
    {
        return self::miss($this->read, (string) $this->key, 'invalid_entry');
    }

    public function withReason(string $reason, ?Throwable $exception = null): self
    {
        return new self($this->read, $this->status, $this->key, null, $reason, $exception);
    }

    /**
     * Non-sensitive representation used by the debugger output.
     *
     * @return array{read: string, status: string, reason: string|null, key: string|null}
     */
    public function toArray(): array
    {
        return [
            'read' => $this->read,
            'status' => $this->status,
            'reason' => $this->reason,
            'key' => $this->key,
        ];
    }
}
