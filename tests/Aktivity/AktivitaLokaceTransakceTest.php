<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Místnosti a hlavní místnost se ukládají v jedné transakci: když selže poslední krok (zápis hlavní místnosti),
 * nesmí zůstat smazané ani přidané místnosti.
 */
class AktivitaLokaceTransakceTest extends AbstractTestDb
{
    private const ID_AKTIVITY_SE_SELHANIM = 2199;

    protected static bool $disableStrictTransTables = true;

    /**
     * @return array<string>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            <<<'SQL'
INSERT INTO lokace(id_lokace, nazev, dvere, poznamka, poradi, rok)
VALUES
    (3201, 'Transakce místnost 1', '', '', 3201, 0),
    (3202, 'Transakce místnost 2', '', '', 3202, 0),
    (3203, 'Transakce místnost 3', '', '', 3203, 0)
SQL,
            <<<'SQL'
INSERT INTO akce_seznam(id_akce, nazev_akce, typ, rok, stav, id_hlavni_lokace)
VALUES (2199, 'Aktivita se selhávajícím uložením hlavní místnosti', 1, 2024, 2, 3202)
SQL,
            <<<'SQL'
INSERT INTO akce_lokace(id_akce, id_lokace)
VALUES (2199, 3201), (2199, 3202)
SQL,
        ];
    }

    public static function setUpBeforeClass(): void
    {
        // Trigger je DDL a ten potvrdí rozpracovanou transakci, proto vzniká ještě před transakcí třídy.
        // Shodí každou změnu jednoho řádku akce_seznam, tedy i zápis hlavní místnosti.
        dbQuery(<<<'SQL'
            CREATE TRIGGER test_selhani_zapisu_hlavni_lokace BEFORE UPDATE ON akce_seznam FOR EACH ROW
            BEGIN
                IF NEW.id_akce = 2199 THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Testovací selhání zápisu hlavní místnosti';
                END IF;
            END
            SQL);

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        dbQuery(<<<'SQL'
            DROP TRIGGER IF EXISTS test_selhani_zapisu_hlavni_lokace
            SQL);
    }

    public function testSelhaniZapisuHlavniLokaceVratiMistnosti(): void
    {
        $aktivita = Aktivita::zId(self::ID_AKTIVITY_SE_SELHANIM);

        try {
            $aktivita->nastavLokacePodleIds([3203], 3203);
            self::fail('Zápis hlavní místnosti měl selhat na testovacím triggeru');
        } catch (\Throwable $throwable) {
            self::assertStringContainsString('Testovací selhání zápisu hlavní místnosti', $throwable->getMessage());
        }

        self::assertSame(
            [3201, 3202],
            array_map('intval', dbFetchColumn(<<<'SQL'
                SELECT id_lokace FROM akce_lokace WHERE id_akce = 2199 ORDER BY id_lokace
                SQL)),
            'Smazané a přidané místnosti se musí vrátit',
        );
        self::assertSame('3202', dbOneCol(<<<'SQL'
            SELECT id_hlavni_lokace FROM akce_seznam WHERE id_akce = 2199
            SQL));
        self::assertSame(3202, Aktivita::zId(self::ID_AKTIVITY_SE_SELHANIM)->idHlavniLokace());
    }
}
