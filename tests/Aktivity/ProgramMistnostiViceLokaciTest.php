<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Program;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Test pro bug #877 — aktivita ve více místnostech blokuje zobrazení dalších aktivit v programu po místnostech
 */
class ProgramMistnostiViceLokaciTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    protected static array $initQueries = [
        <<<SQL
INSERT INTO lokace(id_lokace, nazev, dvere, poznamka, poradi, rok)
VALUES
    (3001, 'Místnost A', '', '', 1, 0),
    (3002, 'Místnost B', '', '', 2, 0),
    (3003, 'Místnost C', '', '', 3, 0)
SQL,
        <<<SQL
INSERT INTO akce_seznam(id_akce, nazev_akce, typ, rok, stav, zacatek, konec, kapacita, kapacita_f, kapacita_m, teamova, popis, popis_kratky, vybaveni, id_hlavni_lokace)
VALUES
    (4001, 'Aktivita X vícemístnostní', 1, 2026, 2, '2026-07-16 10:00:00', '2026-07-16 12:00:00', 10, 0, 0, 0, '', '', '', 3001),
    (4002, 'Aktivita Y jednomístnostní', 1, 2026, 2, '2026-07-16 10:00:00', '2026-07-16 12:00:00', 10, 0, 0, 0, '', '', '', 3003)
SQL,
        <<<SQL
INSERT INTO akce_lokace(id_akce, id_lokace)
VALUES
    (4001, 3001),
    (4001, 3002),
    (4002, 3003)
SQL,
    ];

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [];
    }

    /**
     * @test
     * Bug #877: Aktivita ve více místnostech způsobuje, že další aktivity v programu po místnostech zmizí
     */
    public function testAktivitaZaViceMistnostniSeZobrazuje()
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();

        $program = new Program(
            systemoveNastaveni: $systemoveNastaveni,
            nastaveni: [
                Program::SKUPINY => Program::SKUPINY_MISTNOSTI,
                Program::PRAZDNE => true,
                Program::INTERNI => true,
            ],
        );

        ob_start();
        $program->tisk();
        $output = ob_get_clean();

        self::assertStringContainsString(
            'Aktivita X vícemístnostní',
            $output,
            'Aktivita X ve více místnostech by se měla zobrazit v programu',
        );

        self::assertStringContainsString(
            'Aktivita Y jednomístnostní',
            $output,
            'Aktivita Y v jiné místnosti by se měla zobrazit v programu, i když aktivita X je ve více místnostech (bug #877)',
        );
    }
}
