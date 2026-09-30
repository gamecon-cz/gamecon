<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use App\Kernel;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * A temporary table lives in one database session only, so seeing it from both sides
 * proves legacy and Doctrine run on the same physical connection.
 */
class JednoSpojeniTest extends TestCase
{
    protected function setUp(): void
    {
        dbQuery('CREATE TEMPORARY TABLE IF NOT EXISTS tmp_jedno_spojeni (cislo INT)');
        dbQuery('DELETE FROM tmp_jedno_spojeni');
    }

    protected function tearDown(): void
    {
        while ($this->doctrine()->isTransactionActive()) {
            $this->doctrine()->rollBack();
        }
        dbQuery('DROP TEMPORARY TABLE IF EXISTS tmp_jedno_spojeni');
    }

    public function testLegacyPouzivaSpojeniDoctrine(): void
    {
        self::assertSame($this->doctrine()->getNativeConnection(), dbConnect());
    }

    public function testDoctrineVidiLegacyTransakci(): void
    {
        dbBegin();

        self::assertTrue($this->doctrine()->isTransactionActive());
    }

    public function testDoctrineZapisUvnitrLegacyTransakceSeVratiSNi(): void
    {
        dbBegin();
        $this->doctrine()->transactional(
            static fn (Connection $connection) => $connection->executeStatement('INSERT INTO tmp_jedno_spojeni VALUES (1)'),
        );
        dbRollback();

        self::assertSame(0, (int) dbOneCol('SELECT COUNT(*) FROM tmp_jedno_spojeni'));
    }

    public function testLegacyZapisUvnitrDoctrineTransakceSeVratiSNi(): void
    {
        $this->doctrine()->beginTransaction();
        dbBegin();
        dbQuery('INSERT INTO tmp_jedno_spojeni VALUES (1)');
        dbCommit();
        $this->doctrine()->rollBack();

        self::assertSame(0, (int) dbOneCol('SELECT COUNT(*) FROM tmp_jedno_spojeni'));
    }

    public function testDalsiKernelDostaneStejneSpojeni(): void
    {
        $dalsiKernel = new Kernel('test', false);
        $dalsiKernel->boot();
        try {
            self::assertSame($this->doctrine(), $dalsiKernel->getContainer()->get('doctrine.dbal.default_connection'));
        } finally {
            $dalsiKernel->shutdown();
        }
    }

    public function testVypnutiDalsihoKerneluNeukonciOtevrenouTransakci(): void
    {
        dbBegin();
        dbQuery('INSERT INTO tmp_jedno_spojeni VALUES (1)');

        $dalsiKernel = new Kernel('test', false);
        $dalsiKernel->boot();
        $dalsiKernel->getContainer()->get('doctrine.dbal.default_connection');
        $dalsiKernel->shutdown();

        self::assertTrue($this->doctrine()->isTransactionActive());
        dbRollback();
        self::assertSame(0, (int) dbOneCol('SELECT COUNT(*) FROM tmp_jedno_spojeni'));
    }

    private function doctrine(): Connection
    {
        return $GLOBALS['systemoveNastaveni']->kernel()->getContainer()->get('doctrine.dbal.default_connection');
    }
}
