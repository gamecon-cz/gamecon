<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use App\Doctrine\SharedConnection;
use App\Kernel;
use App\Tests\Support\SoubeznaTransakce;
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

    public function testPoDeadlockuSeTransakceUzavreStejneJakoNaServeru(): void
    {
        [$prvni, $druhy] = array_map('intval', dbOneArray(
            'SELECT id_nastaveni FROM systemove_nastaveni ORDER BY id_nastaveni LIMIT 2',
        ));
        dbBegin();
        dbBegin();
        dbQuery('SELECT id_nastaveni FROM systemove_nastaveni WHERE id_nastaveni = $0 FOR UPDATE', [$druhy]);
        // The rival changes a row, so InnoDB picks this transaction, which changed none, as the victim.
        $souper = SoubeznaTransakce::spust($this->doctrine(), [
            ['sql', "UPDATE systemove_nastaveni SET hodnota = CONCAT(hodnota, 'x') WHERE id_nastaveni = {$prvni}"],
            ['hlasim', 'drzi prvni'],
            ['cekej', 700],
            ['sql', "SELECT id_nastaveni FROM systemove_nastaveni WHERE id_nastaveni = {$druhy} FOR UPDATE"],
        ]);

        $deadlock = null;
        try {
            dbQuery('SELECT id_nastaveni FROM systemove_nastaveni WHERE id_nastaveni = $0 FOR UPDATE', [$prvni]);
        } catch (\Throwable $chyba) {
            $deadlock = $chyba;
        }
        dbRollback();
        dbRollback();

        self::assertNotNull($deadlock, 'Tahle transakce měla být obětí deadlocku');
        self::assertSame('hotovo', $souper->dokonci());
        self::assertFalse($this->doctrine()->isTransactionActive());
        dbBegin();
        self::assertSame(1, (int) dbOneCol('SELECT @@in_transaction'));
        dbCommit();
        self::assertSame(0, (int) dbOneCol('SELECT @@in_transaction'));
    }

    public function testZavreniMimoVypinaniKerneluTransakciOpravduZavre(): void
    {
        dbBegin();

        $this->doctrine()->close();

        self::assertFalse($this->doctrine()->isTransactionActive());
    }

    public function testPoCommituSeSpustiAzPoVnejsimCommitu(): void
    {
        $spusteno = 0;
        dbBegin();
        $this->doctrine()->transactional(function () use (&$spusteno) {
            $this->doctrine()->afterCommit(static function () use (&$spusteno) {
                ++$spusteno;
            });
        });

        self::assertSame(0, $spusteno, 'Vnitřní commit je jen savepoint, data ještě nikdo jiný nevidí');
        dbCommit();
        self::assertSame(1, $spusteno);
    }

    public function testPoRollbackuSeNespustiNic(): void
    {
        $spusteno = false;
        dbBegin();
        $this->doctrine()->afterCommit(static function () use (&$spusteno) {
            $spusteno = true;
        });
        dbRollback();
        dbBegin();
        dbCommit();

        self::assertFalse($spusteno);
    }

    public function testPoNepovedenemCommituSeNespustiNicAniPozdeji(): void
    {
        $spusteno = false;
        dbBegin();
        $this->doctrine()->afterCommit(static function () use (&$spusteno) {
            $spusteno = true;
        });
        // what an implicit commit (DDL, LOCK TABLES) does behind Doctrine's back
        dbConnect()->exec('COMMIT');
        try {
            dbCommit();
        } catch (\Throwable) {
        }
        dbBegin();
        dbCommit();

        self::assertFalse($spusteno);
    }

    public function testBezTransakceSePoCommituSpustiHned(): void
    {
        $spusteno = false;

        $this->doctrine()->afterCommit(static function () use (&$spusteno) {
            $spusteno = true;
        });

        self::assertTrue($spusteno);
    }

    private function doctrine(): SharedConnection
    {
        return $GLOBALS['systemoveNastaveni']->kernel()->getContainer()->get('doctrine.dbal.default_connection');
    }
}
