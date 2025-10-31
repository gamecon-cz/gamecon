<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

class AbstractDoctrineTestDb extends AbstractTestDb
{
    // Disable legacy mysqli transaction wrapping because this test uses Doctrine factories.
    // Doctrine uses a separate PDO connection, so legacy mysqli transactions would cause deadlocks.
    // Foundry (via the Factories trait) handles transaction management for Doctrine.
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }
}
