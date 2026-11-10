<?php

declare(strict_types=1);

namespace Gamecon\Tests\ArchivPrednasek;

use Gamecon\ArchivPrednasek\Import\ImportArcitabulky;
use Gamecon\ArchivPrednasek\Import\RadekArcitabulky;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Pairing by fixed ids, checked against year and title, and user links checked against the login.
 */
class ImportArcitabulkyTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    /**
     * @return list<string>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            <<<'SQL'
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, popis, cena, kapacita, kapacita_f, kapacita_m)
VALUES
    (990101, 'Gamemaster - úskalí a svízel', 'gamemaster-archiv', 3, 2022, 7, '', 0, 40, 0, 0),
    (990102, 'Jiná přednáška', 'jina-prednaska-archiv', 3, 2023, 7, '', 0, 40, 0, 0),
    (990103, 'Přejmenovaná v programu', 'prejmenovana-archiv', 3, 2024, 7, '', 0, 40, 0, 0),
    (990104, 'Organizace turnajů', 'organizace-turnaju-archiv', 3, 2019, 3, '', 0, 40, 0, 0)
SQL,
            <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (990101, 'Kybersova-test', 'Ondřej', 'Test', 'kybersova-test@example.invalid'),
    (990102, 'jiny-login-test', 'Jiný', 'Test', 'jiny-test@example.invalid')
SQL,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        // the migration already filled the archive of the test database, the test starts from an empty one
        dbQuery('DELETE FROM archiv_prednasek');
        dbQuery('DELETE FROM archiv_prednasek_prednasejici');
        dbQuery('DELETE FROM archiv_prednasek_klicove_slovo');
    }

    /**
     * @param list<string> $prednasejici
     * @param list<string> $klicovaSlova
     */
    private static function radek(string $nazev, int $rok, array $prednasejici = [], array $klicovaSlova = [], ?string $youtubeId = null): RadekArcitabulky
    {
        return new RadekArcitabulky(2, $nazev, $nazev, $rok, $prednasejici, $klicovaSlova, $youtubeId);
    }

    private static function import(): ImportArcitabulky
    {
        return new ImportArcitabulky(
            uzivatele: [
                'Kybersova'    => [990101, 'Kybersova-test'],
                'Přejmenovaný' => [990102, 'puvodni-login-test'],
                'Smazaný'      => [990199, 'smazany-test'],
            ],
            parovani: [
                // the title in the program after unification: a spaced hyphen is the same as a dash
                ['Gamemaster – úskalí', 2022, 990101, 'Gamemaster – úskalí a svízel'],
                ['Špatný rok', 2024, 990102, 'Jiná přednáška'],
                ['Jiný název', 2024, 990103, 'Původní název v programu'],
                ['Chybějící aktivita', 2024, 990199, 'Neexistuje'],
                ['Neodpovídá řádku', 2024, 990102, 'Jiná přednáška'],
            ],
            zProgramu: [
                ['Organizace turnajů', 2019, 990104, 'Organizace turnajů', true, ['Kybersova']],
                ['Stará přednáška', 2017, 990102, 'Jiná přednáška', false, ['Kybersova', 'Neznámý']],
            ],
        );
    }

    /**
     * @return array<string, int|null>
     */
    private static function sparovani(): array
    {
        $vysledek = [];
        foreach (dbFetchAll('SELECT nazev, id_akce FROM archiv_prednasek') as $radek) {
            $vysledek[$radek['nazev']] = isset($radek['id_akce'])
                ? (int) $radek['id_akce']
                : null;
        }

        return $vysledek;
    }

    public function testParujeJenAktivituSeStejnymRokemANazvem(): void
    {
        $zpravy = self::import()->importuj([
            self::radek('Gamemaster – úskalí', 2022),
            self::radek('Špatný rok', 2024),
            self::radek('Jiný název', 2024),
            self::radek('Chybějící aktivita', 2024),
            self::radek('Bez párování', 2015),
        ]);

        self::assertSame([
            'Gamemaster – úskalí' => 990101,
            'Špatný rok'          => null,
            'Jiný název'          => null,
            'Chybějící aktivita'  => null,
            'Bez párování'        => null,
            'Organizace turnajů'  => 990104,
            'Stará přednáška'     => null,
        ], self::sparovani());
        self::assertContains('Přednáška „Špatný rok“ (2024) zůstala nespárovaná: aktivita 990102 je z roku 2023', $zpravy);
        self::assertContains('Přednáška „Jiný název“ (2024) zůstala nespárovaná: aktivita 990103 se jmenuje „Přejmenovaná v programu“, čekal jsem „Původní název v programu“', $zpravy);
        self::assertContains('Přednáška „Chybějící aktivita“ (2024) zůstala nespárovaná: aktivita 990199 v databázi není', $zpravy);
        self::assertContains('Párování „2024 neodpovídá řádku“ s aktivitou 990102 neodpovídá žádné přednášce z tabulky', $zpravy);
    }

    public function testNapojiPrednasejicihoJenSeStejnymLoginem(): void
    {
        $zpravy = self::import()->importuj([
            self::radek('Gamemaster – úskalí', 2022, ['Kybersova', 'Přejmenovaný', 'Smazaný', 'Bez uživatele']),
            self::radek('Druhá přednáška', 2023, ['Kybersova']),
        ]);

        $prednasejici = dbFetchAll('SELECT jmeno, id_uzivatele FROM archiv_prednasek_prednasejici ORDER BY jmeno');
        self::assertSame([
            [
                'jmeno'        => 'Bez uživatele',
                'id_uzivatele' => null,
            ],
            [
                'jmeno'        => 'Kybersova',
                'id_uzivatele' => '990101',
            ],
            [
                'jmeno'        => 'Neznámý',
                'id_uzivatele' => null,
            ],
            [
                'jmeno'        => 'Přejmenovaný',
                'id_uzivatele' => null,
            ],
            [
                'jmeno'        => 'Smazaný',
                'id_uzivatele' => null,
            ],
        ], $prednasejici, 'Jeden přednášející na jméno, i když přednáší víckrát');
        self::assertContains('Přednášející „Přejmenovaný“ zůstal bez uživatele: uživatel 990102 má login „jiny-login-test“, čekal jsem „puvodni-login-test“', $zpravy);
        self::assertContains('Přednášející „Smazaný“ zůstal bez uživatele: uživatel 990199 v databázi není', $zpravy);
    }

    public function testUloziPoradiPrednasejicichKlicovaSlovaAVideo(): void
    {
        self::import()->importuj([
            self::radek('Gamemaster – úskalí', 2022, ['Přejmenovaný', 'Kybersova'], ['RPG', 'Talk'], 'QaGjJIZJ4to'),
            self::radek('Druhá přednáška', 2023, [], ['rpg']),
        ]);

        self::assertSame(['Přejmenovaný', 'Kybersova'], dbOneArray(<<<'SQL'
            SELECT archiv_prednasek_prednasejici.jmeno
            FROM archiv_prednasek_prednaska_prednasejici
            JOIN archiv_prednasek USING (id_prednasky)
            JOIN archiv_prednasek_prednasejici USING (id_prednasejiciho)
            WHERE archiv_prednasek.nazev = 'Gamemaster – úskalí'
            ORDER BY archiv_prednasek_prednaska_prednasejici.poradi
            SQL));
        self::assertSame('QaGjJIZJ4to', dbOneCol("SELECT youtube_id FROM archiv_prednasek WHERE nazev = 'Gamemaster – úskalí'"));
        self::assertNull(dbOneCol("SELECT youtube_id FROM archiv_prednasek WHERE nazev = 'Druhá přednáška'"));
        // a keyword differing only in case is the same keyword
        self::assertSame(['RPG', 'Talk'], dbOneArray('SELECT nazev FROM archiv_prednasek_klicove_slovo ORDER BY nazev'));
        self::assertSame(2, (int) dbOneCol(<<<'SQL'
            SELECT COUNT(*)
            FROM archiv_prednasek_prednaska_klicove_slovo
            JOIN archiv_prednasek_klicove_slovo USING (id_klicoveho_slova)
            WHERE archiv_prednasek_klicove_slovo.nazev = 'RPG'
            SQL));
    }
}
