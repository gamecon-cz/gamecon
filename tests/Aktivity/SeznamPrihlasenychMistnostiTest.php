<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as AktivitaSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Místnosti aktivity v Seznamu přihlášených (admin Aktivity → Seznam přihlášených) jsou ve stejném pořadí jako
 * v Aktivita::seznamLokaci(): hlavní místnost první, ostatní podle pořadí místností, @see \Gamecon\Aktivita\HlavniLokace
 */
class SeznamPrihlasenychMistnostiTest extends AbstractTestDb
{
    private const string MODULY_AKTIVIT = __DIR__ . '/../../admin/scripts/modules/aktivity';

    protected static bool $disableStrictTransTables = true;

    protected static array $initQueries = [
        // ID jsou schválně v opačném pořadí než `poradi`, ať je vidět, že se neřadí podle ID
        <<<'SQL'
        INSERT INTO lokace(id_lokace, nazev, dvere, poznamka, poradi, rok)
        VALUES
            (3203, 'Seznam RPG 1', '', '', 1, 0),
            (3202, 'Seznam RPG 2', 'dveře 2', '', 2, 0),
            (3201, 'Seznam venku', '', '', 50, 0)
        SQL,
    ];

    public function testZvolenaHlavniMistnostJePrvniPredPoradim(): void
    {
        $idAktivity = $this->vytvorAktivituVMistnostech([3203, 3202, 3201], 3201);

        self::assertStringContainsString(
            ' - Seznam venku; Seznam RPG 1; Seznam RPG 2, dveře 2 - ',
            $this->vypisAktivityVSeznamuPrihlasenych($idAktivity),
        );
    }

    public function testAutomatickaHlavniMistnostJdePodlePoradi(): void
    {
        $idAktivity = $this->vytvorAktivituVMistnostech([3201, 3202, 3203], null);

        self::assertStringContainsString(
            ' - Seznam RPG 1; Seznam RPG 2, dveře 2; Seznam venku - ',
            $this->vypisAktivityVSeznamuPrihlasenych($idAktivity),
        );
    }

    /**
     * @param array<int> $idckaLokaci
     */
    private function vytvorAktivituVMistnostech(array $idckaLokaci, ?int $idZvoleneHlavniLokace): int
    {
        dbInsert(AktivitaSql::AKCE_SEZNAM_TABULKA, [
            AktivitaSql::NAZEV_AKCE => 'Larp ve více místnostech ' . uniqid('', true),
            AktivitaSql::TYP        => TypAktivity::LARP,
            AktivitaSql::ROK        => ROCNIK,
            AktivitaSql::STAV       => StavAktivity::AKTIVOVANA,
            AktivitaSql::ZACATEK    => ROCNIK . '-07-16 10:00:00',
            AktivitaSql::KONEC      => ROCNIK . '-07-16 13:00:00',
            AktivitaSql::KAPACITA   => 5,
            AktivitaSql::KAPACITA_M => 0,
            AktivitaSql::KAPACITA_F => 0,
            AktivitaSql::CENA       => 0,
            AktivitaSql::TEAMOVA    => 0,
        ]);
        $idAktivity = (int) dbInsertId();
        Aktivita::zId($idAktivity)->nastavLokacePodleIds($idckaLokaci, $idZvoleneHlavniLokace);

        return $idAktivity;
    }

    /**
     * Seznam přihlášených linie larpů, jen řádek s časem, orgy a místnostmi pod nadpisem aktivity.
     */
    private function vypisAktivityVSeznamuPrihlasenych(int $idAktivity): string
    {
        $spustSeznam = static function (): void {
            require self::MODULY_AKTIVIT . '/prihlaseni.php';
        };
        $puvodniGet = $_GET;
        $_GET = [
            'typ' => (string) TypAktivity::LARP,
        ];
        ob_start();
        try {
            $spustSeznam();
        } finally {
            $vystup = (string) ob_get_clean();
            $_GET = $puvodniGet;
        }

        self::assertSame(
            1,
            preg_match('~<h2 id="aktivita-' . $idAktivity . '">[^\n]*\n([^\n]*)~', $vystup, $shoda),
            "Seznam přihlášených aktivitu {$idAktivity} nevypsal",
        );

        return $shoda[1];
    }
}
