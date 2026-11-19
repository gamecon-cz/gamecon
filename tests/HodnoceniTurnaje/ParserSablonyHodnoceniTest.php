<?php

declare(strict_types=1);

namespace Gamecon\Tests\HodnoceniTurnaje;

use Gamecon\HodnoceniTurnaje\ChybaSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\KategorieHodnoceni;
use Gamecon\HodnoceniTurnaje\KriteriumHodnoceni;
use Gamecon\HodnoceniTurnaje\ParserSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\SablonaHodnoceni;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Soubor s kritérii hodnocení turnaje, text (škála, „# Kategorie“, „## Kritérium“) i tabulka (kategorie | kritérium).
 * Co nejde přečíst, hlásí s číslem řádku.
 */
class ParserSablonyHodnoceniTest extends TestCase
{
    /**
     * Ukázka z karty 1513, bez překlepu v kategorii 3.
     */
    private const string UKAZKA_Z_KARTY = <<<'TEXT'
        Škála: 1 2 3 4 5
        # Kategorie 1
        ## Kritérium 1
        ## Kritérium 2
        ## Kritérium 3
        # Kategorie 2
        ## Kritérium 1
        ## Kritérium 2
        ## Kritérium 3
        # Kategorie 3
        ## Kritérium 1
        ## Kritérium 2
        ## Kritérium 3
        # Kategorie 4
        ## Kritérium 1
        TEXT;

    public function testPrectePrikladZKarty(): void
    {
        $sablona = ParserSablonyHodnoceni::rozeber(self::UKAZKA_Z_KARTY);

        self::assertNull($sablona->id);
        self::assertSame([1, 2, 3, 4, 5], $sablona->skala);
        self::assertSame(
            ['Kategorie 1', 'Kategorie 2', 'Kategorie 3', 'Kategorie 4'],
            array_map(static fn (KategorieHodnoceni $kategorie): string => $kategorie->nazev, $sablona->kategorie),
        );
        // stejné názvy kritérií v různých kategoriích nevadí
        self::assertSame(
            ['Kritérium 1', 'Kritérium 2', 'Kritérium 3'],
            array_map(static fn (KriteriumHodnoceni $kriterium): string => $kriterium->nazev, $sablona->kategorie[2]->kriteria),
        );
        self::assertSame([3, 3, 3, 1], array_map(
            static fn (KategorieHodnoceni $kategorie): int => count($kategorie->kriteria),
            $sablona->kategorie,
        ));
        self::assertSame(self::UKAZKA_Z_KARTY, $sablona->getText(), 'Textový zápis jde zpátky');
    }

    public function testDuplicitniKriteriumVKategoriiJeChybaSCislemRadku(): void
    {
        // přesně jak je na kartě: v kategorii 3 dvakrát „Kritérium 2“
        $text = str_replace(
            "# Kategorie 3\n## Kritérium 1\n## Kritérium 2\n## Kritérium 3",
            "# Kategorie 3\n## Kritérium 1\n## Kritérium 2\n## Kritérium 2",
            self::UKAZKA_Z_KARTY,
        );

        self::assertSame(
            ['Řádek 13: kritérium „Kritérium 2“ už je v kategorii „Kategorie 3“ na řádku 12.'],
            $this->getChyby($text),
        );
    }

    public function testSnesePoznamkovyBlok(): void
    {
        $text = "\xEF\xBB\xBF  SKALA :  0, 1 ,2  \r\n\r\n#Hraní rolí  \r\n ##  Postavy\r\n## Spolupráce\r\n";

        $sablona = ParserSablonyHodnoceni::rozeber($text);

        self::assertSame([0, 1, 2], $sablona->skala);
        self::assertSame('Hraní rolí', $sablona->kategorie[0]->nazev);
        self::assertSame('Postavy', $sablona->kategorie[0]->kriteria[0]->nazev);
        self::assertSame("Škála: 0 1 2\n# Hraní rolí\n## Postavy\n## Spolupráce", $sablona->getText());
    }

    public function testPrevedeKodovaniWindows1250(): void
    {
        $text = (string) iconv('UTF-8', 'WINDOWS-1250', "Škála: 1 2 3\n# Řešení\n## Nápaditost");

        $sablona = ParserSablonyHodnoceni::rozeber($text);

        self::assertSame('Řešení', $sablona->kategorie[0]->nazev);
        self::assertSame('Nápaditost', $sablona->kategorie[0]->kriteria[0]->nazev);
    }

    /**
     * @param list<string> $ocekavaneChyby
     */
    #[DataProvider('chybneSoubory')]
    public function testChybnySouborHlasiVsechnyChyby(string $text, array $ocekavaneChyby): void
    {
        self::assertSame($ocekavaneChyby, $this->getChyby($text));
    }

    /**
     * @return \Generator<string, array{string, list<string>}>
     */
    public static function chybneSoubory(): \Generator
    {
        yield 'kritérium před kategorií' => [
            "Škála: 1 2\n## Postavy\n# Hraní\n## Spolupráce",
            ['Řádek 2: kritérium „Postavy“ nepatří do žádné kategorie, nad ním chybí řádek „# Kategorie“.'],
        ];
        yield 'kategorie bez kritérií' => [
            "Škála: 1 2\n# Hraní\n# Řešení\n## Nápaditost",
            ['Řádek 2: kategorie „Hraní“ nemá žádné kritérium.'],
        ];
        yield 'neznámý řádek a moc mřížek' => [
            "Škála: 1 2\n# Hraní\nPostavy\n### Podkritérium",
            [
                'Řádek 2: kategorie „Hraní“ nemá žádné kritérium.',
                'Řádek 3: nevím, co je „Postavy“. Škála začíná „Škála:“, kategorie „# “, kritérium „## “.',
                'Řádek 4: „### Podkritérium“ má moc mřížek. Kategorie začíná „# “, kritérium „## “.',
            ],
        ];
        yield 'bez škály a bez kategorií' => [
            "\n\n",
            [
                'Chybí škála, třeba řádek „Škála: 1 2 3 4 5“.',
                'V souboru není žádná kategorie, tedy řádek „# Kategorie“ s kritérii „## Kritérium“ pod ním.',
            ],
        ];
        yield 'škála s nečíslem a dvojí hodnotou' => [
            "Škála: 1 dobrý 1 2\n# Hraní\n## Postavy",
            [
                'Řádek 1: hodnota škály „dobrý“ není celé číslo od -1000 do 1000.',
                'Řádek 1: hodnota škály 1 je tam dvakrát.',
            ],
        ];
        yield 'škála s víc než dvaceti hodnotami' => [
            'Škála: ' . implode(' ', range(0, 20)) . "\n# Hraní\n## Postavy",
            ['Řádek 1: škála má 21 hodnot, nejvýš jde 20.'],
        ];
        yield 'hodnota škály mimo rozsah' => [
            "Škála: 1 1001 -40000 99999999999999999999\n# Hraní\n## Postavy",
            [
                'Řádek 1: hodnota škály „1001“ není celé číslo od -1000 do 1000.',
                'Řádek 1: hodnota škály „-40000“ není celé číslo od -1000 do 1000.',
                'Řádek 1: hodnota škály „99999999999999999999“ není celé číslo od -1000 do 1000.',
            ],
        ];
        yield 'škála s jednou hodnotou a druhá škála' => [
            "Škála: 5\n# Hraní\n## Postavy\nŠkála: 1 2",
            [
                'Řádek 1: škála potřebuje aspoň dvě hodnoty oddělené mezerou, třeba „1 2 3 4 5“.',
                'Řádek 4: škála už je na řádku 1.',
            ],
        ];
        yield 'stejná kategorie jinými písmeny a prázdný název' => [
            "Škála: 1 2\n# Hraní rolí\n## Postavy\n# HRANÍ ROLÍ\n## Spolupráce\n##",
            [
                'Řádek 4: kategorie „HRANÍ ROLÍ“ už je na řádku 2.',
                'Řádek 6: chybí název kritéria.',
            ],
        ];
    }

    public function testDvacetKrajnichHodnotSkalySeVejde(): void
    {
        $skala = array_merge(range(-1000, -991), range(991, 1000));

        $sablona = ParserSablonyHodnoceni::rozeber('Škála: ' . implode(' ', $skala) . "\n# Hraní\n## Postavy");

        self::assertSame($skala, $sablona->skala);
        self::assertLessThanOrEqual(255, strlen(implode(' ', $sablona->skala)), 'Škála se vejde do VARCHAR(255)');
    }

    public function testPrecteTabulku(): void
    {
        $sablona = ParserSablonyHodnoceni::rozeberTabulku([
            1 => ['Kategorie', 'Kritérium', 'Poznámka'],
            2 => ['Škála', '1 2 3'],
            4 => [' Hraní rolí ', 'Postavy', 'další sloupce se přeskočí'],
            // prázdná kategorie je stejná jako o řádek výš (sloučené buňky)
            5 => ['', 'Spolupráce'],
            // řádek jen s kategorií ji zahájí
            6 => ['Řešení', ''],
            7 => ['', 'Nápaditost'],
            // stejná kategorie o kus dál pokračuje, bez ohledu na velikost písmen
            8  => ['hraní rolí', 'Hlas'],
            9  => ['', '', ''],
            10 => ['Škálování', 'Rychlost'],
        ], ParserSablonyHodnoceni::VYCHOZI_SKALA);

        self::assertSame([1, 2, 3], $sablona->skala);
        self::assertSame(['Hraní rolí', 'Řešení', 'Škálování'], array_column($sablona->kategorie, 'nazev'));
        self::assertSame(['Postavy', 'Spolupráce', 'Hlas'], array_column($sablona->kategorie[0]->kriteria, 'nazev'));
        self::assertSame(['Nápaditost'], array_column($sablona->kategorie[1]->kriteria, 'nazev'));
    }

    public function testTabulkaBezSkalyVezmeVychoziAJinakSkaluPoznaVsude(): void
    {
        self::assertSame(
            [0, 1, 2],
            ParserSablonyHodnoceni::rozeberTabulku([
                1 => ['Hraní', 'Postavy'],
            ], [0, 1, 2])->skala,
            'Bez řádku se škálou zůstane škála z editoru',
        );
        foreach ([['Škála', '1', '2', '3'], ['SKALA:', '1, 2, 3'], ['Škála: 1 2', '3']] as $radekSeSkalou) {
            self::assertSame(
                [1, 2, 3],
                ParserSablonyHodnoceni::rozeberTabulku([
                    1 => ['Hraní', 'Postavy'],
                    2 => $radekSeSkalou,
                ], [0, 1])->skala,
                implode(' | ', $radekSeSkalou),
            );
        }
    }

    /**
     * @param array<int, list<string>> $radky
     * @param list<string>             $ocekavaneChyby
     */
    #[DataProvider('chybneTabulky')]
    public function testChybnaTabulkaHlasiVsechnyChyby(array $radky, array $ocekavaneChyby): void
    {
        self::assertSame($ocekavaneChyby, $this->getChybyTabulky($radky));
    }

    /**
     * @return \Generator<string, array{array<int, list<string>>, list<string>}>
     */
    public static function chybneTabulky(): \Generator
    {
        yield 'kritérium bez kategorie a dvakrát stejné' => [
            [
                1 => ['', 'Postavy'],
                2 => ['Hraní', 'Spolupráce'],
                3 => ['', 'spolupráce'],
            ],
            [
                'Řádek 1: kritérium „Postavy“ nemá kategorii, doplň ji do prvního sloupce.',
                'Řádek 3: kritérium „spolupráce“ už je v kategorii „Hraní“ na řádku 2.',
            ],
        ];
        yield 'kategorie bez kritérií a dvě škály' => [
            [
                1 => ['Škála', '1 2'],
                2 => ['Hraní', ''],
                3 => ['Řešení', 'Nápaditost'],
                4 => ['Škála', 'pět'],
            ],
            [
                'Řádek 2: kategorie „Hraní“ nemá žádné kritérium.',
                'Řádek 4: škála už je na řádku 1.',
            ],
        ];
        yield 'špatná škála' => [
            [
                1 => ['Škála', '1 x'],
                2 => ['Hraní', 'Postavy'],
            ],
            ['Řádek 1: hodnota škály „x“ není celé číslo od -1000 do 1000.'],
        ];
        yield 'prázdná tabulka' => [
            [
                1 => ['Kategorie', 'Kritérium'],
                2 => ['', ''],
            ],
            ['V tabulce není žádné kritérium. Kategorie patří do prvního sloupce, kritéria do druhého.'],
        ];
        yield 'moc dlouhý název' => [
            [
                1 => [str_repeat('a', 256), 'Postavy'],
            ],
            ['Řádek 1: název kategorie je delší než 255 znaků.'],
        ];
    }

    public function testTabulkaAZapisJdouZpatky(): void
    {
        $sablona = ParserSablonyHodnoceni::rozeber(self::UKAZKA_Z_KARTY);
        $radky = [ParserSablonyHodnoceni::HLAVICKA_TABULKY, ...$sablona->getRadkyTabulky()];

        $zTabulky = ParserSablonyHodnoceni::rozeberTabulku(
            array_combine(range(1, count($radky)), $radky),
            [0, 1],
        );

        self::assertSame(self::UKAZKA_Z_KARTY, $zTabulky->getText());
        self::assertSame(['Škála', '1 2 3 4 5'], $sablona->getRadkyTabulky()[0]);
        self::assertSame(['Kategorie 1', 'Kritérium 1'], $sablona->getRadkyTabulky()[1]);
    }

    public function testMocKategoriiNeprojde(): void
    {
        $radky = [];
        foreach (range(1, ParserSablonyHodnoceni::MAX_KATEGORII + 1) as $cislo) {
            $radky[$cislo] = ["Kategorie {$cislo}", 'Kritérium'];
        }

        self::assertSame(['V souboru je 51 kategorií, nejvýš jde 50.'], $this->getChybyTabulky($radky));
    }

    public function testSkalaZEditoruHlasiChybyBezRadku(): void
    {
        try {
            ParserSablonyHodnoceni::rozeberSkalu('1 x 1', '');
            self::fail('Škála měla mít chyby');
        } catch (ChybaSablonyHodnoceni $chybaSablonyHodnoceni) {
            self::assertSame(
                ['Hodnota škály „x“ není celé číslo od -1000 do 1000.', 'Hodnota škály 1 je tam dvakrát.'],
                $chybaSablonyHodnoceni->getChyby(),
            );
        }
    }

    public function testUkazkaVeFormulariJdePrecist(): void
    {
        self::assertInstanceOf(SablonaHodnoceni::class, ParserSablonyHodnoceni::rozeber(ParserSablonyHodnoceni::UKAZKA));
    }

    /**
     * @param array<int, list<string>> $radky
     *
     * @return list<string>
     */
    private function getChybyTabulky(array $radky): array
    {
        try {
            ParserSablonyHodnoceni::rozeberTabulku($radky, ParserSablonyHodnoceni::VYCHOZI_SKALA);
        } catch (ChybaSablonyHodnoceni $chybaSablonyHodnoceni) {
            return $chybaSablonyHodnoceni->getChyby();
        }
        self::fail('Tabulka měla jít přečíst jen s chybou');
    }

    /**
     * @return list<string>
     */
    private function getChyby(string $text): array
    {
        try {
            ParserSablonyHodnoceni::rozeber($text);
        } catch (ChybaSablonyHodnoceni $chybaSablonyHodnoceni) {
            return $chybaSablonyHodnoceni->getChyby();
        }
        self::fail('Soubor měl jít přečíst jen s chybou');
    }
}
