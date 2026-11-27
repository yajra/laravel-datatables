<?php

namespace Yajra\DataTables\Cache;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Database\DatabaseTransactionsManager;

/**
 * Finds the transactions of a connection that defer the "after commit" callbacks.
 *
 * The transaction wrapping a test that uses RefreshDatabase is not one of
 * them: its reads are cached and its flushes run immediately, like the
 * "after commit" callbacks of the application.
 */
final readonly class Transactions
{
    /**
     * @param  (Closure(): mixed)|null  $manager  Resolves the database transactions manager.
     */
    public function __construct(private ?Closure $manager) {}

    /**
     * Check if a transaction is open on the connection, so its reads may be rolled back.
     */
    public function isOpen(Connection $connection): bool
    {
        if ($connection->transactionLevel() === 0) {
            return false;
        }

        $manager = $this->manager();

        return is_null($manager) || ! is_null($this->current($manager, $connection));
    }

    /**
     * Run the callback once the current transaction of the connection is committed.
     *
     * The callback is attached to the transaction of the given connection,
     * not to the last transaction opened on any connection: a rollback on
     * another connection does not discard it. When the connection has no
     * open transaction and $anyConnection is true, the callback is attached
     * to the last transaction opened on any connection, like Laravel does,
     * since the data may have been written there. It runs immediately when
     * no transaction defers it.
     */
    public function afterCommit(Connection $connection, Closure $callback, bool $anyConnection = false): void
    {
        $manager = $this->manager();
        $transaction = is_null($manager) ? null : $this->current($manager, $connection);

        match (true) {
            ! is_null($transaction) => $transaction->addCallback($callback),
            $anyConnection && ! is_null($manager) => $manager->addCallback($callback),
            is_null($manager) && $connection->transactionLevel() > 0 => $connection->afterCommit($callback),
            default => $callback(),
        };
    }

    /**
     * Get the innermost transaction of the connection that defers the callbacks.
     */
    private function current(DatabaseTransactionsManager $manager, Connection $connection): ?DatabaseTransactionRecord
    {
        return $manager->callbackApplicableTransactions()
            ->last(fn (DatabaseTransactionRecord $transaction) => $transaction->connection === $connection->getName());
    }

    private function manager(): ?DatabaseTransactionsManager
    {
        $manager = is_null($this->manager) ? null : ($this->manager)();

        return $manager instanceof DatabaseTransactionsManager ? $manager : null;
    }
}
