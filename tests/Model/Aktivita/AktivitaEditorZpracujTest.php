<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Tests\Db\AbstractTestDb;

class AktivitaEditorZpracujTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $rok = (string) ROCNIK;

        return [
            <<<SQL
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (801, 'test-hrac', 'Test', 'Hrac', 'test-hrac@example.invalid')
SQL,
            <<<SQL
INSERT INTO akce_seznam(
    id_akce, nazev_akce, url_akce, typ, rok, stav, patri_pod, zacatek, konec, cena, teamova, team_min, team_max, kapacita, kapacita_f, kapacita_m
)
VALUES
    (7101, 'Hlavní aktivita', 'hlavni-aktivita-editor-test', 1, {$rok}, 2, NULL, '{$rok}-07-16 10:00:00', '{$rok}-07-16 12:00:00', 100, 0, NULL, NULL, 5, 0, 0)
SQL,
            'INSERT INTO akce_instance(id_instance, id_hlavni_akce) VALUES (9101, 7101)',
            'UPDATE akce_seznam SET patri_pod = 9101 WHERE id_akce = 7101',
            <<<SQL
INSERT INTO akce_seznam(
    id_akce, nazev_akce, url_akce, typ, rok, stav, patri_pod, zacatek, konec, cena, teamova, team_min, team_max, kapacita, kapacita_f, kapacita_m
)
VALUES
    (7102, 'Instance bez přihlášených', 'instance-bez-prihlasenych-editor-test', 1, {$rok}, 2, 9101, '{$rok}-07-16 10:00:00', '{$rok}-07-16 12:00:00', 100, 0, NULL, NULL, 5, 0, 0),
    (7103, 'Instance s přihlášenými', 'instance-s-prihlasenymi-editor-test', 1, {$rok}, 2, 9101, '{$rok}-07-16 10:00:00', '{$rok}-07-16 12:00:00', 100, 0, NULL, NULL, 5, 0, 0)
SQL,
            sprintf(
                'INSERT INTO akce_prihlaseni SET id_akce = 7103, id_uzivatele = 801, id_stavu_prihlaseni = %d',
                StavPrihlaseni::PRIHLASEN,
            ),
            sprintf(
                "INSERT INTO akce_prihlaseni_log(id_akce, rocnik, id_uzivatele, zdroj_zmeny) VALUES (7103, %d, 801, 'test')",
                ROCNIK,
            ),
        ];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_FILES = [];
        $_COOKIE = [];

        parent::tearDown();
    }

    public function testEditorZpracujVyžadujePotvrzeníIProPřihlášenéNaJinéInstanci(): void
    {
        $_POST[Aktivita::POST_KLIC] = [
            Sql::ID_AKCE    => 7102,
            Sql::NAZEV_AKCE => 'Instance bez přihlášených',
            Sql::URL_AKCE   => 'instance-bez-prihlasenych-editor-test',
            Sql::PATRI_POD  => 9101,
            Sql::POPIS      => '',
            'den'           => ROCNIK . '-07-17',
            Sql::ZACATEK    => '10',
            Sql::KONEC      => '12',
            Sql::CENA       => 100,
            Sql::TEAMOVA    => 0,
            Sql::KAPACITA   => 5,
            Sql::KAPACITA_F => 0,
            Sql::KAPACITA_M => 0,
        ];
        $_POST[Aktivita::POTVRDIT_ZMENU_UDAJU_S_PRIHLASENYMI_KLIC] = '0';

        $upravenaAktivita = Aktivita::editorZpracuj(null);

        self::assertNull($upravenaAktivita, 'Bez potvrzení se změna nemá uložit.');
        self::assertSame(
            ROCNIK . '-07-16 10:00:00',
            dbOneCol('SELECT zacatek FROM akce_seznam WHERE id_akce = 7102'),
            'Aktivita se bez potvrzení nesmí změnit.',
        );

        $varovaniHtml = \Chyba::vyzvedniHtml();
        self::assertStringContainsString('Tato aktivita už má přihlášené hráče (1).', $varovaniHtml);
        self::assertStringContainsString('změnit den', $varovaniHtml);
    }

    public function testEditorZpracujVyžadujePotvrzeníProHlavníAktivituSPřihlášenýmiNaInstanci(): void
    {
        $_POST[Aktivita::POST_KLIC] = [
            Sql::ID_AKCE    => 7101,
            Sql::NAZEV_AKCE => 'Hlavní aktivita',
            Sql::URL_AKCE   => 'hlavni-aktivita-editor-test',
            Sql::PATRI_POD  => 9101,
            Sql::POPIS      => '',
            'den'           => ROCNIK . '-07-17',
            Sql::ZACATEK    => '10',
            Sql::KONEC      => '12',
            Sql::CENA       => 100,
            Sql::TEAMOVA    => 0,
            Sql::KAPACITA   => 5,
            Sql::KAPACITA_F => 0,
            Sql::KAPACITA_M => 0,
        ];
        $_POST[Aktivita::POTVRDIT_ZMENU_UDAJU_S_PRIHLASENYMI_KLIC] = '0';

        $upravenaAktivita = Aktivita::editorZpracuj(null);

        self::assertNull($upravenaAktivita, 'Bez potvrzení se změna nemá uložit.');
        self::assertSame(
            ROCNIK . '-07-16 10:00:00',
            dbOneCol('SELECT zacatek FROM akce_seznam WHERE id_akce = 7101'),
            'Hlavní aktivita se bez potvrzení nesmí změnit.',
        );

        $varovaniHtml = \Chyba::vyzvedniHtml();
        self::assertStringContainsString('Tato aktivita už má přihlášené hráče (1).', $varovaniHtml);
        self::assertStringContainsString('změnit den', $varovaniHtml);
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return true;
    }
}
