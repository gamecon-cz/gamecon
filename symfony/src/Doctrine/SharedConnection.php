<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

/**
 * Legacy code and every kernel booted in the process use one instance per database,
 * so a legacy transaction and a Doctrine write inside it are one transaction.
 */
class SharedConnection extends Connection
{
    /**
     * @var array<string, self>
     */
    private static array $sharedByDatabase = [];

    private static bool $kernelShuttingDown = false;

    /**
     * @var list<callable(): void>
     */
    private array $afterCommit = [];

    public static function share(self $connection): self
    {
        return self::$sharedByDatabase[$connection->databaseKey()] ??= $connection;
    }

    /**
     * A kernel closes its connections on shutdown, but this one belongs to the process:
     * legacy code and other kernels are still using it, maybe inside a transaction.
     */
    public static function keepOpenWhile(callable $shutdown): void
    {
        self::$kernelShuttingDown = true;
        try {
            $shutdown();
        } finally {
            self::$kernelShuttingDown = false;
        }
    }

    public function close(): void
    {
        if (self::$kernelShuttingDown) {
            return;
        }
        $this->afterCommit = [];
        parent::close();
    }

    /**
     * Another process, such as a worker, sees the data only once the outermost transaction
     * commits; a commit of a nested one is just a released savepoint.
     *
     * @param callable(): void $callback
     */
    public function afterCommit(callable $callback): void
    {
        if (! $this->isTransactionActive()) {
            $callback();

            return;
        }
        $this->afterCommit[] = $callback;
    }

    public function commit(): bool
    {
        $result = parent::commit();
        if (! $this->isTransactionActive()) {
            $callbacks = $this->afterCommit;
            $this->afterCommit = [];
            foreach ($callbacks as $callback) {
                $callback();
            }
        }

        return $result;
    }

    /**
     * A deadlock rolls back the whole transaction on the server, savepoints included, and
     * rolling back to a savepoint then fails and leaves DBAL counting a transaction that is gone.
     */
    public function rollBack(): bool
    {
        try {
            $result = parent::rollBack();
        } catch (Exception $exception) {
            if ($this->serverHasTransaction()) {
                throw $exception;
            }
            $this->close();

            return true;
        }
        if (! $this->isTransactionActive()) {
            $this->afterCommit = [];
        }

        return $result;
    }

    private function serverHasTransaction(): bool
    {
        try {
            return (bool) $this->fetchOne('SELECT @@in_transaction');
        } catch (Exception) {
            return false;
        }
    }

    private function databaseKey(): string
    {
        $params = $this->getParams();

        return implode('|', [
            $params['host'] ?? '',
            (string) ($params['port'] ?? ''),
            $params['dbname'] ?? '',
            $params['user'] ?? '',
        ]);
    }
}
