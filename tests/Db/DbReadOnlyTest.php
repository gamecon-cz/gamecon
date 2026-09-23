<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

class DbReadOnlyTest extends AbstractTestDb
{
    // the read-only connection is a different one, it would not see rows of an uncommitted test transaction
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // a TEMPORARY table would not do, MariaDB lets even a read-only transaction write into one
        dbQuery('DROP TABLE IF EXISTS tmp_read_only_probe');
        dbQuery('CREATE TABLE tmp_read_only_probe (id INT NOT NULL)');
    }

    protected function tearDown(): void
    {
        dbQuery('DROP TABLE IF EXISTS tmp_read_only_probe');
        parent::tearDown();
    }

    public function testZapisUvnitrReadOnlyDatabazeOdmitne(): void
    {
        try {
            dbReadOnly(static fn () => dbQuery('INSERT INTO tmp_read_only_probe (id) VALUES (1)'));
            self::fail('Zápis v read-only režimu měl selhat');
        } catch (\DbException $exception) {
            self::assertSame(self::kodOdmitnutehoZapisu(), $exception->getCode(), $exception->getMessage());
        }

        self::assertSame(0, (int) dbOneCol('SELECT COUNT(*) FROM tmp_read_only_probe'));
    }

    public function testReadOnlySpojeniSePrihlasiJakoUzivatelJenProCteni(): void
    {
        self::assertSame(
            DB_READONLY_USER,
            dbReadOnly(static fn () => dbOneCol("SELECT SUBSTRING_INDEX(CURRENT_USER(), '@', 1)")),
        );
    }

    public function testCteniUvnitrReadOnlyProjde(): void
    {
        self::assertSame(
            '1',
            dbReadOnly(static fn () => dbOneCol('SELECT 1')),
        );
    }

    /**
     * The persistent connection is reused by the following requests; had it become read-only,
     * the whole application would have been read-only for everyone.
     */
    public function testSdileneSpojeniNikdyNeniReadOnly(): void
    {
        $sdileneSpojeni = dbConnect();

        dbReadOnly(static function () use ($sdileneSpojeni) {
            self::assertNotSame($sdileneSpojeni, dbConnect());
            self::assertSame(1, (int) dbOneCol('SELECT @@tx_read_only'));
            self::assertSame(0, (int) dbOneCol('SELECT @@tx_read_only', null, $sdileneSpojeni));
        });

        self::assertSame($sdileneSpojeni, dbConnect());
        self::assertSame(0, (int) dbOneCol('SELECT @@tx_read_only'));
    }

    public function testPoVyjimceJeZpetSdileneSpojeni(): void
    {
        $sdileneSpojeni = dbConnect();
        try {
            dbReadOnly(static function () {
                dbQuery('SELECT 1');
                throw new \RuntimeException('selhání reportu');
            });
        } catch (\RuntimeException) {
        }

        self::assertSame($sdileneSpojeni, dbConnect());
        dbQuery('INSERT INTO tmp_read_only_probe (id) VALUES (1)');
        self::assertSame(1, (int) dbOneCol('SELECT COUNT(*) FROM tmp_read_only_probe'));
    }

    public function testPrepnutiNaReadOnlySpojeniNechaSdileneZapisovatelne(): void
    {
        global $spojeni, $dbJenProCteni;
        $sdileneSpojeni = dbConnect();
        try {
            dbSwitchToReadOnlyConnection();

            self::assertSame(1, (int) dbOneCol('SELECT @@tx_read_only'));
            self::assertSame(0, (int) dbOneCol('SELECT @@tx_read_only', null, $sdileneSpojeni));
            try {
                dbQuery('INSERT INTO tmp_read_only_probe (id) VALUES (1)');
                self::fail('Zápis po přepnutí na read-only spojení měl selhat');
            } catch (\DbException $exception) {
                self::assertSame(self::kodOdmitnutehoZapisu(), $exception->getCode(), $exception->getMessage());
            }
        } finally {
            dbClose();
            $dbJenProCteni = false;
            $spojeni = $sdileneSpojeni;
        }
    }

    public function testZnovupripojeniZustaneReadOnly(): void
    {
        $sdileneSpojeni = dbConnect();

        dbReadOnly(static function () use ($sdileneSpojeni) {
            $spojeniJenProCteni = dbConnect();
            $noveSpojeni = dbConnect(reconnect: true);

            self::assertNotSame($spojeniJenProCteni, $noveSpojeni);
            self::assertNotSame($sdileneSpojeni, $noveSpojeni);
            self::assertSame(1, (int) dbOneCol('SELECT @@tx_read_only'));
        });

        self::assertSame($sdileneSpojeni, dbConnect());
    }

    public function testReadOnlySpojeniNezapisujeRocnikDoNastaveni(): void
    {
        $rocnikVNastaveni = "SELECT hodnota FROM systemove_nastaveni WHERE klic = 'ROCNIK'";
        $puvodniRocnik = dbOneCol($rocnikVNastaveni);
        dbQuery("UPDATE systemove_nastaveni SET hodnota = '1999' WHERE klic = 'ROCNIK'");
        try {
            self::assertSame(ROCNIK, (int) dbReadOnly(static fn () => dbOneCol('SELECT @rocnik')));
            self::assertSame('1999', dbOneCol($rocnikVNastaveni));
        } finally {
            dbQuery("UPDATE systemove_nastaveni SET hodnota = $0 WHERE klic = 'ROCNIK'", [$puvodniRocnik]);
        }
    }

    public function testDocasneSpojeniZustaneReadOnly(): void
    {
        dbReadOnly(static function () {
            $docasneSpojeni = dbConnectTemporary();
            try {
                self::assertSame(1, (int) dbOneCol('SELECT @@tx_read_only', null, $docasneSpojeni));
            } finally {
                mysqli_close($docasneSpojeni);
            }
        });
    }

    public function testVnorenyReadOnlyPouzijeStejneSpojeni(): void
    {
        dbReadOnly(static function () {
            $vnejsiSpojeni = dbConnect();
            dbReadOnly(static fn () => self::assertSame($vnejsiSpojeni, dbConnect()));

            self::assertSame($vnejsiSpojeni, dbConnect());
            self::assertSame(1, (int) dbOneCol('SELECT @@tx_read_only'));
        });
    }

    public function testTransakceVReadOnlyNeovlivniHloubkuTransakciSdilenehoSpojeni(): void
    {
        $GLOBALS['dbTransactionDepth'] = 2;
        try {
            dbReadOnly(static function () {
                self::assertSame(0, $GLOBALS['dbTransactionDepth']);
                dbBegin();
                throw new \RuntimeException('selhání reportu');
            });
        } catch (\RuntimeException) {
        } finally {
            $hloubkaPoReadOnly = $GLOBALS['dbTransactionDepth'];
            $GLOBALS['dbTransactionDepth'] = 0;
        }

        self::assertSame(2, $hloubkaPoReadOnly);
    }

    /**
     * A SELECT-only account is refused on privileges (1142) before the read-only session is even consulted,
     * so each environment has to expect the layer that is actually in effect.
     */
    private static function kodOdmitnutehoZapisu(): int
    {
        return DB_READONLY_USER === DB_USER
            ? 1792
            : 1142;
    }
}
