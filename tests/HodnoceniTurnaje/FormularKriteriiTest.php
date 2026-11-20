<?php

declare(strict_types=1);

namespace Gamecon\Tests\HodnoceniTurnaje;

use Gamecon\HodnoceniTurnaje\ChybaSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\FormularKriterii;
use Gamecon\HodnoceniTurnaje\KategorieHodnoceni;
use Gamecon\HodnoceniTurnaje\KriteriumHodnoceni;
use Gamecon\HodnoceniTurnaje\ParserSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\SablonaHodnoceni;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Editor kritérií: odeslaný formulář s řádky v pořadí na stránce, uložené řádky s id, nové bez něj. Chyby hlásí podle
 * názvů.
 */
class FormularKriteriiTest extends TestCase
{
    public function testOdeslanyFormularDrziPoradiAId(): void
    {
        $formular = FormularKriterii::zPostu(' 5 4 3 ', [
            'j2' => [
                'id'       => '',
                'nazev'    => ' Řešení ',
                'kriteria' => [
                    'j3' => [
                        'id'    => '',
                        'nazev' => 'Nápaditost',
                    ],
                ],
            ],
            'i12' => [
                'id'       => '12',
                'nazev'    => 'Hraní rolí',
                'kriteria' => [
                    'i31' => [
                        'id'    => '31',
                        'nazev' => 'Spolupráce',
                    ],
                    'i30' => [
                        'id'    => '30',
                        'nazev' => 'Postavy',
                    ],
                ],
            ],
        ]);

        $sablona = $formular->getSablona();

        self::assertSame([5, 4, 3], $sablona->skala);
        self::assertSame(['Řešení', 'Hraní rolí'], array_column($sablona->kategorie, 'nazev'));
        self::assertSame([null, 12], array_column($sablona->kategorie, 'id'));
        self::assertSame([31, 30], array_column($sablona->kategorie[1]->kriteria, 'id'));
        self::assertSame(['Spolupráce', 'Postavy'], array_column($sablona->kategorie[1]->kriteria, 'nazev'));
        self::assertSame('j2', $formular->kategorie[0]['klic'], 'Klíč zůstane, ať chybný formulář jde odeslat znovu');
    }

    public function testCiziTvarFormularePreskoci(): void
    {
        $formular = FormularKriterii::zPostu(['1 2'], [
            'nesmysl'  => 'text',
            '<script>' => [
                'id'       => '0',
                'nazev'    => ['pole'],
                'kriteria' => 'text',
            ],
            'i5' => [
                'id'       => '5abc',
                'nazev'    => 'Hraní',
                'kriteria' => [[
                    'id'    => '-3',
                    'nazev' => 'Postavy',
                ], 'text'],
            ],
        ]);

        self::assertSame('', $formular->skala);
        self::assertSame([
            [
                'klic'     => 'x1',
                'id'       => null,
                'nazev'    => '',
                'kriteria' => [],
            ],
            [
                'klic'     => 'i5',
                'id'       => null,
                'nazev'    => 'Hraní',
                'kriteria' => [[
                    'klic'  => 'x1',
                    'id'    => null,
                    'nazev' => 'Postavy',
                ]],
            ],
        ], $formular->kategorie);
    }

    /**
     * @param list<string> $ocekavaneChyby
     */
    #[DataProvider('chybneFormulare')]
    public function testChybnyFormularHlasiVsechnyChyby(FormularKriterii $formular, array $ocekavaneChyby): void
    {
        try {
            $formular->getSablona();
            self::fail('Formulář neměl jít uložit');
        } catch (ChybaSablonyHodnoceni $chybaSablonyHodnoceni) {
            self::assertSame($ocekavaneChyby, $chybaSablonyHodnoceni->getChyby());
        }
    }

    /**
     * @return \Generator<string, array{FormularKriterii, list<string>}>
     */
    public static function chybneFormulare(): \Generator
    {
        yield 'bez kategorií a se špatnou škálou' => [
            new FormularKriterii('5', []),
            [
                'Škála potřebuje aspoň dvě hodnoty oddělené mezerou, třeba „1 2 3 4 5“.',
                'Přidej aspoň jednu kategorii s kritériem.',
            ],
        ];
        yield 'prázdné názvy, dvojí názvy a kategorie bez kritérií' => [
            new FormularKriterii('1 2', [
                self::kategorie('', ['Postavy', '']),
                self::kategorie('Hraní', ['Postavy', 'POSTAVY ']),
                self::kategorie('hraní', []),
            ]),
            [
                'Chybí název 1. kategorie.',
                'V 1. kategorii chybí název 2. kritéria.',
                'V kategorii „Hraní“ je dvakrát kritérium „POSTAVY“.',
                'Kategorie „hraní“ je tam dvakrát.',
                'Kategorie „hraní“ nemá žádné kritérium.',
            ],
        ];
        yield 'moc dlouhé názvy' => [
            new FormularKriterii('1 2', [self::kategorie(str_repeat('a', 256), [str_repeat('b', 256)])]),
            [
                'Název 1. kategorie je delší než 255 znaků.',
                'V kategorii „' . str_repeat('a', 256) . '“ je název 1. kritéria delší než 255 znaků.',
            ],
        ];
        yield 'moc kritérií' => [
            new FormularKriterii('1 2', [self::kategorie('Hraní', array_map(
                static fn (int $cislo): string => "Kritérium {$cislo}",
                range(1, ParserSablonyHodnoceni::MAX_KRITERII + 1),
            ))]),
            ['Kritérií je 201, nejvýš jde 200.'],
        ];
    }

    public function testUlozenaSablonaMaKlicePodleIdANovaPoradi(): void
    {
        $formular = FormularKriterii::zeSablony(new SablonaHodnoceni(7, [1, 2], [
            new KategorieHodnoceni(3, 'Hraní', [new KriteriumHodnoceni(8, 'Postavy'), new KriteriumHodnoceni(null, 'Hlas')]),
        ]));

        self::assertSame('1 2', $formular->skala);
        self::assertSame('i3', $formular->kategorie[0]['klic']);
        self::assertSame(['i8', 'n2'], array_column($formular->kategorie[0]['kriteria'], 'klic'));
        self::assertSame([8, null], array_column($formular->kategorie[0]['kriteria'], 'id'));
    }

    public function testPrazdnyFormularMaVychoziSkaluAJedenRadek(): void
    {
        $prazdny = FormularKriterii::prazdny();

        self::assertSame('1 2 3 4 5', $prazdny->skala);
        self::assertCount(1, $prazdny->kategorie);
        self::assertCount(1, $prazdny->kategorie[0]['kriteria']);
    }

    /**
     * @param list<string> $kriteria
     *
     * @return array{klic: string, id: ?int, nazev: string, kriteria: list<array{klic: string, id: ?int, nazev: string}>}
     */
    private static function kategorie(string $nazev, array $kriteria): array
    {
        return [
            'klic'     => 'n1',
            'id'       => null,
            'nazev'    => trim($nazev),
            'kriteria' => array_map(
                static fn (string $kriterium, int $index): array => [
                    'klic'  => 'n' . ($index + 1),
                    'id'    => null,
                    'nazev' => trim($kriterium),
                ],
                $kriteria,
                array_keys($kriteria),
            ),
        ];
    }
}
