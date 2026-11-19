<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Co je v editoru kritérií: škála, jak ji org napsal, a kategorie s kritérii v pořadí na stránce. Uložené kategorie
 * a kritéria nesou id, takže přejmenování i přeřazení se pozná bez porovnávání názvů (@see HodnoceniTurnaje::ulozSablonu()).
 *
 * Klíč řádku pojmenovává pole formuláře (`kategorie[klíč][kriteria][klíč][nazev]`): u uložených je z id, nové řádky
 * mají vlastní (JavaScript editoru `j1`, `j2`…, server `n1`…). PHP skládá pole v pořadí polí ve formuláři, pořadí
 * řádků na stránce tedy dorazí s nimi.
 */
final readonly class FormularKriterii
{
    /**
     * @param list<array{klic: string, id: ?int, nazev: string, kriteria: list<array{klic: string, id: ?int, nazev: string}>}> $kategorie
     */
    public function __construct(
        public string $skala,
        public array $kategorie,
    ) {
    }

    /**
     * Prázdný editor: výchozí škála a jedna kategorie s jedním kritériem k vyplnění.
     */
    public static function prazdny(): self
    {
        return new self(
            implode(' ', ParserSablonyHodnoceni::VYCHOZI_SKALA),
            [['klic' => 'n1', 'id' => null, 'nazev' => '', 'kriteria' => [['klic' => 'n1', 'id' => null, 'nazev' => '']]]],
        );
    }

    public static function zeSablony(SablonaHodnoceni $sablona): self
    {
        $kategorie = [];
        foreach ($sablona->kategorie as $indexKategorie => $jednaKategorie) {
            $kriteria = [];
            foreach ($jednaKategorie->kriteria as $indexKriteria => $kriterium) {
                $kriteria[] = [
                    'klic'  => self::getKlic($kriterium->id, $indexKriteria),
                    'id'    => $kriterium->id,
                    'nazev' => $kriterium->nazev,
                ];
            }
            $kategorie[] = [
                'klic'     => self::getKlic($jednaKategorie->id, $indexKategorie),
                'id'       => $jednaKategorie->id,
                'nazev'    => $jednaKategorie->nazev,
                'kriteria' => $kriteria,
            ];
        }

        return new self(implode(' ', $sablona->skala), $kategorie);
    }

    /**
     * Z odeslaného formuláře. Co nemá tvar formuláře editoru, přeskočí.
     */
    public static function zPostu(mixed $skala, mixed $kategorie): self
    {
        $vysledek = [];
        foreach (is_array($kategorie)
            ? $kategorie
            : [] as $klicKategorie => $jednaKategorie) {
            if (! is_array($jednaKategorie)) {
                continue;
            }
            $kriteria = [];
            foreach (is_array($jednaKategorie['kriteria'] ?? null)
                ? $jednaKategorie['kriteria']
                : [] as $klicKriteria => $kriterium) {
                if (is_array($kriterium)) {
                    $kriteria[] = [
                        'klic'  => self::getKlicZPostu($klicKriteria, count($kriteria)),
                        'id'    => self::getIdZPostu($kriterium['id'] ?? null),
                        'nazev' => self::getNazevZPostu($kriterium['nazev'] ?? null),
                    ];
                }
            }
            $vysledek[] = [
                'klic'     => self::getKlicZPostu($klicKategorie, count($vysledek)),
                'id'       => self::getIdZPostu($jednaKategorie['id'] ?? null),
                'nazev'    => self::getNazevZPostu($jednaKategorie['nazev'] ?? null),
                'kriteria' => $kriteria,
            ];
        }

        return new self(
            is_string($skala)
                ? trim($skala)
                : '',
            $vysledek,
        );
    }

    /**
     * Šablona k uložení. Chyby hlásí podle názvů, čísla řádků editor nemá.
     *
     * @throws ChybaSablonyHodnoceni
     */
    public function getSablona(): SablonaHodnoceni
    {
        $chyby = [];
        $skala = null;
        try {
            $skala = ParserSablonyHodnoceni::rozeberSkalu($this->skala, '');
        } catch (ChybaSablonyHodnoceni $chybaSkaly) {
            array_push($chyby, ...$chybaSkaly->getChyby());
        }

        if ($this->kategorie === []) {
            $chyby[] = 'Přidej aspoň jednu kategorii s kritériem.';
        }
        $nazvyKategorii = [];
        $pocetKriterii = 0;
        foreach ($this->kategorie as $indexKategorie => $jednaKategorie) {
            $popisKategorie = $jednaKategorie['nazev'] === ''
                ? sprintf('%d. kategorie', $indexKategorie + 1)
                : sprintf('kategorie „%s“', $jednaKategorie['nazev']);
            if ($jednaKategorie['nazev'] === '') {
                $chyby[] = sprintf('Chybí název %d. kategorie.', $indexKategorie + 1);
            } elseif (mb_strlen($jednaKategorie['nazev']) > ParserSablonyHodnoceni::MAX_DELKA_NAZVU) {
                $chyby[] = sprintf(
                    'Název %d. kategorie je delší než %d znaků.',
                    $indexKategorie + 1,
                    ParserSablonyHodnoceni::MAX_DELKA_NAZVU,
                );
            } elseif (in_array(ParserSablonyHodnoceni::getKlicNazvu($jednaKategorie['nazev']), $nazvyKategorii, true)) {
                $chyby[] = sprintf('Kategorie „%s“ je tam dvakrát.', $jednaKategorie['nazev']);
            } else {
                $nazvyKategorii[] = ParserSablonyHodnoceni::getKlicNazvu($jednaKategorie['nazev']);
            }
            if ($jednaKategorie['kriteria'] === []) {
                $chyby[] = sprintf('%s nemá žádné kritérium.', self::sVelkym($popisKategorie));
            }
            $nazvyKriterii = [];
            foreach ($jednaKategorie['kriteria'] as $indexKriteria => $kriterium) {
                if ($kriterium['nazev'] === '') {
                    $chyby[] = sprintf('V %s chybí název %d. kritéria.', self::v($popisKategorie), $indexKriteria + 1);
                } elseif (mb_strlen($kriterium['nazev']) > ParserSablonyHodnoceni::MAX_DELKA_NAZVU) {
                    $chyby[] = sprintf(
                        'V %s je název %d. kritéria delší než %d znaků.',
                        self::v($popisKategorie),
                        $indexKriteria + 1,
                        ParserSablonyHodnoceni::MAX_DELKA_NAZVU,
                    );
                } elseif (in_array(ParserSablonyHodnoceni::getKlicNazvu($kriterium['nazev']), $nazvyKriterii, true)) {
                    $chyby[] = sprintf('V %s je dvakrát kritérium „%s“.', self::v($popisKategorie), $kriterium['nazev']);
                } else {
                    $nazvyKriterii[] = ParserSablonyHodnoceni::getKlicNazvu($kriterium['nazev']);
                }
            }
            $pocetKriterii += count($jednaKategorie['kriteria']);
        }
        if (count($this->kategorie) > ParserSablonyHodnoceni::MAX_KATEGORII) {
            $chyby[] = sprintf(
                'Kategorií je %d, nejvýš jde %d.',
                count($this->kategorie),
                ParserSablonyHodnoceni::MAX_KATEGORII,
            );
        }
        if ($pocetKriterii > ParserSablonyHodnoceni::MAX_KRITERII) {
            $chyby[] = sprintf('Kritérií je %d, nejvýš jde %d.', $pocetKriterii, ParserSablonyHodnoceni::MAX_KRITERII);
        }
        // bez škály je vždy i chyba, podmínka na ni je tu kvůli typu
        if ($chyby !== [] || $skala === null) {
            throw new ChybaSablonyHodnoceni($chyby);
        }

        return new SablonaHodnoceni(
            null,
            $skala,
            array_map(
                static fn (array $jednaKategorie): KategorieHodnoceni => new KategorieHodnoceni(
                    $jednaKategorie['id'],
                    $jednaKategorie['nazev'],
                    array_map(
                        static fn (array $kriterium): KriteriumHodnoceni => new KriteriumHodnoceni(
                            $kriterium['id'],
                            $kriterium['nazev'],
                        ),
                        $jednaKategorie['kriteria'],
                    ),
                ),
                $this->kategorie,
            ),
        );
    }

    private static function getKlic(?int $id, int $index): string
    {
        return $id === null
            ? 'n' . ($index + 1)
            : 'i' . $id;
    }

    private static function getKlicZPostu(int|string $klic, int $index): string
    {
        // jiné písmeno než klíče editoru, aby se s nimi nesrazil
        return preg_match('~^[a-z]\d{1,9}$~D', (string)$klic) === 1
            ? (string)$klic
            : 'x' . ($index + 1);
    }

    private static function getIdZPostu(mixed $id): ?int
    {
        return is_string($id) && preg_match('~^[1-9]\d{0,9}$~D', $id) === 1
            ? (int)$id
            : null;
    }

    private static function getNazevZPostu(mixed $nazev): string
    {
        return is_string($nazev)
            ? trim($nazev)
            : '';
    }

    /**
     * „kategorie „X““ → „kategorii „X““, „2. kategorie“ → „2. kategorii“: „V kategorii…“.
     */
    private static function v(string $popisKategorie): string
    {
        return preg_replace('~kategorie~u', 'kategorii', $popisKategorie, 1);
    }

    private static function sVelkym(string $text): string
    {
        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
    }
}
