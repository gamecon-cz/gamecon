<?php

declare(strict_types=1);

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Base class for Symfony-stack tests that write to the database.
 *
 * Wraps each test method in a transaction on the Doctrine connection and rolls
 * it back afterwards, so a test leaves the database as it found it.
 *
 * The legacy Gamecon\Tests\Db\AbstractTestDb does the same thing, but on the
 * legacy mysqli/PDO connection — a different connection from Doctrine's, as
 * SELECT CONNECTION_ID() on both confirms. Its transaction therefore cannot
 * cover Doctrine writes, and mixing legacy fixtures with Doctrine reads of the
 * same rows deadlocks on row locks (documented in CLAUDE.md). Hence a separate
 * wrapper here rather than reusing that one.
 *
 * Extends ApiTestCase so subclasses can use createClient(); a test that needs
 * no HTTP request simply never calls it.
 */
abstract class AbstractDatabaseKernelTestCase extends ApiTestCase
{
    /**
     * Booting a kernel shuts down the previous one, which would discard the
     * connection holding this test's transaction. The kernel is booted once in
     * setUp() below, so createClient() must reuse it rather than boot its own.
     * (This is also the API Platform 5.0 default, so setting it silences the
     * deprecation notice.)
     */
    protected static ?bool $alwaysBootKernel = false;

    protected function setUp(): void
    {
        parent::setUp();
        static::bootKernel();
        $this->connection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->connection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        // The identity map still holds entities whose rows have just been rolled
        // back; without clearing it the next test in the class would read them.
        $this->entityManager()->clear();

        parent::tearDown();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function connection(): Connection
    {
        return $this->entityManager()->getConnection();
    }

    /**
     * @param array<string, mixed> $headers extra headers, merged over the JSON-LD Accept
     */
    protected function jsonLdClient(array $headers = []): Client
    {
        return static::createClient([], [
            'headers' => [
                'Accept' => 'application/ld+json',
            ] + $headers,
        ]);
    }
}
