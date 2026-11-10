<?php

declare(strict_types=1);

namespace Gamecon\Tests\ArchivPrednasek;

use Gamecon\ArchivPrednasek\Import\Arcitabulka;
use Gamecon\ArchivPrednasek\Import\DataArcitabulky;
use Gamecon\ArchivPrednasek\Import\RadekArcitabulky;
use PHPUnit\Framework\TestCase;

/**
 * Unification of the real spreadsheet, as the migration imports it.
 */
class ArcitabulkaTest extends TestCase
{
    private const string TABULKA = __DIR__ . '/../../migrace/pomocne/archiv-prednasek/arcitabulka-prednasek.tsv';

    /**
     * @var list<RadekArcitabulky>|null
     */
    private static ?array $radky = null;

    /**
     * @return list<RadekArcitabulky>
     */
    private static function radky(): array
    {
        return self::$radky ??= (new Arcitabulka())->nactiSoubor(self::TABULKA);
    }

    private static function radek(string $nazev, int $rok): RadekArcitabulky
    {
        foreach (self::radky() as $radek) {
            if ($radek->nazev === $nazev && $radek->rok === $rok) {
                return $radek;
            }
        }
        self::fail("V tabulce není „{$nazev}“ ({$rok})");
    }

    public function testNactePrednaskyBezHlavicky(): void
    {
        self::assertCount(192, self::radky());
        self::assertSame(2, self::radky()[0]->cisloRadku);
        self::assertSame('12 zápletek podle motivace postav', self::radky()[0]->nazev);
    }

    public function testKlicovaSlovaJsouSjednocena(): void
    {
        $slova = array_unique(array_merge(...array_map(static fn (RadekArcitabulky $radek) => $radek->klicovaSlova, self::radky())));

        foreach (['Horror', 'Seting', 'Larpy', 'Tvorby', 'WG', 'Zapovězené Země', 'Lone Wolf RPG', 'Call of Cthullu 7e', 'Diskuze', 'Panelovka', 'Dračák', 'Svět', 'JRRT', ''] as $puvodni) {
            self::assertNotContains($puvodni, $slova, "Klíčové slovo „{$puvodni}“ mělo být sjednocené");
        }
        foreach (['Horor', 'Setting', 'Larp', 'Tvorba', 'Wargaming', 'Zapovězené země', 'Lone Wolf', 'Call of Cthulhu 7e', 'Debata', 'Panelová diskuse', 'Dračí doupě', 'Pán prstenů'] as $sjednocene) {
            self::assertContains($sjednocene, $slova);
        }
        // the same word in another case is one keyword
        $malymi = array_map('mb_strtolower', $slova);
        self::assertSame(count($malymi), count(array_unique($malymi)));
    }

    public function testPrazdnaKlicovaSlovaZaCarkouZmizi(): void
    {
        self::assertSame(['RPG', 'Talk', 'Boj'], self::radek('Dobrý souboj v DrD?', 2011)->klicovaSlova);
        self::assertSame(['RPG', 'Talk'], self::radek('Detektivky a konspirace v RPG', 2010)->klicovaSlova);
    }

    public function testVariantyJmenPrednasejicichJsouJednoJmeno(): void
    {
        self::assertSame(['Guff', 'Jerry!'], self::radek('Mistrovství DrD – Rozuzlení', 2017)->prednasejici);
        self::assertSame(['Jerry!'], self::radek('Dračí doupě plus 2.0', 2013)->prednasejici);
        self::assertSame(['mackan', 'Guff', 'Aikin'], self::radek('Mistrovství DrD – Hodnocení družin a postupová kritéria', 2022)->prednasejici);
        self::assertSame(['Jezevec_CoM'], self::radek('Výběr ze zahraniční larpů', 2008)->prednasejici);
        self::assertSame(['Ecthelion1'], self::radek('Stories of the Empire', 2009)->prednasejici);
        self::assertSame(['RomiK', 'Bobaque'], self::radek('Drakkar – internetový časopis', 2009)->prednasejici);
        self::assertSame(['Toren'], self::radek('Filmové fantasy propadáky a kde je problém?', 2019)->prednasejici);
        // two accounts, two speakers
        self::assertContains('Marky', self::radek('Requel – beseda s tvůrci fanfilmu', 2014)->prednasejici);
        self::assertSame(['MarkyParky'], self::radek('DrD2 Variace – Hra o zdroje pokaždé jinak', 2014)->prednasejici);
    }

    public function testSkupinaPrednasiSeSvymiCleny(): void
    {
        foreach ([['Kampaně vs One-shoty', 2024], ['Majstri svetov Live: Prečo hrať naratívne RPG', 2023], ['One-Shot: Od nápadu po realizaci', 2025]] as [$nazev, $rok]) {
            self::assertSame(['Majstri svetov', 'Babcom', 'Thanatophagous'], self::radek($nazev, $rok)->prednasejici);
        }
        self::assertArrayNotHasKey('Majstri svetov', DataArcitabulky::UZIVATELE);
        self::assertSame([4843, 'Babcom'], DataArcitabulky::UZIVATELE['Babcom']);
        self::assertSame([5420, 'Thanatophagous'], DataArcitabulky::UZIVATELE['Thanatophagous']);
    }

    public function testNeznamyPrednasejiciNeniPrednasejici(): void
    {
        self::assertSame([], self::radek('Asterion – tvorba a budoucnost', 2016)->prednasejici);
        self::assertSame([], self::radek('Dračí Kutloch', 2012)->prednasejici);
    }

    public function testOdkazNaYoutubeJeJenId(): void
    {
        $sVideem = array_filter(self::radky(), static fn (RadekArcitabulky $radek) => $radek->youtubeId !== null);
        self::assertCount(40, $sVideem);
        foreach ($sVideem as $radek) {
            self::assertMatchesRegularExpression('~^[A-Za-z0-9_-]{11}$~', $radek->youtubeId);
        }
        self::assertSame('-7-8Ax6sDuQ', self::radek('Asterion: Krajiny za obzorem 2', 2022)->youtubeId);
        // a space after the link in the spreadsheet
        self::assertSame('qrGYVOsfodk', self::radek('Jak jsem potkal / dělal Dračí doupě', 2025)->youtubeId);
    }

    public function testBezZaznamuNemaVideo(): void
    {
        self::assertNull(self::radek('Láska, sex a něžnosti v RPG', 2025)->youtubeId); // záznam dodáme
        self::assertNull(self::radek('Historie deskových her', 2016)->youtubeId); // záznam není
    }

    public function testNazvyMajiJednuPomlckuBezMezerNaKoncichAOpraveneTypy(): void
    {
        self::radek('Mistrovství DrD – Hodnocení družin, postupová kritéria a FAQ', 2023); // "-" in the spreadsheet
        self::radek('Mistrovství DrD – Hodnocení družin, postupová kritéria a FAQ', 2025); // "–" in the spreadsheet
        self::radek('Deskové hry v Československu', 2024); // a space at the end in the spreadsheet
        self::radek('Hexcrawl: jde vůbec udělat dobré dobrodružství pod širým nebem?', 2023);
        self::radek('Úskalí komediálního larpu', 2021);
        self::radek('Jak se nezklamat z tvorby', 2021);
        self::assertSame('Hecrawl: jde vůbec udělat dobré dobrodružství pod širým nebem?', self::radek('Hexcrawl: jde vůbec udělat dobré dobrodružství pod širým nebem?', 2023)->puvodniNazev);
    }

    public function testStejnyNazevVRuznychLetechJsouDvePrednasky(): void
    {
        self::assertSame(['Thaddeus'], self::radek('Historie deskových her', 2016)->prednasejici);
        self::assertSame(['Shadowulf'], self::radek('Historie deskových her', 2023)->prednasejici);
        self::radek('Umíte mluvit?', 2012);
        self::radek('Umíte mluvit?', 2014);
    }

    public function testPrednaskuUrcujeNazevARokBezDuplicit(): void
    {
        $klice = array_map(static fn (RadekArcitabulky $radek) => $radek->rok . ' ' . mb_strtolower($radek->nazev), self::radky());
        foreach (DataArcitabulky::Z_PROGRAMU as [$nazev, $rok]) {
            $klice[] = $rok . ' ' . mb_strtolower($nazev);
        }
        self::assertSame([], array_keys(array_filter(array_count_values($klice), static fn (int $pocet) => $pocet > 1)));
    }

    public function testKazdeParovaniOdpovidaPrednasceZTabulky(): void
    {
        $klice = array_map(static fn (RadekArcitabulky $radek) => $radek->rok . ' ' . $radek->nazev, self::radky());
        foreach (DataArcitabulky::PAROVANI as [$nazev, $rok, $idAkce]) {
            self::assertContains("{$rok} {$nazev}", $klice, "Párování s aktivitou {$idAkce} neodpovídá žádné přednášce z tabulky");
            self::assertGreaterThanOrEqual(2018, $rok, 'Staré ročníky se nepárují');
        }
        $idsAkci = [...array_column(DataArcitabulky::PAROVANI, 2), ...array_column(DataArcitabulky::Z_PROGRAMU, 2)];
        self::assertSame($idsAkci, array_values(array_unique($idsAkci)), 'Jedna aktivita patří nejvýš jedné přednášce');
    }

    public function testDoplneneZProgramuSeParujiJenOdRoku2018(): void
    {
        foreach (DataArcitabulky::Z_PROGRAMU as [$nazev, $rok, , , $sparovat]) {
            self::assertSame($rok >= 2018, $sparovat, "„{$nazev}“ ({$rok})");
        }
    }

    public function testKazdyNapojenyPrednasejiciNekdePrednasi(): void
    {
        $jmena = array_merge(
            ...array_map(static fn (RadekArcitabulky $radek) => $radek->prednasejici, self::radky()),
            ...array_column(DataArcitabulky::Z_PROGRAMU, 5),
        );
        foreach (array_keys(DataArcitabulky::UZIVATELE) as $jmeno) {
            self::assertContains($jmeno, $jmena, "Přednášející „{$jmeno}“ má uživatele, ale žádnou přednášku");
        }
        // groups and companies have no user (záměr)
        foreach (['Czech Games Edition', 'Deskofobie', 'Altar', 'Majstri svetov', 'GameCon'] as $skupina) {
            self::assertArrayNotHasKey($skupina, DataArcitabulky::UZIVATELE);
        }
        $idsUzivatelu = array_column(DataArcitabulky::UZIVATELE, 0);
        self::assertSame($idsUzivatelu, array_values(array_unique($idsUzivatelu)), 'Jeden uživatel je jeden přednášející');
    }

    public function testOdkazKteryNeniVideoImportZastavi(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Řádek 5 arcitabulky má odkaz, který není video na YouTube');

        $sloupce = array_fill(0, 13, 'x');
        $sloupce[0] = 'Přednáška';
        $sloupce[10] = '2024';
        $sloupce[12] = 'https://vimeo.com/123';
        (new Arcitabulka())->radek($sloupce, 5);
    }
}
