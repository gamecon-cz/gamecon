<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Přečte textový soubor s kritérii hodnocení:
 *
 *     Škála: 1 2 3 4 5
 *     # Kategorie 1
 *     ## Kritérium 1
 *     ## Kritérium 2
 *
 * Snese BOM, konce řádků z Windows, kódování Windows-1250 (Poznámkový blok), prázdné řádky, mezery okolo i „Skala“
 * bez diakritiky a velkými písmeny. Co nepozná, vrátí jako chybu s číslem řádku, všechny chyby naráz.
 */
final class ParserSablonyHodnoceni
{
    public const int MAX_DELKA_NAZVU = 255;

    /** Zdroj se ukládá do sloupce TEXT. */
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

    /** Sloupec `turnaj_hodnoceni_sablona.skala`. */
    private const int MAX_DELKA_SKALY = 255;

    public const string UKAZKA = <<<'TEXT'
        Škála: 1 2 3 4 5
        # Kategorie 1
        ## Kritérium 1
        ## Kritérium 2
        # Kategorie 2
        ## Kritérium 1
        TEXT;

    private const int DELKA_UKAZKY_RADKU = 60;

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
                    $posledni = array_key_last($kategorie);
                    $stejne = self::najdiStejnyNazev($kategorie[$posledni]['kriteria'], $nazev);
                    if ($stejne !== null) {
                        $chyby[] = sprintf(
                            'Řádek %d: kritérium „%s“ už je v kategorii „%s“ na řádku %d.',
                            $cisloRadku,
                            $nazev,
                            $kategorie[$posledni]['nazev'],
                            $stejne['radek'],
                        );
                    } else {
                        $kategorie[$posledni]['kriteria'][] = ['nazev' => $nazev, 'radek' => $cisloRadku];
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
                    $skala = self::rozeberSkalu($radekSeSkalou['hodnoty'], $cisloRadku);
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
        foreach ($kategorie as $jednaKategorie) {
            if ($jednaKategorie['kriteria'] === []) {
                $chyby[] = sprintf(
                    'Řádek %d: kategorie „%s“ nemá žádné kritérium.',
                    $jednaKategorie['radek'],
                    $jednaKategorie['nazev'],
                );
            }
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
            $text,
        );
    }

    /**
     * UTF-8 bez BOM s konci řádků \n. Poznámkový blok ve Windows ukládal Windows-1250.
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
     * @return list<int>
     * @throws ChybaSablonyHodnoceni
     */
    private static function rozeberSkalu(string $hodnoty, int $cisloRadku): array
    {
        $skala = [];
        $chyby = [];
        foreach (preg_split('~[\s,;]+~u', trim($hodnoty), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $hodnota) {
            if (preg_match('~^-?\d+$~D', $hodnota) !== 1 || abs((int)$hodnota) > self::MAX_HODNOTA) {
                $chyby[] = sprintf(
                    'Řádek %d: hodnota škály „%s“ není celé číslo od -%d do %d.',
                    $cisloRadku,
                    self::zkrat($hodnota),
                    self::MAX_HODNOTA,
                    self::MAX_HODNOTA,
                );
                continue;
            }
            if (in_array((int)$hodnota, $skala, true)) {
                $chyby[] = sprintf('Řádek %d: hodnota škály %d je tam dvakrát.', $cisloRadku, (int)$hodnota);
                continue;
            }
            $skala[] = (int)$hodnota;
        }
        if ($chyby === [] && count($skala) < 2) {
            $chyby[] = sprintf(
                'Řádek %d: škála potřebuje aspoň dvě hodnoty oddělené mezerou, třeba „Škála: 1 2 3 4 5“.',
                $cisloRadku,
            );
        }
        if (count($skala) > self::MAX_HODNOT_SKALY) {
            $chyby[] = sprintf(
                'Řádek %d: škála má %d hodnot, nejvýš jde %d.',
                $cisloRadku,
                count($skala),
                self::MAX_HODNOT_SKALY,
            );
        } elseif (strlen(implode(' ', $skala)) > self::MAX_DELKA_SKALY) {
            // při limitech výš nenastane, pojistka pro jejich změnu
            $chyby[] = sprintf('Řádek %d: škála je moc dlouhá.', $cisloRadku);
        }
        if ($chyby !== []) {
            throw new ChybaSablonyHodnoceni($chyby);
        }

        return $skala;
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
     * Stejný název bez ohledu na velikost písmen: „Hraní rolí“ a „hraní rolí“ by ve výsledcích nikdo nerozlišil.
     *
     * @template T of array{nazev: string, radek: int}
     * @param list<T> $polozky
     * @return T|null
     */
    private static function najdiStejnyNazev(array $polozky, string $nazev): ?array
    {
        foreach ($polozky as $polozka) {
            if (mb_strtolower($polozka['nazev']) === mb_strtolower($nazev)) {
                return $polozka;
            }
        }

        return null;
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
