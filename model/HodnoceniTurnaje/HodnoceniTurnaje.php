<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

use Gamecon\Aktivita\TypAktivity;
use Psr\Clock\ClockInterface;

/**
 * Hodnocení skupin (týmů) turnaje vypravěči, zatím pro turnaj D&D. Org turnaje zadá v editoru šablonu (škálu,
 * kategorie a kritéria, @see FormularKriterii, ze souboru @see SouborSablonyHodnoceni) jednu na ročník, vypravěč pak
 * u každé skupiny, kterou v kole vedl, vybere
 * u každého kritéria hodnotu ze škály. Výsledky sčítají body skupiny po kategoriích přes kola, která skupina hraje.
 *
 * Kolo je aktivita turnaje (`akce_seznam.turnaj_kolo`), skupina tým na ní (`akce_tym_akce`). Kdo kterou skupinu vedl,
 * se nikde neukládá (v kole D&D bývá jedna aktivita s několika vypravěči a stejným počtem týmů), proto smí vypravěč
 * hodnotit kteroukoli skupinu z aktivity, kterou organizuje. Hodnocení je jedno na skupinu a kolo a patří vypravěči
 * (`id_hodnotitele`): druhý vypravěč téhož kola ho nepřepíše ani neuvidí jeho hodnoty, org turnaje ano. Když ho
 * zadává org, vybere, za kterého vypravěče kola; kdo ho naposledy zadal, je v `id_zadal`.
 */
final class HodnoceniTurnaje
{
    /**
     * Při přeřazení kategorií a kritérií se pořadí nejdřív posune mimo stávající, aby se unikátní klíč (šablona nebo
     * kategorie, pořadí) nesrazil. Kategorií ani kritérií nebude přes @see ParserSablonyHodnoceni::MAX_KRITERII,
     * posunuté pořadí se tedy vejde do SMALLINT UNSIGNED.
     */
    private const int DOCASNY_POSUN_PORADI = 30000;

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
            SELECT id, skala, uzavreno
            FROM turnaj_hodnoceni_sablona
            WHERE rok = $0
                AND id_typu = $1
            SQL, [0 => $this->rok, 1 => $this->idTypuAktivity]);

        return $radek === []
            ? null
            : $this->vytvorSablonuZRadku($radek);
    }

    /**
     * Uloží kritéria z editoru. Kategorie a kritéria s id jsou uložená: změní se jim název a pořadí (kritérium smí
     * i do jiné kategorie) a hodnoty hodnocení u nich zůstanou. Bez id jsou nová, uložená, která v šabloně chybí, se
     * smažou. Co by smazalo, co vypravěči zadali, chce potvrzení (@see ZmenaKriteriiChcePotvrzeni):
     * - odebrané kritérium vezme s sebou svou hodnotu z každého hodnocení, hodnocení zůstanou úplná;
     * - nové kritérium nebo škála, ze které vypadne použitá hodnota, smaže všechna hodnocení: hodnocení má hodnotu ze
     *   škály u každého kritéria, jinak by se skupiny nesčítaly stejně.
     *
     * @param string|null $potvrzeni klíč z @see ZmenaKriteriiChcePotvrzeni::getKlicPotvrzeni()
     * @return int kolik hodnocení se smazalo
     * @throws ZmenaKriteriiChcePotvrzeni
     * @throws HodnoceniTurnajeException id, které uložená šablona nemá (formulář otevřený před změnou jiného orga)
     */
    public function ulozSablonu(SablonaHodnoceni $nova, int $idUlozil, ?string $potvrzeni = null): int
    {
        return $this->vTransakci(function (?SablonaHodnoceni $stara) use ($nova, $idUlozil, $potvrzeni): int {
            self::overId($stara, $nova);
            if ($stara === null) {
                dbQuery(<<<'SQL'
                    INSERT INTO turnaj_hodnoceni_sablona (rok, id_typu, skala, zdroj, nahrano, id_nahral)
                    VALUES ($0, $1, $2, $3, $4, $5)
                    SQL, [
                    0 => $this->rok,
                    1 => $this->idTypuAktivity,
                    2 => implode(' ', $nova->skala),
                    3 => $nova->getText(),
                    4 => $this->getTedDb(),
                    5 => $idUlozil,
                ]);
                $this->prepisKategorie((int)dbInsertId(), $nova);

                return 0;
            }

            $zmena = $this->getZmenaChticiPotvrzeni($stara, $nova);
            if ($zmena !== null && $zmena->getKlicPotvrzeni() !== $potvrzeni) {
                throw $zmena;
            }
            $smazano = 0;
            if ($zmena?->isSmazaniVsech()) {
                $smazano = $zmena->getPocetHodnoceni();
                dbQuery(<<<'SQL'
                    DELETE FROM turnaj_hodnoceni WHERE id_sablony = $0
                    SQL, [0 => $stara->id]);
            }
            $this->prepisKategorie((int)$stara->id, $nova);
            dbQuery(<<<'SQL'
                UPDATE turnaj_hodnoceni_sablona SET skala = $0, zdroj = $1, nahrano = $2, id_nahral = $3 WHERE id = $4
                SQL, [
                0 => implode(' ', $nova->skala),
                1 => $nova->getText(),
                2 => $this->getTedDb(),
                3 => $idUlozil,
                4 => $stara->id,
            ]);

            return $smazano;
        });
    }

    /**
     * Kritéria ze souboru dostanou id uložených se stejným názvem (bez ohledu na velikost písmen), ostatní zůstanou
     * nová. Editor pak ukáže, co je nové, a uložení zachová hodnoty u spárovaných:
     * - kategorie podle názvu, kritérium nejdřív podle názvu ve stejnojmenné kategorii;
     * - kritérium, které tam není, podle názvu v jiné kategorii, když je takové mezi zbylými uloženými jen jedno
     *   (přesun do jiné kategorie). „Kritérium 1“ ve více kategoriích tak zůstane nové.
     * Přejmenované v souboru je nové kritérium, přejmenovat bez ztráty hodnot jde jen v editoru.
     */
    public function sparujPodleNazvu(SablonaHodnoceni $zeSouboru): SablonaHodnoceni
    {
        $ulozenaKategoriePodleNazvu = [];
        /** @var array<int, string> $nazvyUlozenychKriterii id kritéria => klíč názvu */
        $nazvyUlozenychKriterii = [];
        foreach ($this->getSablona()->kategorie ?? [] as $ulozenaKategorie) {
            $ulozenaKategoriePodleNazvu[ParserSablonyHodnoceni::getKlicNazvu($ulozenaKategorie->nazev)] = $ulozenaKategorie;
            foreach ($ulozenaKategorie->kriteria as $ulozeneKriterium) {
                $nazvyUlozenychKriterii[(int)$ulozeneKriterium->id] = ParserSablonyHodnoceni::getKlicNazvu($ulozeneKriterium->nazev);
            }
        }

        // nejdřív ve stejnojmenné kategorii, ať přesun nesebere kritérium, které v ní zůstalo
        /** @var array<int, array<int, int>> $idKriterii index kategorie => index kritéria => id */
        $idKriterii = [];
        foreach ($zeSouboru->kategorie as $indexKategorie => $kategorie) {
            $ulozenaKategorie = $ulozenaKategoriePodleNazvu[ParserSablonyHodnoceni::getKlicNazvu($kategorie->nazev)] ?? null;
            foreach ($kategorie->kriteria as $indexKriteria => $kriterium) {
                foreach ($ulozenaKategorie->kriteria ?? [] as $ulozeneKriterium) {
                    $idUlozeneho = (int)$ulozeneKriterium->id;
                    if (isset($nazvyUlozenychKriterii[$idUlozeneho])
                        && $nazvyUlozenychKriterii[$idUlozeneho] === ParserSablonyHodnoceni::getKlicNazvu($kriterium->nazev)
                    ) {
                        $idKriterii[$indexKategorie][$indexKriteria] = $idUlozeneho;
                        unset($nazvyUlozenychKriterii[$idUlozeneho]);
                        break;
                    }
                }
            }
        }
        foreach ($zeSouboru->kategorie as $indexKategorie => $kategorie) {
            foreach ($kategorie->kriteria as $indexKriteria => $kriterium) {
                if (isset($idKriterii[$indexKategorie][$indexKriteria])) {
                    continue;
                }
                $stejnaJmena = array_keys($nazvyUlozenychKriterii, ParserSablonyHodnoceni::getKlicNazvu($kriterium->nazev), true);
                if (count($stejnaJmena) === 1) {
                    $idKriterii[$indexKategorie][$indexKriteria] = $stejnaJmena[0];
                    unset($nazvyUlozenychKriterii[$stejnaJmena[0]]);
                }
            }
        }

        return new SablonaHodnoceni(
            null,
            $zeSouboru->skala,
            array_map(
                static fn (KategorieHodnoceni $kategorie, int $indexKategorie): KategorieHodnoceni => new KategorieHodnoceni(
                    ($ulozenaKategoriePodleNazvu[ParserSablonyHodnoceni::getKlicNazvu($kategorie->nazev)] ?? null)?->id,
                    $kategorie->nazev,
                    array_map(
                        static fn (KriteriumHodnoceni $kriterium, int $indexKriteria): KriteriumHodnoceni => new KriteriumHodnoceni(
                            $idKriterii[$indexKategorie][$indexKriteria] ?? null,
                            $kriterium->nazev,
                        ),
                        $kategorie->kriteria,
                        array_keys($kategorie->kriteria),
                    ),
                ),
                $zeSouboru->kategorie,
                array_keys($zeSouboru->kategorie),
            ),
        );
    }

    /**
     * Uzavřené hodnocení vypravěči už nezmění, org turnaje ano.
     */
    public function setUzavreno(bool $uzavreno): void
    {
        $this->vTransakci(function (?SablonaHodnoceni $sablona) use ($uzavreno): void {
            if ($sablona === null) {
                throw new HodnoceniTurnajeException('Kritéria hodnocení ještě nejsou zadaná.');
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
     * Kola turnaje (aktivity) se skupinami, které na nich hrají, seřazená podle začátku.
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
                'nazev' => self::getNazevTymu($radekTymu['nazev'], (int)$radekTymu['id']),
            ];
        }

        return array_map(fn (array $radek): array => [
            'idAkce'       => (int)$radek['id_akce'],
            'kolo'         => (int)$radek['turnaj_kolo'],
            'nazev'        => (string)$radek['nazev_akce'],
            'nazevTurnaje' => (string)$radek['nazev_turnaje'],
            'zacatek'      => $this->getCasZDb($radek['zacatek']),
            'tymy'         => $tymyPodleAktivity[(int)$radek['id_akce']] ?? [],
        ], $radky);
    }

    /**
     * Vypravěči kola (organizátoři aktivity), za které smí org turnaje zadat nové hodnocení.
     *
     * @return list<array{id: int, jmeno: string}>
     */
    public function getVypraveciKola(int $idAkce): array
    {
        return array_map(static fn (array $radek): array => [
            'id'    => (int)$radek['id_uzivatele'],
            'jmeno' => (string)\Uzivatel::jmenoNickZjisti($radek),
        ], dbFetchAll(<<<'SQL'
            SELECT uzivatele_hodnoty.id_uzivatele, uzivatele_hodnoty.login_uzivatele,
                uzivatele_hodnoty.jmeno_uzivatele, uzivatele_hodnoty.prijmeni_uzivatele
            FROM akce_organizatori
            JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = akce_organizatori.id_uzivatele
            WHERE akce_organizatori.id_akce = $0
            ORDER BY uzivatele_hodnoty.prijmeni_uzivatele, uzivatele_hodnoty.jmeno_uzivatele,
                uzivatele_hodnoty.id_uzivatele
            SQL, [0 => $idAkce]));
    }

    /**
     * @return array{id: int, idAkce: ?int, idHodnotitele: ?int, hodnotitel: ?string, idZadal: ?int, zadal: ?string, upraveno: \DateTimeImmutable, hodnoty: array<int, int>}|null
     *     hodnoty podle id kritéria
     */
    public function getHodnoceni(int $idTymu, int $kolo): ?array
    {
        $radek = dbOneLine(<<<'SQL'
            SELECT turnaj_hodnoceni.id, turnaj_hodnoceni.id_akce, turnaj_hodnoceni.id_hodnotitele,
                turnaj_hodnoceni.id_zadal, turnaj_hodnoceni.upraveno,
                hodnotitel.login_uzivatele AS login_hodnotitele, hodnotitel.jmeno_uzivatele AS jmeno_hodnotitele,
                hodnotitel.prijmeni_uzivatele AS prijmeni_hodnotitele,
                zadal.login_uzivatele AS login_zadal, zadal.jmeno_uzivatele AS jmeno_zadal,
                zadal.prijmeni_uzivatele AS prijmeni_zadal
            FROM turnaj_hodnoceni
            JOIN turnaj_hodnoceni_sablona ON turnaj_hodnoceni_sablona.id = turnaj_hodnoceni.id_sablony
            LEFT JOIN uzivatele_hodnoty AS hodnotitel ON hodnotitel.id_uzivatele = turnaj_hodnoceni.id_hodnotitele
            LEFT JOIN uzivatele_hodnoty AS zadal ON zadal.id_uzivatele = turnaj_hodnoceni.id_zadal
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
            'idAkce'        => self::getIdNeboNull($radek['id_akce']),
            'idHodnotitele' => self::getIdNeboNull($radek['id_hodnotitele']),
            'hodnotitel'    => self::getJmeno($radek, 'hodnotitele'),
            'idZadal'       => self::getIdNeboNull($radek['id_zadal']),
            'zadal'         => self::getJmeno($radek, 'zadal'),
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
     * dokud hodnocení není uzavřené, a jen když hodnocení není cizí. Org turnaje smí vždy: nové hodnocení zadává za
     * vypravěče kola (`$zaVypravece`), u stávajícího zůstane hodnotitel, jaký byl.
     *
     * @param array<int|string, mixed> $hodnoty hodnota podle id kritéria, jak přišla z formuláře
     * @param int                      $idZadal kdo hodnocení zadává (vypravěč, nebo org turnaje)
     * @param int|null                 $zaVypravece za kterého vypravěče kola zadává org nové hodnocení
     * @throws HodnoceniTurnajeException
     */
    public function ulozHodnoceni(
        int $idAkce,
        int $idTymu,
        array $hodnoty,
        int $idZadal,
        bool $jakoOrg,
        ?int $zaVypravece = null,
    ): void {
        $this->vTransakci(function (?SablonaHodnoceni $sablona) use (
            $idAkce,
            $idTymu,
            $hodnoty,
            $idZadal,
            $jakoOrg,
            $zaVypravece,
        ): void {
            $duvodZakazu = $this->getDuvodZakazuSablony($sablona, $idAkce, $idTymu, $idZadal, $jakoOrg);
            if ($duvodZakazu !== null || $sablona === null) {
                throw new HodnoceniTurnajeException($duvodZakazu ?? 'Kritéria hodnocení ještě nejsou zadaná.');
            }
            $hodnotyKriterii = $this->overHodnoty($sablona, $hodnoty);
            $kolo = (int)$this->getKoloAktivity($idAkce)['turnaj_kolo'];
            $stavajici = $this->getHodnoceni($idTymu, $kolo);

            if ($stavajici === null) {
                $idHodnotitele = $idZadal;
                if ($jakoOrg) {
                    if ($zaVypravece === null || ! $this->isOrganizator($idAkce, $zaVypravece)) {
                        throw new HodnoceniTurnajeException(
                            'Vyber vypravěče kola, za kterého hodnocení zadáváš: hodnocení patří jemu.',
                        );
                    }
                    $idHodnotitele = $zaVypravece;
                }
                dbQuery(<<<'SQL'
                    INSERT INTO turnaj_hodnoceni
                        (id_sablony, id_tymu, kolo, id_akce, id_hodnotitele, id_zadal, vytvoreno, upraveno)
                    VALUES ($0, $1, $2, $3, $4, $5, $6, $6)
                    SQL, [
                    0 => $sablona->id,
                    1 => $idTymu,
                    2 => $kolo,
                    3 => $idAkce,
                    4 => $idHodnotitele,
                    5 => $idZadal,
                    6 => $this->getTedDb(),
                ]);
                $idHodnoceni = (int)dbInsertId();
            } else {
                $idHodnoceni = $stavajici['id'];
                dbQuery(<<<'SQL'
                    UPDATE turnaj_hodnoceni SET id_akce = $0, id_zadal = $1, upraveno = $2 WHERE id = $3
                    SQL, [0 => $idAkce, 1 => $idZadal, 2 => $this->getTedDb(), 3 => $idHodnoceni]);
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
     * Skupiny turnaje s body po kategoriích a celkem, seřazené od nejlepší. Počítají se jen hodnocení kol, která
     * skupina hraje (`akce_tym_akce`); hodnocení kola, ve kterém už není, zůstane jen v přehledu hodnocení. Pořadí je
     * podle celkového součtu, stejný součet dělí místo (2.–3.). Skupina, které chybí hodnocení některého kola, je
     * neúplná, ale pořadí dostane taky.
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
        $tymy = $this->getTymyTurnaje();
        $kategoriePodleKriteria = [];
        $nulovaSoucty = [];
        foreach ($sablona->kategorie as $kategorie) {
            $nulovaSoucty[(int)$kategorie->id] = 0;
            foreach ($kategorie->kriteria as $kriterium) {
                $kategoriePodleKriteria[(int)$kriterium->id] = (int)$kategorie->id;
            }
        }

        $soucty = [];
        $hodnocenoKol = [];
        foreach ($this->getHodnoceniSablony($sablona, $tymy) as $hodnoceni) {
            if (! $hodnoceni['hrajeKolo']) {
                continue;
            }
            $idTymu = $hodnoceni['idTymu'];
            $soucty[$idTymu] ??= $nulovaSoucty;
            foreach ($hodnoceni['hodnoty'] as $idKriteria => $hodnota) {
                $soucty[$idTymu][$kategoriePodleKriteria[$idKriteria]] += $hodnota;
            }
            $hodnocenoKol[$idTymu] = ($hodnocenoKol[$idTymu] ?? 0) + 1;
        }

        $vysledky = [];
        foreach ($tymy as $idTymu => $tym) {
            $souctyKategorii = $soucty[$idTymu] ?? $nulovaSoucty;
            $kol = count($tym['kola']);
            $hodnoceno = $hodnocenoKol[$idTymu] ?? 0;
            $vysledky[] = [
                'idTymu'          => $idTymu,
                'nazevTymu'       => $tym['nazev'],
                'nazevTurnaje'    => $tym['nazevTurnaje'],
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
     * Všechna hodnocení ročníku, po bězích, skupinách a kolech, i hodnocení kol, která skupina už nehraje
     * (`hrajeKolo` false, do výsledků se nepočítají).
     *
     * @return list<array{id: int, idTymu: int, idAkce: ?int, nazevTymu: string, nazevTurnaje: string, kolo: int, hrajeKolo: bool, idHodnotitele: ?int, hodnotitel: ?string, idZadal: ?int, zadal: ?string, upraveno: \DateTimeImmutable, hodnoty: array<int, int>, celkem: int}>
     *     idAkce je aktivita, na které skupina kolo teď hraje; hodnoty podle id kritéria
     */
    public function getVsechnaHodnoceni(): array
    {
        $sablona = $this->getSablona();

        return $sablona === null
            ? []
            : $this->getHodnoceniSablony($sablona, $this->getTymyTurnaje());
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
     * „Jana“, nebo „Jana (zadal(a) org Petr)“, když hodnocení za vypravěče naposledy zadal někdo jiný.
     */
    public static function getPopisHodnotitele(?int $idHodnotitele, ?string $hodnotitel, ?int $idZadal, ?string $zadal): string
    {
        $popis = $hodnotitel ?? 'někdo, kdo už nemá účet';
        if ($idZadal !== null && $idZadal !== $idHodnotitele) {
            $popis .= ' (zadal(a) org ' . ($zadal ?? '') . ')';
        }

        return $popis;
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
     * Všechna hodnocení pro export: hodnocení na řádek, hodnota u každého kritéria. Hodnocení kola, které skupina už
     * nehraje, má poznámku a do výsledků se nepočítá.
     *
     * @return array{hlavicky: list<string>, radky: list<list<int|string>>}
     */
    public function getExportHodnoceni(): array
    {
        $sablona = $this->getSablona();
        $hlavicky = ['Běh', 'Skupina', 'Kolo', 'Hodnotil', 'Zadal', 'Upraveno'];
        $idKriterii = [];
        foreach ($sablona->kategorie ?? [] as $kategorie) {
            foreach ($kategorie->kriteria as $kriterium) {
                $hlavicky[] = "{$kategorie->nazev}: {$kriterium->nazev}";
                $idKriterii[] = (int)$kriterium->id;
            }
        }
        array_push($hlavicky, 'Celkem', 'Poznámka');

        $radky = [];
        foreach ($this->getVsechnaHodnoceni() as $hodnoceni) {
            $radky[] = [
                $hodnoceni['nazevTurnaje'],
                $hodnoceni['nazevTymu'],
                $hodnoceni['kolo'],
                $hodnoceni['hodnotitel'] ?? '',
                $hodnoceni['zadal'] ?? '',
                $hodnoceni['upraveno']->format('Y-m-d H:i'),
                ...array_map(static fn (int $idKriteria): int|string => $hodnoceni['hodnoty'][$idKriteria] ?? '', $idKriterii),
                $hodnoceni['celkem'],
                $hodnoceni['hrajeKolo']
                    ? ''
                    : 'skupina už v kole není, do výsledků se nepočítá',
            ];
        }

        return ['hlavicky' => $hlavicky, 'radky' => $radky];
    }

    /**
     * @param array<int, array{nazev: string, nazevTurnaje: string, kola: array<int, int>}> $tymy z @see getTymyTurnaje()
     * @return list<array{id: int, idTymu: int, idAkce: ?int, nazevTymu: string, nazevTurnaje: string, kolo: int, hrajeKolo: bool, idHodnotitele: ?int, hodnotitel: ?string, idZadal: ?int, zadal: ?string, upraveno: \DateTimeImmutable, hodnoty: array<int, int>, celkem: int}>
     */
    private function getHodnoceniSablony(SablonaHodnoceni $sablona, array $tymy): array
    {
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

        $vsechna = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT turnaj_hodnoceni.id, turnaj_hodnoceni.id_tymu, turnaj_hodnoceni.kolo, turnaj_hodnoceni.upraveno,
                turnaj_hodnoceni.id_hodnotitele, turnaj_hodnoceni.id_zadal, akce_tym.nazev AS nazev_tymu,
                turnaje.nazev AS nazev_turnaje,
                hodnotitel.login_uzivatele AS login_hodnotitele, hodnotitel.jmeno_uzivatele AS jmeno_hodnotitele,
                hodnotitel.prijmeni_uzivatele AS prijmeni_hodnotitele,
                zadal.login_uzivatele AS login_zadal, zadal.jmeno_uzivatele AS jmeno_zadal,
                zadal.prijmeni_uzivatele AS prijmeni_zadal
            FROM turnaj_hodnoceni
            JOIN akce_tym ON akce_tym.id = turnaj_hodnoceni.id_tymu
            LEFT JOIN akce_seznam ON akce_seznam.id_akce = turnaj_hodnoceni.id_akce
            LEFT JOIN turnaje ON turnaje.id_turnaje = akce_seznam.id_turnaje
            LEFT JOIN uzivatele_hodnoty AS hodnotitel ON hodnotitel.id_uzivatele = turnaj_hodnoceni.id_hodnotitele
            LEFT JOIN uzivatele_hodnoty AS zadal ON zadal.id_uzivatele = turnaj_hodnoceni.id_zadal
            WHERE turnaj_hodnoceni.id_sablony = $0
            SQL, [0 => $sablona->id]) as $radek) {
            $idTymu = (int)$radek['id_tymu'];
            $kolo = (int)$radek['kolo'];
            $hodnoty = $hodnotyPodleHodnoceni[(int)$radek['id']] ?? [];
            $idAkce = $tymy[$idTymu]['kola'][$kolo] ?? null;
            $vsechna[] = [
                'id'            => (int)$radek['id'],
                'idTymu'        => $idTymu,
                'idAkce'        => $idAkce,
                'nazevTymu'     => self::getNazevTymu($radek['nazev_tymu'], $idTymu),
                // běh podle toho, kde skupina hraje; když už nehraje nikde, podle aktivity hodnocení
                'nazevTurnaje'  => $tymy[$idTymu]['nazevTurnaje'] ?? (string)($radek['nazev_turnaje'] ?? ''),
                'kolo'          => $kolo,
                'hrajeKolo'     => $idAkce !== null,
                'idHodnotitele' => self::getIdNeboNull($radek['id_hodnotitele']),
                'hodnotitel'    => self::getJmeno($radek, 'hodnotitele'),
                'idZadal'       => self::getIdNeboNull($radek['id_zadal']),
                'zadal'         => self::getJmeno($radek, 'zadal'),
                'upraveno'      => new \DateTimeImmutable($radek['upraveno']),
                'hodnoty'       => $hodnoty,
                'celkem'        => array_sum($hodnoty),
            ];
        }
        usort($vsechna, static fn (array $a, array $b): int => [$a['nazevTurnaje'], $a['nazevTymu'], $a['idTymu'], $a['kolo']]
                                                             <=> [$b['nazevTurnaje'], $b['nazevTymu'], $b['idTymu'], $b['kolo']]);

        return $vsechna;
    }

    /**
     * Skupiny turnaje ročníku s koly, která teď hrají.
     *
     * @return array<int, array{nazev: string, nazevTurnaje: string, kola: array<int, int>}> podle id týmu;
     *     kola: číslo kola => id aktivity, na které ho skupina hraje
     */
    private function getTymyTurnaje(): array
    {
        $tymy = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT akce_tym.id, akce_tym.nazev, akce_seznam.id_akce, akce_seznam.turnaj_kolo,
                turnaje.nazev AS nazev_turnaje
            FROM akce_tym
            JOIN akce_tym_akce ON akce_tym_akce.id_tymu = akce_tym.id
            JOIN akce_seznam ON akce_seznam.id_akce = akce_tym_akce.id_akce
            JOIN turnaje ON turnaje.id_turnaje = akce_seznam.id_turnaje
            WHERE akce_seznam.rok = $0
                AND akce_seznam.typ = $1
                AND akce_seznam.turnaj_kolo IS NOT NULL
            ORDER BY akce_tym.id, turnaje.nazev, akce_seznam.turnaj_kolo, akce_seznam.id_akce
            SQL, [0 => $this->rok, 1 => $this->idTypuAktivity]) as $radek) {
            $idTymu = (int)$radek['id'];
            // první řádek týmu má abecedně první běh, jako dřív MIN(turnaje.nazev)
            $tymy[$idTymu] ??= [
                'nazev'        => self::getNazevTymu($radek['nazev'], $idTymu),
                'nazevTurnaje' => (string)$radek['nazev_turnaje'],
                'kola'         => [],
            ];
            $tymy[$idTymu]['kola'][(int)$radek['turnaj_kolo']] ??= (int)$radek['id_akce'];
        }

        return $tymy;
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
            return 'Kritéria hodnocení ještě nejsou zadaná, hodnotit zatím nejde.';
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
        $zacatek = $this->getCasZDb($aktivita['zacatek']);
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
                self::getNazevTymu($tym['nazev'], $idTymu),
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

    /**
     * Id z editoru musí patřit uložené šabloně a každé smí jen jednou. Jinak je formulář z doby před tím, než kritéria
     * změnil někdo jiný (nebo stejná stránka v jiném okně).
     */
    private static function overId(?SablonaHodnoceni $stara, SablonaHodnoceni $nova): void
    {
        $idKategorii = array_map(static fn (KategorieHodnoceni $kategorie): ?int => $kategorie->id, $stara->kategorie ?? []);
        $idKriterii = array_keys($stara?->getKriteriaPodleId() ?? []);
        $pouzita = [];
        foreach ($nova->kategorie as $kategorie) {
            $ids = [['kategorie', $kategorie->id, $idKategorii]];
            foreach ($kategorie->kriteria as $kriterium) {
                $ids[] = ['kriterium', $kriterium->id, $idKriterii];
            }
            foreach ($ids as [$co, $id, $ulozena]) {
                if ($id === null) {
                    continue;
                }
                if (isset($pouzita[$co][$id]) || ! in_array($id, $ulozena, true)) {
                    throw new HodnoceniTurnajeException(
                        'Kritéria mezitím uložil někdo jiný, nebo jsou otevřená v jiném okně. Načti stránku znovu'
                        . ' a změny udělej na aktuálních kritériích.',
                    );
                }
                $pouzita[$co][$id] = true;
            }
        }
    }

    /**
     * Co by uložení smazalo z hodnocení, nebo null, když nic. Klíč potvrzení nese jen to, co se smaže (všechna
     * hodnocení a kolik jich je, nebo která kritéria), takže přejmenování ani přeřazení potvrzení nezruší.
     */
    private function getZmenaChticiPotvrzeni(SablonaHodnoceni $stara, SablonaHodnoceni $nova): ?ZmenaKriteriiChcePotvrzeni
    {
        $pocetHodnoceni = (int)dbOneCol(<<<'SQL'
            SELECT COUNT(*) FROM turnaj_hodnoceni WHERE id_sablony = $0
            SQL, [0 => $stara->id]);
        if ($pocetHodnoceni === 0) {
            return null;
        }

        $novaKriteria = [];
        foreach ($nova->kategorie as $kategorie) {
            foreach ($kategorie->kriteria as $kriterium) {
                if ($kriterium->id === null) {
                    $novaKriteria[] = sprintf('„%s“ (%s)', $kriterium->nazev, $kategorie->nazev);
                }
            }
        }
        $ponechana = array_keys($nova->getKriteriaPodleId());
        $odebrana = [];
        foreach ($stara->kategorie as $kategorie) {
            foreach ($kategorie->kriteria as $kriterium) {
                if (! in_array($kriterium->id, $ponechana, true)) {
                    $odebrana[(int)$kriterium->id] = sprintf('„%s“ (%s)', $kriterium->nazev, $kategorie->nazev);
                }
            }
        }
        $hodnotyMimoSkalu = array_map('intval', dbFetchColumn(<<<'SQL'
            SELECT DISTINCT turnaj_hodnoceni_hodnota.hodnota
            FROM turnaj_hodnoceni_hodnota
            JOIN turnaj_hodnoceni ON turnaj_hodnoceni.id = turnaj_hodnoceni_hodnota.id_hodnoceni
            WHERE turnaj_hodnoceni.id_sablony = $0
                AND turnaj_hodnoceni_hodnota.id_kriteria IN ($1)
                AND turnaj_hodnoceni_hodnota.hodnota NOT IN ($2)
            ORDER BY turnaj_hodnoceni_hodnota.hodnota
            SQL, [0 => $stara->id, 1 => $ponechana, 2 => $nova->skala]));

        $smazaniVsech = $novaKriteria !== [] || $hodnotyMimoSkalu !== [];
        if (! $smazaniVsech && $odebrana === []) {
            return null;
        }
        $podrobnosti = [];
        if ($novaKriteria !== []) {
            $podrobnosti[] = sprintf(
                'Přibude %s %s. Hodnocení k %s nemají hodnotu, proto se smažou všechna.',
                count($novaKriteria) === 1
                    ? 'kritérium'
                    : 'kritéria',
                implode(', ', $novaKriteria),
                count($novaKriteria) === 1
                    ? 'němu'
                    : 'nim',
            );
        }
        if ($hodnotyMimoSkalu !== []) {
            $podrobnosti[] = sprintf(
                'V nové škále chybí použité %s %s, proto se smažou všechna hodnocení.',
                count($hodnotyMimoSkalu) === 1
                    ? 'hodnota'
                    : 'hodnoty',
                implode(', ', $hodnotyMimoSkalu),
            );
        }
        if ($odebrana !== []) {
            $podrobnosti[] = sprintf(
                'Odebíráš %s %s, %s hodnoty %s.',
                count($odebrana) === 1
                    ? 'kritérium'
                    : 'kritéria',
                implode(', ', $odebrana),
                count($odebrana) === 1
                    ? 'jeho'
                    : 'jejich',
                $smazaniVsech
                    ? 'zmizí se všemi hodnoceními'
                    : 'se z hodnocení smažou, ostatní hodnoty zůstanou',
            );
        }
        $idOdebranych = array_keys($odebrana);
        sort($idOdebranych);

        return new ZmenaKriteriiChcePotvrzeni(
            $smazaniVsech,
            $pocetHodnoceni,
            $podrobnosti,
            hash('sha256', json_encode(
                $smazaniVsech
                    ? ['vse', $pocetHodnoceni]
                    : ['kriteria', $pocetHodnoceni, $idOdebranych],
                JSON_THROW_ON_ERROR,
            )),
        );
    }

    /**
     * Zapíše kategorie a kritéria šablony podle id (@see ulozSablonu()). Odebraná kritéria vezmou s sebou své hodnoty
     * (ON DELETE CASCADE), odebrané kategorie kritéria, která v nich zůstala.
     */
    private function prepisKategorie(int $idSablony, SablonaHodnoceni $nova): void
    {
        dbQuery(<<<'SQL'
            UPDATE turnaj_hodnoceni_kategorie SET poradi = poradi + $1 WHERE id_sablony = $0
            SQL, [0 => $idSablony, 1 => self::DOCASNY_POSUN_PORADI]);
        dbQuery(<<<'SQL'
            UPDATE turnaj_hodnoceni_kriterium
            JOIN turnaj_hodnoceni_kategorie ON turnaj_hodnoceni_kategorie.id = turnaj_hodnoceni_kriterium.id_kategorie
            SET turnaj_hodnoceni_kriterium.poradi = turnaj_hodnoceni_kriterium.poradi + $1
            WHERE turnaj_hodnoceni_kategorie.id_sablony = $0
            SQL, [0 => $idSablony, 1 => self::DOCASNY_POSUN_PORADI]);

        $idKategorii = [];
        $idKriterii = [];
        foreach ($nova->kategorie as $indexKategorie => $kategorie) {
            if ($kategorie->id === null) {
                dbQuery(<<<'SQL'
                    INSERT INTO turnaj_hodnoceni_kategorie (id_sablony, poradi, nazev) VALUES ($0, $1, $2)
                    SQL, [0 => $idSablony, 1 => $indexKategorie + 1, 2 => $kategorie->nazev]);
                $idKategorie = (int)dbInsertId();
            } else {
                dbQuery(<<<'SQL'
                    UPDATE turnaj_hodnoceni_kategorie SET poradi = $0, nazev = $1 WHERE id = $2 AND id_sablony = $3
                    SQL, [0 => $indexKategorie + 1, 1 => $kategorie->nazev, 2 => $kategorie->id, 3 => $idSablony]);
                $idKategorie = $kategorie->id;
            }
            $idKategorii[] = $idKategorie;
            foreach ($kategorie->kriteria as $indexKriteria => $kriterium) {
                if ($kriterium->id === null) {
                    dbQuery(<<<'SQL'
                        INSERT INTO turnaj_hodnoceni_kriterium (id_kategorie, poradi, nazev) VALUES ($0, $1, $2)
                        SQL, [0 => $idKategorie, 1 => $indexKriteria + 1, 2 => $kriterium->nazev]);
                    $idKriterii[] = (int)dbInsertId();
                } else {
                    dbQuery(<<<'SQL'
                        UPDATE turnaj_hodnoceni_kriterium SET id_kategorie = $0, poradi = $1, nazev = $2 WHERE id = $3
                        SQL, [0 => $idKategorie, 1 => $indexKriteria + 1, 2 => $kriterium->nazev, 3 => $kriterium->id]);
                    $idKriterii[] = $kriterium->id;
                }
            }
        }

        dbQuery(<<<'SQL'
            DELETE turnaj_hodnoceni_kriterium
            FROM turnaj_hodnoceni_kriterium
            JOIN turnaj_hodnoceni_kategorie ON turnaj_hodnoceni_kategorie.id = turnaj_hodnoceni_kriterium.id_kategorie
            WHERE turnaj_hodnoceni_kategorie.id_sablony = $0
                AND turnaj_hodnoceni_kriterium.id NOT IN ($1)
            SQL, [0 => $idSablony, 1 => $idKriterii]);
        dbQuery(<<<'SQL'
            DELETE FROM turnaj_hodnoceni_kategorie WHERE id_sablony = $0 AND id NOT IN ($1)
            SQL, [0 => $idSablony, 1 => $idKategorii]);
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
                SELECT id, skala, uzavreno
                FROM turnaj_hodnoceni_sablona
                WHERE rok = $0
                    AND id_typu = $1
                FOR UPDATE
                SQL, [0 => $this->rok, 1 => $this->idTypuAktivity]);
            $vysledek = $zmena($radek === []
                ? null
                : $this->vytvorSablonuZRadku($radek));
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
    private function vytvorSablonuZRadku(array $radek): SablonaHodnoceni
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
            $this->getCasZDb($radek['uzavreno']),
        );
    }

    private function getCasZDb(?string $cas): ?\DateTimeImmutable
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
    private static function getNazevTymu(?string $nazev, int $idTymu): string
    {
        return $nazev !== null && trim($nazev) !== ''
            ? $nazev
            : 'Tým č. ' . $idTymu;
    }

    private static function getIdNeboNull(mixed $id): ?int
    {
        return $id === null
            ? null
            : (int)$id;
    }

    /**
     * @param array<string, mixed> $radek s login_{kdo}, jmeno_{kdo} a prijmeni_{kdo}
     */
    private static function getJmeno(array $radek, string $kdo): ?string
    {
        return $radek["login_{$kdo}"] === null
            ? null
            : \Uzivatel::jmenoNickZjisti([
                'login_uzivatele'    => $radek["login_{$kdo}"],
                'jmeno_uzivatele'    => $radek["jmeno_{$kdo}"],
                'prijmeni_uzivatele' => $radek["prijmeni_{$kdo}"],
            ]);
    }
}
