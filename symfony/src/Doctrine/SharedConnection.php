<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\DBAL\Connection;

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

    public static function share(self $connection): self
    {
        return self::$sharedByDatabase[$connection->databaseKey()] ??= $connection;
    }

    /**
     * A kernel shutting down closes its connections, which inside a transaction would
     * silently roll back work that legacy code or another kernel still has open.
     */
    public function close(): void
    {
        if ($this->isTransactionActive()) {
            return;
        }
        parent::close();
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
