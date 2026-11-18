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
 * Soubor s kritérii hodnocení turnaje: škála, „# Kategorie“, „## Kritérium“. Co nejde přečíst, hlásí s číslem řádku.
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
        self::assertSame([
            'skala'    => [1, 2, 3, 4, 5],
            'kriteria' => [3, 3, 3, 1],
        ], $sablona->getTvar());
        self::assertSame(10, $sablona->getPocetKriterii());
        self::assertSame(self::UKAZKA_Z_KARTY, $sablona->zdroj);
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
            $this->chyby($text),
        );
    }

    public function testSnesePoznamkovyBlok(): void
    {
        $text = "\xEF\xBB\xBF  SKALA :  0, 1 ,2  \r\n\r\n#Hraní rolí  \r\n ##  Postavy\r\n## Spolupráce\r\n";

        $sablona = ParserSablonyHodnoceni::rozeber($text);

        self::assertSame([0, 1, 2], $sablona->skala);
        self::assertSame('Hraní rolí', $sablona->kategorie[0]->nazev);
        self::assertSame('Postavy', $sablona->kategorie[0]->kriteria[0]->nazev);
        self::assertStringNotContainsString("\r", $sablona->zdroj);
        self::assertStringStartsNotWith("\xEF\xBB\xBF", $sablona->zdroj);
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
        self::assertSame($ocekavaneChyby, $this->chyby($text));
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
        yield 'škála s jednou hodnotou a druhá škála' => [
            "Škála: 5\n# Hraní\n## Postavy\nŠkála: 1 2",
            [
                'Řádek 1: škála potřebuje aspoň dvě hodnoty oddělené mezerou, třeba „Škála: 1 2 3 4 5“.',
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

    public function testStejnyTvarJeStejnaSkalaAPoctyKriterii(): void
    {
        $sablona = ParserSablonyHodnoceni::rozeber("Škála: 1 2 3\n# A\n## a\n## b\n# B\n## c");
        $prejmenovana = ParserSablonyHodnoceni::rozeber("Škála: 1 2 3\n# Hraní\n## x\n## y\n# Řešení\n## z");
        $jinaSkala = ParserSablonyHodnoceni::rozeber("Škála: 1 2 3 4\n# A\n## a\n## b\n# B\n## c");
        $jinyPocet = ParserSablonyHodnoceni::rozeber("Škála: 1 2 3\n# A\n## a\n# B\n## b\n## c");

        self::assertTrue($sablona->isStejnyTvar($prejmenovana));
        self::assertFalse($sablona->isStejnyTvar($jinaSkala));
        self::assertFalse($sablona->isStejnyTvar($jinyPocet));
    }

    public function testUkazkaVeFormulariJdePrecist(): void
    {
        self::assertInstanceOf(SablonaHodnoceni::class, ParserSablonyHodnoceni::rozeber(ParserSablonyHodnoceni::UKAZKA));
    }

    /**
     * @return list<string>
     */
    private function chyby(string $text): array
    {
        try {
            ParserSablonyHodnoceni::rozeber($text);
        } catch (ChybaSablonyHodnoceni $chybaSablonyHodnoceni) {
            return $chybaSablonyHodnoceni->getChyby();
        }
        self::fail('Soubor měl jít přečíst jen s chybou');
    }
}
