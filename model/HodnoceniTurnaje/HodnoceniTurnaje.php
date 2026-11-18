<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

use Gamecon\Aktivita\TypAktivity;
use Psr\Clock\ClockInterface;

/**
 * Hodnocení skupin (týmů) turnaje vypravěči, zatím pro turnaj D&D. Org turnaje nahraje šablonu (škálu, kategorie
 * a kritéria, @see ParserSablonyHodnoceni) jednu na ročník, vypravěč pak u každé skupiny, kterou v kole vedl, vybere
 * u každého kritéria hodnotu ze škály. Výsledky sčítají body skupiny po kategoriích přes všechna kola.
 *
 * Kolo je aktivita turnaje (`akce_seznam.turnaj_kolo`), skupina tým na ní (`akce_tym_akce`). Kdo kterou skupinu vedl,
 * se nikde neukládá (v kole D&D bývá jedna aktivita s několika vypravěči a stejným počtem týmů), proto smí vypravěč
 * hodnotit kteroukoli skupinu z aktivity, kterou organizuje. Hodnocení je jedno na skupinu a kolo: druhý vypravěč
 * téhož kola cizí hodnocení nepřepíše, org turnaje ano.
 */
final class HodnoceniTurnaje
{
    public function __construct(
        private readonly int $rok,
        private readonly ClockInterface $hodiny,
        private readonly int $idTypuAktivity = TypAktivity::DND,
    ) {
    }

    public function getRok(): int
    {
        return $this->rok;
    }

    public function getSablona(): ?SablonaHodnoceni
    {
        $radek = dbOneLine(<<<'SQL'
            SELECT id, skala, zdroj, uzavreno
            FROM turnaj_hodnoceni_sablona
            WHERE rok = $0
                AND id_typu = $1
            SQL, [0 => $this->rok, 1 => $this->idTypuAktivity]);

        return $radek === []
            ? null
            : $this->sablonaZRadku($radek);
    }

    /**
     * Nahraje kritéria z textu souboru. Šablona se stejným tvarem (škála a počty kritérií v kategoriích) jen přejmenuje
     * kategorie a kritéria, hodnocení zůstanou. Jiný tvar nahradí šablonu celou; když už existují hodnocení, jen se
     * svolením je smazat.
     *
     * @return int kolik hodnocení se smazalo
     * @throws ChybaSablonyHodnoceni soubor nejde přečíst
     * @throws HodnoceniTurnajeException jiný tvar a hodnocení bez svolení je smazat
     */
    public function nahrajSablonu(string $text, int $idNahral, bool $smazatHodnoceni = false): int
    {
        $nova = ParserSablonyHodnoceni::rozeber($text);

        return $this->vTransakci(function (?SablonaHodnoceni $stara) use ($nova, $idNahral, $smazatHodnoceni): int {
            if ($stara === null) {
                dbQuery(<<<'SQL'
                    INSERT INTO turnaj_hodnoceni_sablona (rok, id_typu, skala, zdroj, nahrano, id_nahral)
                    VALUES ($0, $1, $2, $3, $4, $5)
                    SQL, [
                    0 => $this->rok,
                    1 => $this->idTypuAktivity,
                    2 => implode(' ', $nova->skala),
                    3 => $nova->zdroj,
                    4 => $this->getTedDb(),
                    5 => $idNahral,
                ]);
                $this->vlozKategorie((int)dbInsertId(), $nova);

                return 0;
            }

            if ($stara->isStejnyTvar($nova)) {
                $this->prejmenuj($stara, $nova);
                $this->ulozZdroj($stara, $nova, $idNahral);

                return 0;
            }

            $pocetHodnoceni = $this->getPocetHodnoceni();
            if ($pocetHodnoceni > 0 && ! $smazatHodnoceni) {
                throw new HodnoceniTurnajeException(sprintf(
                    'Nová kritéria mají jinou škálu nebo jiný počet kategorií či kritérií a už je uloženo %d hodnocení.'
                    . ' Nahrát je jde jen se smazáním všech hodnocení.',
                    $pocetHodnoceni,
                ));
            }
            // hodnoty hodnocení zmizí s kritérii, řádky hodnocení ne
            dbQuery(<<<'SQL'
                DELETE FROM turnaj_hodnoceni WHERE id_sablony = $0
                SQL, [0 => $stara->id]);
            dbQuery(<<<'SQL'
                DELETE FROM turnaj_hodnoceni_kategorie WHERE id_sablony = $0
                SQL, [0 => $stara->id]);
            $this->vlozKategorie((int)$stara->id, $nova);
            $this->ulozZdroj($stara, $nova, $idNahral);

            return $pocetHodnoceni;
        });
    }

    /**
     * Uzavřené hodnocení vypravěči už nezmění, org turnaje ano.
     */
    public function setUzavreno(bool $uzavreno): void
    {
        $this->vTransakci(function (?SablonaHodnoceni $sablona) use ($uzavreno): void {
            if ($sablona === null) {
                throw new HodnoceniTurnajeException('Kritéria hodnocení ještě nejsou nahraná.');
            }
            dbQuery(<<<'SQL'
                UPDATE turnaj_hodnoceni_sablona SET uzavreno = $0 WHERE id = $1
                SQL, [
                0 => $uzavreno
                    ? $this->getTedDb()
                    : null,
                1 => $sablona->id,
            ]);
        });
    }

    public function getPocetHodnoceni(): int
    {
        return (int)dbOneCol(<<<'SQL'
            SELECT COUNT(*)
            FROM turnaj_hodnoceni
            JOIN turnaj_hodnoceni_sablona ON turnaj_hodnoceni_sablona.id = turnaj_hodnoceni.id_sablony
            WHERE turnaj_hodnoceni_sablona.rok = $0
                AND turnaj_hodnoceni_sablona.id_typu = $1
            SQL, [0 => $this->rok, 1 => $this->idTypuAktivity]);
    }

    /**
     * Kola turnaje (aktivity) se skupinami, které na nich hrály, seřazená podle začátku.
     *
     * @param int|null $idVypravece jen kola, která vede; null všechna (org turnaje)
     * @return list<array{idAkce: int, kolo: int, nazev: string, nazevTurnaje: string, zacatek: ?\DateTimeImmutable, tymy: list<array{id: int, nazev: string}>}>
     */
    public function getKola(?int $idVypravece): array
    {
        $radky = dbFetchAll(<<<'SQL'
            SELECT akce_seznam.id_akce, akce_seznam.nazev_akce, akce_seznam.turnaj_kolo, akce_seznam.zacatek,
                turnaje.nazev AS nazev_turnaje
            FROM akce_seznam
            JOIN turnaje ON turnaje.id_turnaje = akce_seznam.id_turnaje
            WHERE akce_seznam.rok = $0
                AND akce_seznam.typ = $1
                AND akce_seznam.turnaj_kolo IS NOT NULL
                AND (
                    $2 IS NULL
                    OR EXISTS (
                        SELECT 1
                        FROM akce_organizatori
                        WHERE akce_organizatori.id_akce = akce_seznam.id_akce
                            AND akce_organizatori.id_uzivatele = $2
                    )
                )
            ORDER BY akce_seznam.zacatek IS NULL, akce_seznam.zacatek, turnaje.nazev, akce_seznam.turnaj_kolo,
                akce_seznam.id_akce
            SQL, [0 => $this->rok, 1 => $this->idTypuAktivity, 2 => $idVypravece]);
        if ($radky === []) {
            return [];
        }

        $tymyPodleAktivity = [];
        $radkyTymu = dbFetchAll(<<<'SQL'
            SELECT akce_tym_akce.id_akce, akce_tym.id, akce_tym.nazev
            FROM akce_tym_akce
            JOIN akce_tym ON akce_tym.id = akce_tym_akce.id_tymu
            WHERE akce_tym_akce.id_akce IN ($0)
            ORDER BY akce_tym.nazev IS NULL, akce_tym.nazev, akce_tym.id
            SQL, [0 => array_map(static fn (array $radek): int => (int)$radek['id_akce'], $radky)]);
        foreach ($radkyTymu as $radekTymu) {
            $tymyPodleAktivity[(int)$radekTymu['id_akce']][] = [
                'id'    => (int)$radekTymu['id'],
                'nazev' => self::nazevTymu($radekTymu['nazev'], (int)$radekTymu['id']),
            ];
        }

        return array_map(fn (array $radek): array => [
            'idAkce'       => (int)$radek['id_akce'],
            'kolo'         => (int)$radek['turnaj_kolo'],
            'nazev'        => (string)$radek['nazev_akce'],
            'nazevTurnaje' => (string)$radek['nazev_turnaje'],
            'zacatek'      => $this->casZDb($radek['zacatek']),
            'tymy'         => $tymyPodleAktivity[(int)$radek['id_akce']] ?? [],
        ], $radky);
    }

    /**
     * @return array{id: int, idAkce: int, idHodnotitele: ?int, hodnotitel: ?string, upraveno: \DateTimeImmutable, hodnoty: array<int, int>}|null
     *     hodnoty podle id kritéria
     */
    public function getHodnoceni(int $idTymu, int $kolo): ?array
    {
        $radek = dbOneLine(<<<'SQL'
            SELECT turnaj_hodnoceni.id, turnaj_hodnoceni.id_akce, turnaj_hodnoceni.id_hodnotitele,
                turnaj_hodnoceni.upraveno, uzivatele_hodnoty.login_uzivatele, uzivatele_hodnoty.jmeno_uzivatele,
                uzivatele_hodnoty.prijmeni_uzivatele
            FROM turnaj_hodnoceni
            JOIN turnaj_hodnoceni_sablona ON turnaj_hodnoceni_sablona.id = turnaj_hodnoceni.id_sablony
            LEFT JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = turnaj_hodnoceni.id_hodnotitele
            WHERE turnaj_hodnoceni_sablona.rok = $0
                AND turnaj_hodnoceni_sablona.id_typu = $1
                AND turnaj_hodnoceni.id_tymu = $2
                AND turnaj_hodnoceni.kolo = $3
            SQL, [0 => $this->rok, 1 => $this->idTypuAktivity, 2 => $idTymu, 3 => $kolo]);
        if ($radek === []) {
            return null;
        }

        return [
            'id'            => (int)$radek['id'],
            'idAkce'        => (int)$radek['id_akce'],
            'idHodnotitele' => $radek['id_hodnotitele'] === null
                ? null
                : (int)$radek['id_hodnotitele'],
            'hodnotitel'    => self::jmeno($radek),
            'upraveno'      => new \DateTimeImmutable($radek['upraveno']),
            'hodnoty'       => array_map('intval', dbFetchPairs(<<<'SQL'
                SELECT id_kriteria, hodnota FROM turnaj_hodnoceni_hodnota WHERE id_hodnoceni = $0
                SQL, [0 => $radek['id']])),
        ];
    }

    /**
     * Proč uživatel nesmí uložit hodnocení skupiny v kole (aktivitě), nebo null, když smí. Podle toho stránka zamkne
     * formulář a totéž kontroluje @see ulozHodnoceni() pod zámkem.
     */
    public function getDuvodZakazu(int $idAkce, int $idTymu, int $idUzivatele, bool $jakoOrg): ?string
    {
        return $this->getDuvodZakazuSablony($this->getSablona(), $idAkce, $idTymu, $idUzivatele, $jakoOrg);
    }

    /**
     * Uloží nebo přepíše hodnocení skupiny v kole. Vypravěč smí jen skupinu z aktivity, kterou vede, od začátku kola,
     * dokud hodnocení není uzavřené, a jen když skupinu v tom kole nehodnotil někdo jiný. Org turnaje smí vždy;
     * když přepisuje cizí hodnocení, zůstane u něj původní hodnotitel.
     *
     * @param array<int|string, mixed> $hodnoty hodnota podle id kritéria, jak přišla z formuláře
     * @throws HodnoceniTurnajeException
     */
    public function ulozHodnoceni(int $idAkce, int $idTymu, array $hodnoty, int $idHodnotitele, bool $jakoOrg): void
    {
        $this->vTransakci(function (?SablonaHodnoceni $sablona) use (
            $idAkce,
            $idTymu,
            $hodnoty,
            $idHodnotitele,
            $jakoOrg,
        ): void {
            $duvodZakazu = $this->getDuvodZakazuSablony($sablona, $idAkce, $idTymu, $idHodnotitele, $jakoOrg);
            if ($duvodZakazu !== null || $sablona === null) {
                throw new HodnoceniTurnajeException($duvodZakazu ?? 'Kritéria hodnocení ještě nejsou nahraná.');
            }
            $hodnotyKriterii = $this->overHodnoty($sablona, $hodnoty);
            $kolo = (int)$this->getKoloAktivity($idAkce)['turnaj_kolo'];
            $stavajici = $this->getHodnoceni($idTymu, $kolo);

            if ($stavajici === null) {
                dbQuery(<<<'SQL'
                    INSERT INTO turnaj_hodnoceni (id_sablony, id_tymu, kolo, id_akce, id_hodnotitele, vytvoreno, upraveno)
                    VALUES ($0, $1, $2, $3, $4, $5, $5)
                    SQL, [
                    0 => $sablona->id,
                    1 => $idTymu,
                    2 => $kolo,
                    3 => $idAkce,
                    4 => $idHodnotitele,
                    5 => $this->getTedDb(),
                ]);
                $idHodnoceni = (int)dbInsertId();
            } else {
                $idHodnoceni = $stavajici['id'];
                dbQuery(<<<'SQL'
                    UPDATE turnaj_hodnoceni SET id_akce = $0, upraveno = $1 WHERE id = $2
                    SQL, [0 => $idAkce, 1 => $this->getTedDb(), 2 => $idHodnoceni]);
                dbQuery(<<<'SQL'
                    DELETE FROM turnaj_hodnoceni_hodnota WHERE id_hodnoceni = $0
                    SQL, [0 => $idHodnoceni]);
            }
            foreach ($hodnotyKriterii as $idKriteria => $hodnota) {
                dbQuery(<<<'SQL'
                    INSERT INTO turnaj_hodnoceni_hodnota (id_hodnoceni, id_kriteria, hodnota) VALUES ($0, $1, $2)
                    SQL, [0 => $idHodnoceni, 1 => $idKriteria, 2 => $hodnota]);
            }
        });
    }

    /**
     * @throws HodnoceniTurnajeException hodnocení v tomhle ročníku není
     */
    public function smazHodnoceni(int $idHodnoceni): void
    {
        $this->vTransakci(function (?SablonaHodnoceni $sablona) use ($idHodnoceni): void {
            $smazano = dbNumRows(dbQuery(<<<'SQL'
                DELETE FROM turnaj_hodnoceni WHERE id = $0 AND id_sablony = $1
                SQL, [0 => $idHodnoceni, 1 => $sablona?->id]));
            if ($smazano === 0) {
                throw new HodnoceniTurnajeException('Tohle hodnocení už neexistuje.');
            }
        });
    }

    /**
     * Skupiny turnaje s body po kategoriích a celkem, seřazené od nejlepší. Pořadí je podle celkového součtu, stejný
     * součet dělí místo (2.–3.). Skupina, které chybí hodnocení některého kola, je neúplná, ale pořadí dostane taky.
     *
     * @return list<array{idTymu: int, nazevTymu: string, nazevTurnaje: string, souctyKategorii: array<int, int>, celkem: int, hodnocenoKol: int, kol: int, uplna: bool, poradiOd: int, poradiDo: int}>
     *     součty podle id kategorie
     */
    public function getVysledky(): array
    {
        $sablona = $this->getSablona();
        if ($sablona === null) {
            return [];
        }

        $soucty = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT turnaj_hodnoceni.id_tymu, turnaj_hodnoceni_kriterium.id_kategorie,
                SUM(turnaj_hodnoceni_hodnota.hodnota) AS soucet
            FROM turnaj_hodnoceni
            JOIN turnaj_hodnoceni_hodnota ON turnaj_hodnoceni_hodnota.id_hodnoceni = turnaj_hodnoceni.id
            JOIN turnaj_hodnoceni_kriterium ON turnaj_hodnoceni_kriterium.id = turnaj_hodnoceni_hodnota.id_kriteria
            WHERE turnaj_hodnoceni.id_sablony = $0
            GROUP BY turnaj_hodnoceni.id_tymu, turnaj_hodnoceni_kriterium.id_kategorie
            SQL, [0 => $sablona->id]) as $radek) {
            $soucty[(int)$radek['id_tymu']][(int)$radek['id_kategorie']] = (int)$radek['soucet'];
        }
        $hodnocenoKol = array_map('intval', dbFetchPairs(<<<'SQL'
            SELECT id_tymu, COUNT(*) FROM turnaj_hodnoceni WHERE id_sablony = $0 GROUP BY id_tymu
            SQL, [0 => $sablona->id]));

        $vysledky = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT akce_tym.id, akce_tym.nazev, MIN(turnaje.nazev) AS nazev_turnaje,
                COUNT(DISTINCT akce_seznam.turnaj_kolo) AS kol
            FROM akce_tym
            JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id
            JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce
            JOIN turnaje ON turnaje.id_turnaje = akce_seznam.id_turnaje
            WHERE akce_seznam.rok = $0
                AND akce_seznam.typ = $1
                AND akce_seznam.turnaj_kolo IS NOT NULL
            GROUP BY akce_tym.id, akce_tym.nazev
            SQL, [0 => $this->rok, 1 => $this->idTypuAktivity]) as $radek) {
            $idTymu = (int)$radek['id'];
            $souctyKategorii = [];
            foreach ($sablona->kategorie as $kategorie) {
                $souctyKategorii[(int)$kategorie->id] = $soucty[$idTymu][(int)$kategorie->id] ?? 0;
            }
            $kol = (int)$radek['kol'];
            $hodnoceno = $hodnocenoKol[$idTymu] ?? 0;
            $vysledky[] = [
                'idTymu'          => $idTymu,
                'nazevTymu'       => self::nazevTymu($radek['nazev'], $idTymu),
                'nazevTurnaje'    => (string)$radek['nazev_turnaje'],
                'souctyKategorii' => $souctyKategorii,
                'celkem'          => array_sum($souctyKategorii),
                'hodnocenoKol'    => $hodnoceno,
                'kol'             => $kol,
                'uplna'           => $hodnoceno >= $kol,
                'poradiOd'        => 0,
                'poradiDo'        => 0,
            ];
        }

        usort($vysledky, static fn (array $a, array $b): int => [$b['celkem'], $a['nazevTymu'], $a['idTymu']]
                                                               <=> [$a['celkem'], $b['nazevTymu'], $b['idTymu']]);
        $pocetPodleCelku = array_count_values(array_column($vysledky, 'celkem'));
        $poradiOd = 1;
        foreach ($vysledky as $index => $vysledek) {
            if ($index > 0 && $vysledky[$index - 1]['celkem'] !== $vysledek['celkem']) {
                $poradiOd = $index + 1;
            }
            $vysledky[$index]['poradiOd'] = $poradiOd;
            $vysledky[$index]['poradiDo'] = $poradiOd + $pocetPodleCelku[$vysledek['celkem']] - 1;
        }

        return $vysledky;
    }

    /**
     * Všechna hodnocení ročníku, po bězích, skupinách a kolech.
     *
     * @return list<array{id: int, idTymu: int, idAkce: int, nazevTymu: string, nazevTurnaje: string, kolo: int, hodnotitel: ?string, upraveno: \DateTimeImmutable, hodnoty: array<int, int>, celkem: int}>
     *     hodnoty podle id kritéria
     */
    public function getVsechnaHodnoceni(): array
    {
        $sablona = $this->getSablona();
        if ($sablona === null) {
            return [];
        }

        $hodnotyPodleHodnoceni = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT turnaj_hodnoceni_hodnota.id_hodnoceni, turnaj_hodnoceni_hodnota.id_kriteria,
                turnaj_hodnoceni_hodnota.hodnota
            FROM turnaj_hodnoceni_hodnota
            JOIN turnaj_hodnoceni ON turnaj_hodnoceni.id = turnaj_hodnoceni_hodnota.id_hodnoceni
            WHERE turnaj_hodnoceni.id_sablony = $0
            SQL, [0 => $sablona->id]) as $radek) {
            $hodnotyPodleHodnoceni[(int)$radek['id_hodnoceni']][(int)$radek['id_kriteria']] = (int)$radek['hodnota'];
        }

        return array_map(static function (array $radek) use ($hodnotyPodleHodnoceni): array {
            $hodnoty = $hodnotyPodleHodnoceni[(int)$radek['id']] ?? [];

            return [
                'id'           => (int)$radek['id'],
                'idTymu'       => (int)$radek['id_tymu'],
                'idAkce'       => (int)$radek['id_akce'],
                'nazevTymu'    => self::nazevTymu($radek['nazev_tymu'], (int)$radek['id_tymu']),
                'nazevTurnaje' => (string)$radek['nazev_turnaje'],
                'kolo'         => (int)$radek['kolo'],
                'hodnotitel'   => self::jmeno($radek),
                'upraveno'     => new \DateTimeImmutable($radek['upraveno']),
                'hodnoty'      => $hodnoty,
                'celkem'       => array_sum($hodnoty),
            ];
        }, dbFetchAll(<<<'SQL'
            SELECT turnaj_hodnoceni.id, turnaj_hodnoceni.id_tymu, turnaj_hodnoceni.id_akce, turnaj_hodnoceni.kolo,
                turnaj_hodnoceni.upraveno, akce_tym.nazev AS nazev_tymu, turnaje.nazev AS nazev_turnaje,
                uzivatele_hodnoty.login_uzivatele, uzivatele_hodnoty.jmeno_uzivatele, uzivatele_hodnoty.prijmeni_uzivatele
            FROM turnaj_hodnoceni
            JOIN akce_tym ON akce_tym.id = turnaj_hodnoceni.id_tymu
            JOIN akce_seznam ON akce_seznam.id_akce = turnaj_hodnoceni.id_akce
            LEFT JOIN turnaje ON turnaje.id_turnaje = akce_seznam.id_turnaje
            LEFT JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = turnaj_hodnoceni.id_hodnotitele
            WHERE turnaj_hodnoceni.id_sablony = $0
            ORDER BY turnaje.nazev, akce_tym.nazev IS NULL, akce_tym.nazev, akce_tym.id, turnaj_hodnoceni.kolo
            SQL, [0 => $sablona->id]));
    }

    /**
     * „1.“, nebo „2.–3.“ pro sdílené místo.
     */
    public static function getPopisPoradi(int $poradiOd, int $poradiDo): string
    {
        return $poradiOd === $poradiDo
            ? "{$poradiOd}."
            : "{$poradiOd}.–{$poradiDo}.";
    }

    /**
     * Výsledky pro export: skupina na řádek, body po kategoriích a celkem.
     *
     * @return array{hlavicky: list<string>, radky: list<list<int|string>>}
     */
    public function getExportVysledku(): array
    {
        $sablona = $this->getSablona();
        $hlavicky = ['Pořadí', 'Skupina', 'Běh'];
        foreach ($sablona->kategorie ?? [] as $kategorie) {
            $hlavicky[] = $kategorie->nazev;
        }
        array_push($hlavicky, 'Celkem', 'Ohodnocených kol', 'Kol', 'Úplná');

        $radky = [];
        foreach ($this->getVysledky() as $vysledek) {
            $radky[] = [
                self::getPopisPoradi($vysledek['poradiOd'], $vysledek['poradiDo']),
                $vysledek['nazevTymu'],
                $vysledek['nazevTurnaje'],
                ...array_values($vysledek['souctyKategorii']),
                $vysledek['celkem'],
                $vysledek['hodnocenoKol'],
                $vysledek['kol'],
                $vysledek['uplna']
                    ? 'ano'
                    : 'ne',
            ];
        }

        return ['hlavicky' => $hlavicky, 'radky' => $radky];
    }

    /**
     * Všechna hodnocení pro export: hodnocení na řádek, hodnota u každého kritéria.
     *
     * @return array{hlavicky: list<string>, radky: list<list<int|string>>}
     */
    public function getExportHodnoceni(): array
    {
        $sablona = $this->getSablona();
        $hlavicky = ['Běh', 'Skupina', 'Kolo', 'Hodnotil', 'Upraveno'];
        $idKriterii = [];
        foreach ($sablona->kategorie ?? [] as $kategorie) {
            foreach ($kategorie->kriteria as $kriterium) {
                $hlavicky[] = "{$kategorie->nazev}: {$kriterium->nazev}";
                $idKriterii[] = (int)$kriterium->id;
            }
        }
        $hlavicky[] = 'Celkem';

        $radky = [];
        foreach ($this->getVsechnaHodnoceni() as $hodnoceni) {
            $radky[] = [
                $hodnoceni['nazevTurnaje'],
                $hodnoceni['nazevTymu'],
                $hodnoceni['kolo'],
                $hodnoceni['hodnotitel'] ?? '',
                $hodnoceni['upraveno']->format('Y-m-d H:i'),
                ...array_map(static fn (int $idKriteria): int|string => $hodnoceni['hodnoty'][$idKriteria] ?? '', $idKriterii),
                $hodnoceni['celkem'],
            ];
        }

        return ['hlavicky' => $hlavicky, 'radky' => $radky];
    }

    /**
     * Hodnota ze škály u každého kritéria šablony.
     *
     * @param array<int|string, mixed> $hodnoty
     * @return array<int, int> hodnota podle id kritéria
     */
    private function overHodnoty(SablonaHodnoceni $sablona, array $hodnoty): array
    {
        $overene = [];
        $chybi = [];
        foreach ($sablona->kategorie as $kategorie) {
            foreach ($kategorie->kriteria as $kriterium) {
                $hodnota = $hodnoty[$kriterium->id] ?? null;
                if (! is_scalar($hodnota)
                    || preg_match('~^-?\d+$~D', trim((string)$hodnota)) !== 1
                    || ! $sablona->isVeSkale((int)$hodnota)
                ) {
                    $chybi[] = "{$kategorie->nazev}: {$kriterium->nazev}";
                    continue;
                }
                $overene[(int)$kriterium->id] = (int)$hodnota;
            }
        }
        if ($chybi !== []) {
            throw new HodnoceniTurnajeException(
                'Vyber hodnotu ze škály u každého kritéria. Chybí u: ' . implode(', ', $chybi) . '.',
            );
        }

        return $overene;
    }

    private function getDuvodZakazuSablony(
        ?SablonaHodnoceni $sablona,
        int $idAkce,
        int $idTymu,
        int $idUzivatele,
        bool $jakoOrg,
    ): ?string {
        if ($sablona === null) {
            return 'Kritéria hodnocení ještě nejsou nahraná, hodnotit zatím nejde.';
        }
        if (! $jakoOrg && $sablona->isUzavrena()) {
            return 'Hodnocení je uzavřené. Změnu domluv s orgem turnaje.';
        }
        $aktivita = $this->getKoloAktivity($idAkce);
        if ($aktivita === []) {
            return 'Tohle není kolo turnaje z letošního ročníku.';
        }
        $tym = dbOneLine(<<<'SQL'
            SELECT akce_tym.nazev
            FROM akce_tym
            JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id
            WHERE akce_tym.id = $0
                AND akce_tym_akce.id_akce = $1
            SQL, [0 => $idTymu, 1 => $idAkce]);
        if ($tym === []) {
            return 'Tahle skupina v tomhle kole nehrála.';
        }
        if ($jakoOrg) {
            return null;
        }

        if (! $this->isOrganizator($idAkce, $idUzivatele)) {
            return 'Tohle kolo nevedeš, hodnotit ho můžou jen jeho vypravěči.';
        }
        $zacatek = $this->casZDb($aktivita['zacatek']);
        if ($zacatek === null) {
            return 'Kolo ještě nemá čas začátku, hodnotit jde až od jeho začátku.';
        }
        if ($zacatek > $this->hodiny->now()) {
            return sprintf('Kolo ještě nezačalo, hodnotit jde od %s.', $zacatek->format('j. n. G:i'));
        }
        $kolo = (int)$aktivita['turnaj_kolo'];
        $stavajici = $this->getHodnoceni($idTymu, $kolo);
        if ($stavajici !== null && $stavajici['idHodnotitele'] !== $idUzivatele) {
            return sprintf(
                'Skupinu „%s“ v %d. kole už hodnotil%s %s. Jestli je to omyl, ozvi se orgovi turnaje.',
                self::nazevTymu($tym['nazev'], $idTymu),
                $kolo,
                $stavajici['hodnotitel'] === null
                    ? ''
                    : '(a)',
                $stavajici['hodnotitel'] ?? 'někdo jiný',
            );
        }

        return null;
    }

    /**
     * @return array{turnaj_kolo: string, zacatek: ?string}|array{} prázdné, když aktivita není kolo turnaje ročníku
     */
    private function getKoloAktivity(int $idAkce): array
    {
        return dbOneLine(<<<'SQL'
            SELECT akce_seznam.turnaj_kolo, akce_seznam.zacatek
            FROM akce_seznam
            WHERE akce_seznam.id_akce = $0
                AND akce_seznam.rok = $1
                AND akce_seznam.typ = $2
                AND akce_seznam.id_turnaje IS NOT NULL
                AND akce_seznam.turnaj_kolo IS NOT NULL
            SQL, [0 => $idAkce, 1 => $this->rok, 2 => $this->idTypuAktivity]);
    }

    private function isOrganizator(int $idAkce, int $idUzivatele): bool
    {
        return dbOneCol(<<<'SQL'
            SELECT 1 FROM akce_organizatori WHERE id_akce = $0 AND id_uzivatele = $1
            SQL, [0 => $idAkce, 1 => $idUzivatele]) !== null;
    }

    private function vlozKategorie(int $idSablony, SablonaHodnoceni $sablona): void
    {
        foreach ($sablona->kategorie as $poradiKategorie => $kategorie) {
            dbQuery(<<<'SQL'
                INSERT INTO turnaj_hodnoceni_kategorie (id_sablony, poradi, nazev) VALUES ($0, $1, $2)
                SQL, [0 => $idSablony, 1 => $poradiKategorie + 1, 2 => $kategorie->nazev]);
            $idKategorie = (int)dbInsertId();
            foreach ($kategorie->kriteria as $poradiKriteria => $kriterium) {
                dbQuery(<<<'SQL'
                    INSERT INTO turnaj_hodnoceni_kriterium (id_kategorie, poradi, nazev) VALUES ($0, $1, $2)
                    SQL, [0 => $idKategorie, 1 => $poradiKriteria + 1, 2 => $kriterium->nazev]);
            }
        }
    }

    /**
     * Stejný tvar: kategorie a kritéria si nechají id (a tím hodnocení), změní se jen názvy.
     */
    private function prejmenuj(SablonaHodnoceni $stara, SablonaHodnoceni $nova): void
    {
        foreach ($stara->kategorie as $indexKategorie => $kategorie) {
            $novaKategorie = $nova->kategorie[$indexKategorie];
            dbQuery(<<<'SQL'
                UPDATE turnaj_hodnoceni_kategorie SET nazev = $0 WHERE id = $1
                SQL, [0 => $novaKategorie->nazev, 1 => $kategorie->id]);
            foreach ($kategorie->kriteria as $indexKriteria => $kriterium) {
                dbQuery(<<<'SQL'
                    UPDATE turnaj_hodnoceni_kriterium SET nazev = $0 WHERE id = $1
                    SQL, [0 => $novaKategorie->kriteria[$indexKriteria]->nazev, 1 => $kriterium->id]);
            }
        }
    }

    private function ulozZdroj(SablonaHodnoceni $stara, SablonaHodnoceni $nova, int $idNahral): void
    {
        dbQuery(<<<'SQL'
            UPDATE turnaj_hodnoceni_sablona SET skala = $0, zdroj = $1, nahrano = $2, id_nahral = $3 WHERE id = $4
            SQL, [
            0 => implode(' ', $nova->skala),
            1 => $nova->zdroj,
            2 => $this->getTedDb(),
            3 => $idNahral,
            4 => $stara->id,
        ]);
    }

    /**
     * Změny jednoho ročníku jdou po sobě pod zámkem řádku šablony: dva vypravěči, kteří naráz hodnotí tutéž skupinu
     * v tomtéž kole, se tak nepřepíšou, druhý dostane hlášku, že už je ohodnocená.
     *
     * @template T
     * @param callable(?SablonaHodnoceni): T $zmena dostane šablonu načtenou pod zámkem, nebo null, když ještě není
     * @return T
     */
    private function vTransakci(callable $zmena): mixed
    {
        dbBegin();
        try {
            $radek = dbOneLine(<<<'SQL'
                SELECT id, skala, zdroj, uzavreno
                FROM turnaj_hodnoceni_sablona
                WHERE rok = $0
                    AND id_typu = $1
                FOR UPDATE
                SQL, [0 => $this->rok, 1 => $this->idTypuAktivity]);
            $vysledek = $zmena($radek === []
                ? null
                : $this->sablonaZRadku($radek));
            dbCommit();

            return $vysledek;
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }
    }

    /**
     * @param array<string, mixed> $radek z turnaj_hodnoceni_sablona
     */
    private function sablonaZRadku(array $radek): SablonaHodnoceni
    {
        /** @var array<int, array{nazev: string, kriteria: list<KriteriumHodnoceni>}> $kategorie */
        $kategorie = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT turnaj_hodnoceni_kategorie.id AS id_kategorie, turnaj_hodnoceni_kategorie.nazev AS nazev_kategorie,
                turnaj_hodnoceni_kriterium.id AS id_kriteria, turnaj_hodnoceni_kriterium.nazev AS nazev_kriteria
            FROM turnaj_hodnoceni_kategorie
            JOIN turnaj_hodnoceni_kriterium ON turnaj_hodnoceni_kriterium.id_kategorie = turnaj_hodnoceni_kategorie.id
            WHERE turnaj_hodnoceni_kategorie.id_sablony = $0
            ORDER BY turnaj_hodnoceni_kategorie.poradi, turnaj_hodnoceni_kriterium.poradi
            SQL, [0 => $radek['id']]) as $radekKriteria) {
            $idKategorie = (int)$radekKriteria['id_kategorie'];
            $kategorie[$idKategorie] ??= ['nazev' => (string)$radekKriteria['nazev_kategorie'], 'kriteria' => []];
            $kategorie[$idKategorie]['kriteria'][] = new KriteriumHodnoceni(
                (int)$radekKriteria['id_kriteria'],
                (string)$radekKriteria['nazev_kriteria'],
            );
        }

        return new SablonaHodnoceni(
            (int)$radek['id'],
            array_map('intval', explode(' ', (string)$radek['skala'])),
            array_map(
                static fn (int $idKategorie, array $jednaKategorie): KategorieHodnoceni => new KategorieHodnoceni(
                    $idKategorie,
                    $jednaKategorie['nazev'],
                    $jednaKategorie['kriteria'],
                ),
                array_keys($kategorie),
                $kategorie,
            ),
            (string)$radek['zdroj'],
            $this->casZDb($radek['uzavreno']),
        );
    }

    private function casZDb(?string $cas): ?\DateTimeImmutable
    {
        return $cas === null
            ? null
            : new \DateTimeImmutable($cas, $this->hodiny->now()->getTimezone());
    }

    private function getTedDb(): string
    {
        return $this->hodiny->now()->format('Y-m-d H:i:s');
    }

    /**
     * Název skupiny, jak ho zadal kapitán. Bez názvu číslo týmu, ne jeho kód: kód pouští do nezamčeného týmu.
     */
    private static function nazevTymu(?string $nazev, int $idTymu): string
    {
        return $nazev !== null && trim($nazev) !== ''
            ? $nazev
            : 'Tým č. ' . $idTymu;
    }

    /**
     * @param array<string, mixed> $radek s login_uzivatele, jmeno_uzivatele a prijmeni_uzivatele
     */
    private static function jmeno(array $radek): ?string
    {
        return $radek['login_uzivatele'] === null
            ? null
            : \Uzivatel::jmenoNickZjisti($radek);
    }
}
