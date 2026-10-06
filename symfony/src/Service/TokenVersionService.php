<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Raising a user's version cuts off every token issued before it, however many the pages have
 * handed out. A user with no row is on version 0, so tokens issued before this existed, which
 * carry no version, stay valid until they expire or the user logs out.
 */
class TokenVersionService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function current(int $userId): int
    {
        $version = $this->connection->fetchOne(
            'SELECT version FROM user_token_version WHERE user_id = :userId',
            [
                'userId' => $userId,
            ],
        );

        return $version === false ? 0 : (int) $version;
    }

    public function invalidate(int $userId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO user_token_version (user_id, version) VALUES (:userId, 1)
             ON DUPLICATE KEY UPDATE version = version + 1',
            [
                'userId' => $userId,
            ],
        );
    }
}
