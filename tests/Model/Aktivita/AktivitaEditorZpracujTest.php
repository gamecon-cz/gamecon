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

    public function testEditorZpracujNevyžadujePotvrzeníProPrázdnouInstanci(): void
    {
        // 7102 je prázdná instance (žádní přihlášení), byť sourozenecká 7103 hráče má.
        // Upozornění se týká jen lidí na této konkrétní aktivitě → změna projde bez potvrzení.
        $_POST[Aktivita::POST_KLIC] = [
            Sql::ID_AKCE    => 7102,
            Sql::NAZEV_AKCE => 'Instance bez přihlášených',
            // URL je „main-only" pole sdílené přes hlavní aktivitu rodiny instancí.
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

        self::assertNotNull($upravenaAktivita, 'Prázdná instance se má uložit i bez potvrzení.');
        self::assertSame(
            ROCNIK . '-07-17 10:00:00',
            dbOneCol('SELECT zacatek FROM akce_seznam WHERE id_akce = 7102'),
            'Prázdná instance se měla uložit se změněným dnem.',
        );

        self::assertStringNotContainsString(
            'Tato aktivita už má přihlášené hráče',
            \Chyba::vyzvedniHtml(),
        );
    }

    public function testEditorZpracujVyžadujePotvrzeníProInstanciSPřihlášenými(): void
    {
        $_POST[Aktivita::POST_KLIC] = [
            Sql::ID_AKCE    => 7103,
            Sql::NAZEV_AKCE => 'Instance s přihlášenými',
            Sql::URL_AKCE   => 'instance-s-prihlasenymi-editor-test',
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
            dbOneCol('SELECT zacatek FROM akce_seznam WHERE id_akce = 7103'),
            'Aktivita s přihlášenými se bez potvrzení nesmí změnit.',
        );

        $varovaniHtml = \Chyba::vyzvedniHtml();
        self::assertStringContainsString('Tato změna se dotkne přihlášených hráčů (1).', $varovaniHtml);
        self::assertStringContainsString('změnit den', $varovaniHtml);
    }

    public function testEditorZpracujVyžadujePotvrzeníProZměnuCenyPrázdnéInstanceSPřihlášenýmiVRodině(): void
    {
        // 7102 je prázdná instance, ale cena se propaguje na celou rodinu (i na 7103 s hráčem) →
        // změna ceny musí vyžadovat potvrzení a bez něj se nesmí uložit.
        $_POST[Aktivita::POST_KLIC] = [
            Sql::ID_AKCE    => 7102,
            Sql::NAZEV_AKCE => 'Instance bez přihlášených',
            Sql::URL_AKCE   => 'hlavni-aktivita-editor-test',
            Sql::PATRI_POD  => 9101,
            Sql::POPIS      => '',
            'den'           => ROCNIK . '-07-16',
            Sql::ZACATEK    => '10',
            Sql::KONEC      => '12',
            Sql::CENA       => 150, // změna ze 100 na 150 – propaguje se na celou rodinu
            Sql::TEAMOVA    => 0,
            Sql::KAPACITA   => 5,
            Sql::KAPACITA_F => 0,
            Sql::KAPACITA_M => 0,
        ];
        $_POST[Aktivita::POTVRDIT_ZMENU_UDAJU_S_PRIHLASENYMI_KLIC] = '0';

        $upravenaAktivita = Aktivita::editorZpracuj(null);

        self::assertNull($upravenaAktivita, 'Bez potvrzení se změna ceny nemá uložit.');
        self::assertSame(
            '100',
            dbOneCol('SELECT cena FROM akce_seznam WHERE id_akce = 7103'),
            'Cena se na instanci s přihlášenými nesmí bez potvrzení změnit.',
        );

        $varovaniHtml = \Chyba::vyzvedniHtml();
        self::assertStringContainsString('Tato změna se dotkne přihlášených hráčů (1).', $varovaniHtml);
        self::assertStringContainsString('změnit cenu', $varovaniHtml);
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return true;
    }
}
