<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Přečte kritéria hodnocení ze souboru, který org turnaje načte do editoru (@see SouborSablonyHodnoceni). Dva zápisy:
 *
 * Textový (@see rozeber()):
 *
 *     Škála: 1 2 3 4 5
 *     # Kategorie 1
 *     ## Kritérium 1
 *     ## Kritérium 2
 *
 * Tabulka z XLSX, ODS nebo CSV (@see rozeberTabulku()): v prvním sloupci kategorie, ve druhém kritérium, jedno
 * kritérium na řádek; prázdná kategorie je stejná jako o řádek výš. Škála je volitelný řádek „Škála | 1 2 3 4 5“.
 *
 * Text snese BOM, konce řádků z Windows, kódování Windows-1250 (Poznámkový blok), prázdné řádky, mezery okolo
 * i „Skala“ bez diakritiky a velkými písmeny. Co nepozná, vrátí jako chybu s číslem řádku, všechny chyby naráz.
 */
final class ParserSablonyHodnoceni
{
    public const int MAX_DELKA_NAZVU = 255;

    /** Text se ukládá do sloupce TEXT. */
    public const int MAX_DELKA_TEXTU = 65535;

    /**
     * Hodnoty se ukládají do SMALLINT (do 32 767), tohle s rezervou stačí na každou rozumnou škálu a součty skupiny
     * přes všechna kola a kritéria se počítají v PHP, ne v SMALLINT.
     */
    public const int MAX_HODNOTA = 1000;

    /**
     * Víc hodnot se do výběru u kritéria rozumně nevejde. Škála se ukládá spojená mezerami do VARCHAR(255):
     * 20 hodnot po nejvýš 5 znacích („-1000“) s 19 mezerami je 119 znaků, limit délky tedy hlídá i počet.
     */
    public const int MAX_HODNOT_SKALY = 20;

    /**
     * Na turnaj stačí desítky, limity drží formulář editoru pod `max_input_vars` PHP (výchozích 1 000 polí, kritérium
     * má dvě) a pořadí pod posunem při přeřazení (@see HodnoceniTurnaje::DOCASNY_POSUN_PORADI).
     */
    public const int MAX_KATEGORII = 50;

    public const int MAX_KRITERII = 200;

    /** Víc řádků tabulka s nejvýš 200 kritérii mít nemusí, dál ji nečteme (třeba zapomenutý list s daty). */
    public const int MAX_RADKU_TABULKY = 1000;

    /** Škála editoru bez uložených kritérií a tabulky, ve které škála není. */
    public const array VYCHOZI_SKALA = [1, 2, 3, 4, 5];

    public const array HLAVICKA_TABULKY = ['Kategorie', 'Kritérium'];

    public const string SKALA_V_TABULCE = 'Škála';

    public const string UKAZKA = <<<'TEXT'
        Škála: 1 2 3 4 5
        # Kategorie 1
        ## Kritérium 1
        ## Kritérium 2
        # Kategorie 2
        ## Kritérium 1
        TEXT;

    private const int DELKA_UKAZKY_RADKU = 60;

    /** Sloupec `turnaj_hodnoceni_sablona.skala`. */
    private const int MAX_DELKA_SKALY = 255;

    /**
     * @throws ChybaSablonyHodnoceni
     */
    public static function rozeber(string $text): SablonaHodnoceni
    {
        $text = self::normalizuj($text);
        if (strlen($text) > self::MAX_DELKA_TEXTU) {
            throw new ChybaSablonyHodnoceni([
                sprintf('Soubor je moc dlouhý, kritéria se vejdou do %d bajtů.', self::MAX_DELKA_TEXTU),
            ]);
        }

        $chyby = [];
        $skala = null;
        $radekSkaly = null;
        /** @var list<array{nazev: string, radek: int, kriteria: list<array{nazev: string, radek: int}>}> $kategorie */
        $kategorie = [];

        foreach (explode("\n", $text) as $index => $radek) {
            $cisloRadku = $index + 1;
            $radek = trim($radek);
            if ($radek === '') {
                continue;
            }

            if (preg_match('~^(?<mrizky>#+)\s*(?<nazev>.*)$~u', $radek, $nadpis) === 1) {
                $nazev = trim($nadpis['nazev']);
                $uroven = strlen($nadpis['mrizky']);
                $chybaNazvu = self::getChybaNazvu($nazev, $cisloRadku, $uroven === 1
                    ? 'kategorie'
                    : 'kritéria');
                if ($uroven > 2) {
                    $chyby[] = sprintf(
                        'Řádek %d: „%s“ má moc mřížek. Kategorie začíná „# “, kritérium „## “.',
                        $cisloRadku,
                        self::zkrat($radek),
                    );
                } elseif ($chybaNazvu !== null) {
                    $chyby[] = $chybaNazvu;
                } elseif ($uroven === 1) {
                    $stejna = self::najdiStejnyNazev($kategorie, $nazev);
                    if ($stejna !== null) {
                        $chyby[] = sprintf(
                            'Řádek %d: kategorie „%s“ už je na řádku %d.',
                            $cisloRadku,
                            $nazev,
                            $stejna['radek'],
                        );
                    }
                    // i duplicitní kategorie dostane svá kritéria, aby se nehlásila jako kategorie bez kritérií
                    $kategorie[] = ['nazev' => $nazev, 'radek' => $cisloRadku, 'kriteria' => []];
                } elseif ($kategorie === []) {
                    $chyby[] = sprintf(
                        'Řádek %d: kritérium „%s“ nepatří do žádné kategorie, nad ním chybí řádek „# Kategorie“.',
                        $cisloRadku,
                        $nazev,
                    );
                } else {
                    $chybaKriteria = self::pridejKriterium($kategorie[array_key_last($kategorie)], $nazev, $cisloRadku);
                    if ($chybaKriteria !== null) {
                        $chyby[] = $chybaKriteria;
                    }
                }
                continue;
            }

            if (preg_match('~^[šs]k[áa]la\s*:(?<hodnoty>.*)$~iu', $radek, $radekSeSkalou) === 1) {
                if ($radekSkaly !== null) {
                    $chyby[] = sprintf('Řádek %d: škála už je na řádku %d.', $cisloRadku, $radekSkaly);
                    continue;
                }
                $radekSkaly = $cisloRadku;
                try {
                    $skala = self::rozeberSkalu($radekSeSkalou['hodnoty'], "Řádek {$cisloRadku}: ");
                } catch (ChybaSablonyHodnoceni $chybaSkaly) {
                    array_push($chyby, ...$chybaSkaly->getChyby());
                }
                continue;
            }

            $chyby[] = sprintf(
                'Řádek %d: nevím, co je „%s“. Škála začíná „Škála:“, kategorie „# “, kritérium „## “.',
                $cisloRadku,
                self::zkrat($radek),
            );
        }

        if ($radekSkaly === null) {
            $chyby[] = 'Chybí škála, třeba řádek „Škála: 1 2 3 4 5“.';
        }
        if ($kategorie === []) {
            $chyby[] = 'V souboru není žádná kategorie, tedy řádek „# Kategorie“ s kritérii „## Kritérium“ pod ním.';
        }

        return self::sestav($skala, $kategorie, $chyby);
    }

    /**
     * Tabulka: v prvním sloupci kategorie, ve druhém kritérium. Prázdná kategorie je stejná jako o řádek výš (sloučené
     * buňky), stejná kategorie o kus dál pokračuje (tabulka seřazená jinak). Řádek s kategorií a bez kritéria jen
     * zahájí kategorii. Řádek „Škála“ (hodnoty za ním ve stejné buňce nebo v dalších) a hlavičku
     * @see HLAVICKA_TABULKY pozná kdekoli, další sloupce přeskočí.
     *
     * @param iterable<int, list<string>> $radky    buňky řádků podle čísla řádku od 1, prázdné řádky smějí chybět
     * @param list<int>                   $vychoziSkala když v tabulce škála není
     * @throws ChybaSablonyHodnoceni
     */
    public static function rozeberTabulku(iterable $radky, array $vychoziSkala): SablonaHodnoceni
    {
        $chyby = [];
        $skala = null;
        $radekSkaly = null;
        /** @var list<array{nazev: string, radek: int, kriteria: list<array{nazev: string, radek: int}>}> $kategorie */
        $kategorie = [];
        $aktualni = null;

        foreach ($radky as $cisloRadku => $bunky) {
            if ($cisloRadku > self::MAX_RADKU_TABULKY) {
                $chyby[] = sprintf(
                    'Tabulka má víc než %d řádků. Kritéria patří na první list a vejdou se do méně.',
                    self::MAX_RADKU_TABULKY,
                );
                break;
            }
            $bunky = array_map('trim', $bunky);
            $kategorieVRadku = $bunky[0] ?? '';
            $kriteriumVRadku = $bunky[1] ?? '';

            if (preg_match('~^[šs]k[áa]la(?:\s*:|\s|$)(?<hodnoty>.*)$~iu', $kategorieVRadku, $radekSeSkalou) === 1) {
                if ($radekSkaly !== null) {
                    $chyby[] = sprintf('Řádek %d: škála už je na řádku %d.', $cisloRadku, $radekSkaly);
                    continue;
                }
                $radekSkaly = $cisloRadku;
                try {
                    $skala = self::rozeberSkalu(
                        implode(' ', [$radekSeSkalou['hodnoty'], ...array_slice($bunky, 1)]),
                        "Řádek {$cisloRadku}: ",
                    );
                } catch (ChybaSablonyHodnoceni $chybaSkaly) {
                    array_push($chyby, ...$chybaSkaly->getChyby());
                }
                continue;
            }
            if (self::getKlicNazvu($kategorieVRadku) === mb_strtolower(self::HLAVICKA_TABULKY[0])
                && in_array(self::getKlicNazvu($kriteriumVRadku), ['kritérium', 'kriterium', 'kritéria', 'kriteria'], true)
            ) {
                continue;
            }

            if ($kategorieVRadku !== '') {
                $chybaNazvu = self::getChybaNazvu($kategorieVRadku, $cisloRadku, 'kategorie');
                if ($chybaNazvu !== null) {
                    $chyby[] = $chybaNazvu;
                }
                // i chybný název kategorii založí, aby se její kritéria nehlásila jako kritéria bez kategorie
                $aktualni = null;
                foreach ($kategorie as $index => $jednaKategorie) {
                    if (self::getKlicNazvu($jednaKategorie['nazev']) === self::getKlicNazvu($kategorieVRadku)) {
                        $aktualni = $index;
                    }
                }
                if ($aktualni === null) {
                    $kategorie[] = ['nazev' => $kategorieVRadku, 'radek' => $cisloRadku, 'kriteria' => []];
                    $aktualni = array_key_last($kategorie);
                }
            }
            if ($kriteriumVRadku === '') {
                continue;
            }
            if ($aktualni === null) {
                $chyby[] = sprintf(
                    'Řádek %d: kritérium „%s“ nemá kategorii, doplň ji do prvního sloupce.',
                    $cisloRadku,
                    self::zkrat($kriteriumVRadku),
                );
                continue;
            }
            $chybaKriteria = self::getChybaNazvu($kriteriumVRadku, $cisloRadku, 'kritéria')
                ?? self::pridejKriterium($kategorie[$aktualni], $kriteriumVRadku, $cisloRadku);
            if ($chybaKriteria !== null) {
                $chyby[] = $chybaKriteria;
            }
        }

        if ($kategorie === []) {
            $chyby[] = 'V tabulce není žádné kritérium. Kategorie patří do prvního sloupce, kritéria do druhého.';
        }

        return self::sestav($radekSkaly === null
            ? $vychoziSkala
            : $skala, $kategorie, $chyby);
    }

    /**
     * Hodnoty škály oddělené mezerou (snese i čárku a středník).
     *
     * @param string $kde začátek chybové hlášky, třeba „Řádek 3: “; prázdný pro škálu z editoru
     * @return list<int>
     * @throws ChybaSablonyHodnoceni
     */
    public static function rozeberSkalu(string $hodnoty, string $kde): array
    {
        $skala = [];
        $chyby = [];
        foreach (preg_split('~[\s,;]+~u', trim($hodnoty), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $hodnota) {
            if (preg_match('~^-?\d+$~D', $hodnota) !== 1 || abs((int)$hodnota) > self::MAX_HODNOTA) {
                $chyby[] = self::sKde($kde, sprintf(
                    'hodnota škály „%s“ není celé číslo od -%d do %d.',
                    self::zkrat($hodnota),
                    self::MAX_HODNOTA,
                    self::MAX_HODNOTA,
                ));
                continue;
            }
            if (in_array((int)$hodnota, $skala, true)) {
                $chyby[] = self::sKde($kde, sprintf('hodnota škály %d je tam dvakrát.', (int)$hodnota));
                continue;
            }
            $skala[] = (int)$hodnota;
        }
        if ($chyby === [] && count($skala) < 2) {
            $chyby[] = self::sKde($kde, 'škála potřebuje aspoň dvě hodnoty oddělené mezerou, třeba „1 2 3 4 5“.');
        }
        if (count($skala) > self::MAX_HODNOT_SKALY) {
            $chyby[] = self::sKde($kde, sprintf(
                'škála má %d hodnot, nejvýš jde %d.',
                count($skala),
                self::MAX_HODNOT_SKALY,
            ));
        } elseif (strlen(implode(' ', $skala)) > self::MAX_DELKA_SKALY) {
            // při limitech výš nenastane, pojistka pro jejich změnu
            $chyby[] = self::sKde($kde, 'škála je moc dlouhá.');
        }
        if ($chyby !== []) {
            throw new ChybaSablonyHodnoceni($chyby);
        }

        return $skala;
    }

    /**
     * UTF-8 bez BOM s konci řádků \n. Poznámkový blok ve Windows a Excel v CSV ukládají Windows-1250.
     */
    public static function normalizuj(string $text): string
    {
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            $prevedeny = @iconv('WINDOWS-1250', 'UTF-8', $text);
            $text = $prevedeny === false
                ? mb_scrub($text, 'UTF-8')
                : $prevedeny;
        }

        return str_replace(["\r\n", "\r"], "\n", $text);
    }

    /**
     * Názvy se porovnávají bez ohledu na velikost písmen a okrajové mezery: „Hraní rolí“ a „hraní rolí“ by ve výsledcích
     * nikdo nerozlišil.
     */
    public static function getKlicNazvu(string $nazev): string
    {
        return mb_strtolower(trim($nazev));
    }

    /**
     * Kontroly společné oběma zápisům a sestavení šablony.
     *
     * @param list<int>|null                                                                          $skala     null, když ji nejde přečíst (chyba už je v $chyby)
     * @param list<array{nazev: string, radek: int, kriteria: list<array{nazev: string, radek: int}>}> $kategorie
     * @param list<string>                                                                            $chyby
     * @throws ChybaSablonyHodnoceni
     */
    private static function sestav(?array $skala, array $kategorie, array $chyby): SablonaHodnoceni
    {
        foreach ($kategorie as $jednaKategorie) {
            if ($jednaKategorie['kriteria'] === []) {
                $chyby[] = sprintf(
                    'Řádek %d: kategorie „%s“ nemá žádné kritérium.',
                    $jednaKategorie['radek'],
                    $jednaKategorie['nazev'],
                );
            }
        }
        if (count($kategorie) > self::MAX_KATEGORII) {
            $chyby[] = sprintf('V souboru je %d kategorií, nejvýš jde %d.', count($kategorie), self::MAX_KATEGORII);
        }
        $pocetKriterii = array_sum(array_map(
            static fn (array $jednaKategorie): int => count($jednaKategorie['kriteria']),
            $kategorie,
        ));
        if ($pocetKriterii > self::MAX_KRITERII) {
            $chyby[] = sprintf('V souboru je %d kritérií, nejvýš jde %d.', $pocetKriterii, self::MAX_KRITERII);
        }
        // bez škály je vždy i chyba, podmínka na ni je tu kvůli typu
        if ($chyby !== [] || $skala === null) {
            usort($chyby, static fn (string $a, string $b): int => self::getCisloRadku($a) <=> self::getCisloRadku($b));
            throw new ChybaSablonyHodnoceni($chyby);
        }

        return new SablonaHodnoceni(
            null,
            $skala,
            array_map(
                static fn (array $jednaKategorie): KategorieHodnoceni => new KategorieHodnoceni(
                    null,
                    $jednaKategorie['nazev'],
                    array_map(
                        static fn (array $kriterium): KriteriumHodnoceni => new KriteriumHodnoceni(null, $kriterium['nazev']),
                        $jednaKategorie['kriteria'],
                    ),
                ),
                $kategorie,
            ),
        );
    }

    /**
     * @param array{nazev: string, radek: int, kriteria: list<array{nazev: string, radek: int}>} $kategorie
     * @return string|null chyba, když už kategorie stejné kritérium má
     */
    private static function pridejKriterium(array &$kategorie, string $nazev, int $cisloRadku): ?string
    {
        $stejne = self::najdiStejnyNazev($kategorie['kriteria'], $nazev);
        if ($stejne !== null) {
            return sprintf(
                'Řádek %d: kritérium „%s“ už je v kategorii „%s“ na řádku %d.',
                $cisloRadku,
                $nazev,
                $kategorie['nazev'],
                $stejne['radek'],
            );
        }
        $kategorie['kriteria'][] = ['nazev' => $nazev, 'radek' => $cisloRadku];

        return null;
    }

    private static function getChybaNazvu(string $nazev, int $cisloRadku, string $coJmenuje): ?string
    {
        if ($nazev === '') {
            return sprintf('Řádek %d: chybí název %s.', $cisloRadku, $coJmenuje);
        }
        if (mb_strlen($nazev) > self::MAX_DELKA_NAZVU) {
            return sprintf(
                'Řádek %d: název %s je delší než %d znaků.',
                $cisloRadku,
                $coJmenuje,
                self::MAX_DELKA_NAZVU,
            );
        }

        return null;
    }

    /**
     * @template T of array{nazev: string, radek: int}
     * @param list<T> $polozky
     * @return T|null
     */
    private static function najdiStejnyNazev(array $polozky, string $nazev): ?array
    {
        foreach ($polozky as $polozka) {
            if (self::getKlicNazvu($polozka['nazev']) === self::getKlicNazvu($nazev)) {
                return $polozka;
            }
        }

        return null;
    }

    private static function sKde(string $kde, string $zprava): string
    {
        return $kde === ''
            ? mb_strtoupper(mb_substr($zprava, 0, 1)) . mb_substr($zprava, 1)
            : $kde . $zprava;
    }

    private static function zkrat(string $text): string
    {
        return mb_strlen($text) > self::DELKA_UKAZKY_RADKU
            ? mb_substr($text, 0, self::DELKA_UKAZKY_RADKU) . '…'
            : $text;
    }

    /**
     * Chyby bez čísla řádku (chybí škála, žádná kategorie) patří na konec.
     */
    private static function getCisloRadku(string $chyba): int
    {
        return preg_match('~^Řádek (?<cislo>\d+):~u', $chyba, $shoda) === 1
            ? (int)$shoda['cislo']
            : PHP_INT_MAX;
    }
}
