<?php

declare(strict_types=1);

namespace Gamecon\Tests\ArchivPrednasek;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\ArchivPrednasek\PrednaskaNaWebu;
use Gamecon\ArchivPrednasek\PrednaskyNaWebu;
use Gamecon\ArchivPrednasek\StitekNaWebu;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * What the public archive shows: hidden lectures stay out, annotation and tags only from a paired activity,
 * links to a year and a card only where the line page shows them.
 */
class PrednaskyNaWebuTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    private static function rok(): int
    {
        return ROCNIK - 2;
    }

    /**
     * @return list<string>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $rok = self::rok();
        $letos = ROCNIK;

        return [
            // the migration filled the archive of the test database
            <<<'SQL'
DELETE FROM archiv_prednasek
SQL,
            <<<'SQL'
DELETE FROM archiv_prednasek_prednasejici
SQL,
            <<<'SQL'
DELETE FROM archiv_prednasek_klicove_slovo
SQL,
            <<<SQL
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, popis_kratky, cena, kapacita, kapacita_f, kapacita_m)
VALUES
    (990201, 'Cesta tam', 'cesta-tam', 3, {$rok}, 7, '**Dlouze** o cestě', 'Krátce o cestě', 0, 40, 0, 0),
    (990202, 'Nová v programu', 'nova-v-programu', 3, {$rok}, 1, 'Popis', 'Krátce', 0, 40, 0, 0)
SQL,
            <<<'SQL'
INSERT INTO sjednocene_tagy(id, id_kategorie_tagu, nazev, poznamka)
VALUES
    (990201, (SELECT id FROM kategorie_sjednocenych_tagu WHERE nazev = 'typ Přednášky'), 'Archivní test', ''),
    (990202, (SELECT id FROM kategorie_sjednocenych_tagu WHERE nazev = 'omezení Věk'), 'Věk: 99+', ''),
    (990203, (SELECT id FROM kategorie_sjednocenych_tagu WHERE nazev = 'omezení'), 'Výška: 299+', ''),
    (990204, (SELECT id FROM kategorie_sjednocenych_tagu WHERE nazev = 'typ Přednášky'), 'Jen v programu', '')
SQL,
            <<<'SQL'
INSERT INTO akce_sjednocene_tagy(id_akce, id_tagu)
VALUES (990201, 990201), (990201, 990202), (990201, 990203), (990201, 990204)
SQL,
            <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (990201, 'lektor-archiv', 'Lektor', 'Archivní', 'lektor-archiv@example.invalid'),
    (990202, 'skupina-archiv', '', 'Skupina', 'skupina-archiv@example.invalid')
SQL,
            <<<SQL
INSERT INTO archiv_prednasek(id_prednasky, nazev, rok, youtube_id, skryta, id_akce)
VALUES
    (990201, 'Cesta tam', {$rok}, 'QaGjJIZJ4to', 0, 990201),
    (990202, 'Hrad', {$rok}, NULL, 0, NULL),
    (990203, 'Chata', {$rok}, NULL, 0, NULL),
    (990204, 'Čaj o páté', {$rok}, NULL, 0, NULL),
    (990205, 'Skrytá', {$rok}, NULL, 1, NULL),
    (990206, 'Nová v programu', {$rok}, NULL, 0, 990202),
    (990207, 'Stará', 2008, NULL, 0, NULL),
    (990208, 'Letošní', {$letos}, NULL, 0, NULL)
SQL,
            <<<'SQL'
INSERT INTO archiv_prednasek_prednasejici(id_prednasejiciho, jmeno, id_uzivatele)
VALUES (990201, 'Lektor', 990201), (990202, 'Skupina s účtem', 990202), (990203, 'Bez účtu', NULL)
SQL,
            <<<'SQL'
INSERT INTO archiv_prednasek_prednaska_prednasejici(id_prednasky, id_prednasejiciho, poradi)
VALUES (990201, 990203, 0), (990201, 990201, 1), (990201, 990202, 2), (990205, 990201, 0)
SQL,
            <<<'SQL'
INSERT INTO archiv_prednasek_klicove_slovo(id_klicoveho_slova, nazev)
VALUES (990201, 'archivní TEST'), (990202, 'Teorie'), (990203, 'Larp')
SQL,
            <<<'SQL'
INSERT INTO archiv_prednasek_prednaska_klicove_slovo(id_prednasky, id_klicoveho_slova)
VALUES (990201, 990201), (990201, 990202), (990202, 990203)
SQL,
        ];
    }

    /**
     * @return array<string, PrednaskaNaWebu>
     */
    private static function prednaskyPodleNazvu(): array
    {
        $prednasky = (new PrednaskyNaWebu(ROCNIK, TypAktivity::zId(TypAktivity::PREDNASKA)))->getPrednasky();
        $podleNazvu = [];
        foreach ($prednasky as $prednaska) {
            $podleNazvu[$prednaska->nazev] = $prednaska;
        }

        return $podleNazvu;
    }

    public function testSkrytaNeniARadiSeOdNejnovejsihoRokuCeskouAbecedou(): void
    {
        $nazvy = array_keys(self::prednaskyPodleNazvu());

        self::assertNotContains('Skrytá', $nazvy);
        // C, Č, H, Ch
        self::assertSame(['Letošní', 'Cesta tam', 'Čaj o páté', 'Hrad', 'Chata', 'Nová v programu', 'Stará'], $nazvy);
    }

    public function testSparovanaMaAnotaciAStitkyZProgramuBezOmezeni(): void
    {
        $prednaska = self::prednaskyPodleNazvu()['Cesta tam'];

        self::assertSame('Krátce o cestě', $prednaska->kratkaAnotace);
        self::assertStringContainsString('<strong>Dlouze</strong> o cestě', (string) $prednaska->dlouhaAnotace);
        // the keyword and the tag of the same name are one, written like the keyword; age and height stay out
        self::assertSame(
            [
                ['archivni-test', 'archivní TEST', StitekNaWebu::SKUPINA_KLICOVA_SLOVA],
                ['teorie', 'Teorie', StitekNaWebu::SKUPINA_KLICOVA_SLOVA],
                ['jen-v-programu', 'Jen v programu', 'typ Přednášky'],
            ],
            array_map(static fn (StitekNaWebu $stitek) => [$stitek->klic, $stitek->nazev, $stitek->skupina], $prednaska->stitky),
        );
        self::assertSame('QaGjJIZJ4to', $prednaska->youtubeId);
    }

    public function testNesparovanaJeBezAnotaceJenSKlicovymiSlovy(): void
    {
        $prednaska = self::prednaskyPodleNazvu()['Hrad'];

        self::assertNull($prednaska->kratkaAnotace);
        self::assertNull($prednaska->dlouhaAnotace);
        self::assertNull($prednaska->urlProgramu);
        self::assertNull($prednaska->youtubeId);
        self::assertSame(['Larp'], array_map(static fn (StitekNaWebu $stitek) => $stitek->nazev, $prednaska->stitky));
    }

    public function testPrednasejiciVPoradiSMedailonkemJenUUctuSeJmenem(): void
    {
        $prednasejici = self::prednaskyPodleNazvu()['Cesta tam']->prednasejici;

        self::assertSame(['Bez účtu', 'Lektor', 'Skupina s účtem'], array_map(static fn ($osoba) => $osoba->jmeno, $prednasejici));
        self::assertSame([null, '990201', null], array_map(static fn ($osoba) => $osoba->urlMedailonku, $prednasejici));
    }

    public function testRokOdkazujeNaLiniiJenKdyzHoPrepinacNabizi(): void
    {
        $prednasky = self::prednaskyPodleNazvu();

        self::assertSame('prednasky?rocnik=' . self::rok(), $prednasky['Cesta tam']->urlRoku);
        self::assertSame('prednasky', $prednasky['Letošní']->urlRoku);
        self::assertNull($prednasky['Stará']->urlRoku);
    }

    public function testKartaVProgramuJenUViditelneAktivity(): void
    {
        $prednasky = self::prednaskyPodleNazvu();

        self::assertSame('prednasky?rocnik=' . self::rok() . '#cesta-tam', $prednasky['Cesta tam']->urlProgramu);
        // an activity in preparation is not on the line page
        self::assertNull($prednasky['Nová v programu']->urlProgramu);
    }
}
