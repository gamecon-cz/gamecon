<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

/**
 * Administration of the lecture archive: list, detail, saving with checks, pairing with a card of the program.
 *
 * @see admin/scripts/modules/web/archiv-prednasek.php
 */
final class SpravaArchivu
{
    private readonly ArchivPrednasekRepository $repository;

    public function __construct(
        private readonly int $letosniRocnik,
    ) {
        $this->repository = new ArchivPrednasekRepository();
    }

    /**
     * @param array{rok?: int|null, sparovana?: bool|null, skryta?: bool|null, zaznam?: bool|null, hledat?: string} $filtr
     *
     * @return list<array{id: int, nazev: string, rok: int, youtubeId: string|null, skryta: bool, idAkce: int|null,
     *     nazevAkce: string|null, rokAkce: int|null, prednasejici: string}>
     */
    public function seznam(array $filtr): array
    {
        $hledat = trim((string) ($filtr['hledat'] ?? ''));
        $radky = dbFetchAll(<<<'SQL'
            SELECT archiv_prednasek.id_prednasky, archiv_prednasek.nazev, archiv_prednasek.rok, archiv_prednasek.youtube_id,
                archiv_prednasek.skryta, archiv_prednasek.id_akce, akce_seznam.nazev_akce, akce_seznam.rok AS rok_akce,
                (
                    SELECT GROUP_CONCAT(archiv_prednasek_prednasejici.jmeno ORDER BY archiv_prednasek_prednaska_prednasejici.poradi SEPARATOR ', ')
                    FROM archiv_prednasek_prednaska_prednasejici
                    JOIN archiv_prednasek_prednasejici
                        ON archiv_prednasek_prednasejici.id_prednasejiciho = archiv_prednasek_prednaska_prednasejici.id_prednasejiciho
                    WHERE archiv_prednasek_prednaska_prednasejici.id_prednasky = archiv_prednasek.id_prednasky
                ) AS prednasejici
            FROM archiv_prednasek
            LEFT JOIN akce_seznam ON akce_seznam.id_akce = archiv_prednasek.id_akce
            WHERE ($0 IS NULL OR archiv_prednasek.rok = $0)
                AND ($1 IS NULL OR (archiv_prednasek.id_akce IS NOT NULL) = $1)
                AND ($2 IS NULL OR archiv_prednasek.skryta = $2)
                AND ($3 IS NULL OR (archiv_prednasek.youtube_id IS NOT NULL) = $3)
                AND ($4 = '' OR archiv_prednasek.nazev LIKE $5 OR EXISTS (
                    SELECT 1
                    FROM archiv_prednasek_prednaska_prednasejici
                    JOIN archiv_prednasek_prednasejici
                        ON archiv_prednasek_prednasejici.id_prednasejiciho = archiv_prednasek_prednaska_prednasejici.id_prednasejiciho
                    WHERE archiv_prednasek_prednaska_prednasejici.id_prednasky = archiv_prednasek.id_prednasky
                        AND archiv_prednasek_prednasejici.jmeno LIKE $5
                ))
            ORDER BY archiv_prednasek.rok DESC, archiv_prednasek.nazev
            SQL, [
    0         => $filtr['rok'] ?? null,
            1 => self::bitNeboNull($filtr['sparovana'] ?? null),
            2 => self::bitNeboNull($filtr['skryta'] ?? null),
            3 => self::bitNeboNull($filtr['zaznam'] ?? null),
            4 => $hledat,
            5 => '%' . addcslashes($hledat, '%_\\') . '%',
]);

        return array_map(static fn (array $radek) => [
            'id'           => (int) $radek['id_prednasky'],
            'nazev'        => (string) $radek['nazev'],
            'rok'          => (int) $radek['rok'],
            'youtubeId'    => $radek['youtube_id'] ?: null,
            'skryta'       => (bool) $radek['skryta'],
            'idAkce'       => isset($radek['id_akce']) ? (int) $radek['id_akce'] : null,
            'nazevAkce'    => isset($radek['nazev_akce']) ? (string) $radek['nazev_akce'] : null,
            'rokAkce'      => isset($radek['rok_akce']) ? (int) $radek['rok_akce'] : null,
            'prednasejici' => (string) ($radek['prednasejici'] ?? ''),
        ], $radky);
    }

    /**
     * @return list<int> years in the archive, from the newest
     */
    public function roky(): array
    {
        return array_map('intval', dbOneArray(<<<'SQL'
            SELECT DISTINCT rok FROM archiv_prednasek ORDER BY rok DESC
            SQL));
    }

    /**
     * @return array{id: int, nazev: string, rok: int, youtubeId: string|null, skryta: bool, idAkce: int|null,
     *     prednasejici: list<array{jmeno: string, idUzivatele: int|null}>, klicovaSlova: list<string>}|null
     */
    public function detail(int $idPrednasky): ?array
    {
        $radek = dbOneLine(<<<'SQL'
            SELECT id_prednasky, nazev, rok, youtube_id, skryta, id_akce
            FROM archiv_prednasek
            WHERE id_prednasky = $0
            SQL, [
            0 => $idPrednasky,
        ]);
        if (! $radek) {
            return null;
        }
        $prednasejici = array_map(static fn (array $osoba) => [
            'jmeno'       => (string) $osoba['jmeno'],
            'idUzivatele' => isset($osoba['id_uzivatele']) ? (int) $osoba['id_uzivatele'] : null,
        ], dbFetchAll(<<<'SQL'
            SELECT archiv_prednasek_prednasejici.jmeno, archiv_prednasek_prednasejici.id_uzivatele
            FROM archiv_prednasek_prednaska_prednasejici
            JOIN archiv_prednasek_prednasejici
                ON archiv_prednasek_prednasejici.id_prednasejiciho = archiv_prednasek_prednaska_prednasejici.id_prednasejiciho
            WHERE archiv_prednasek_prednaska_prednasejici.id_prednasky = $0
            ORDER BY archiv_prednasek_prednaska_prednasejici.poradi
            SQL, [
            0 => $idPrednasky,
        ]));
        $klicovaSlova = dbOneArray(<<<'SQL'
            SELECT archiv_prednasek_klicove_slovo.nazev
            FROM archiv_prednasek_prednaska_klicove_slovo
            JOIN archiv_prednasek_klicove_slovo
                ON archiv_prednasek_klicove_slovo.id_klicoveho_slova = archiv_prednasek_prednaska_klicove_slovo.id_klicoveho_slova
            WHERE archiv_prednasek_prednaska_klicove_slovo.id_prednasky = $0
            ORDER BY archiv_prednasek_klicove_slovo.nazev
            SQL, [
            0 => $idPrednasky,
        ]);

        return [
            'id'           => (int) $radek['id_prednasky'],
            'nazev'        => (string) $radek['nazev'],
            'rok'          => (int) $radek['rok'],
            'youtubeId'    => $radek['youtube_id'] ?: null,
            'skryta'       => (bool) $radek['skryta'],
            'idAkce'       => isset($radek['id_akce']) ? (int) $radek['id_akce'] : null,
            'prednasejici' => $prednasejici,
            'klicovaSlova' => array_map('strval', $klicovaSlova),
        ];
    }

    /**
     * @return list<string> all keywords, for the whisperer
     */
    public function klicovaSlova(): array
    {
        return array_map('strval', dbOneArray(<<<'SQL'
            SELECT nazev FROM archiv_prednasek_klicove_slovo ORDER BY nazev
            SQL));
    }

    /**
     * @return list<string> names of all speakers, for the whisperer
     */
    public function jmenaPrednasejicich(): array
    {
        return array_map('strval', dbOneArray(<<<'SQL'
            SELECT DISTINCT jmeno FROM archiv_prednasek_prednasejici ORDER BY jmeno
            SQL));
    }

    /**
     * Checks and saves a lecture; a speaker of an existing name is the same speaker, his link to a user changes
     * for all his lectures, but only when the administrator changed it (menitUzivatele), so that adding a known
     * speaker with an empty user does not unlink him. Unused speakers and keywords are removed afterwards.
     *
     * @param list<array{jmeno: string, idUzivatele: int|null, menitUzivatele: bool}> $prednasejici
     * @param list<string>                                                            $klicovaSlova
     *
     * @return int id of the lecture
     *
     * @throws ChybaArchivu what the administrator has to fix
     */
    public function uloz(
        ?int $idPrednasky,
        string $nazev,
        string $rok,
        string $odkazNaYoutube,
        bool $skryta,
        ?int $idAkce,
        array $prednasejici,
        array $klicovaSlova,
    ): int {
        $nazev = NazevPrednasky::uprav($nazev);
        if ($nazev === '') {
            throw new ChybaArchivu('Přednáška musí mít název.');
        }
        if (mb_strlen($nazev) > 255) {
            throw new ChybaArchivu('Název může mít nejvýš 255 znaků.');
        }
        if (! preg_match('~^\d{4}$~', trim($rok)) || (int) $rok < 2000 || (int) $rok > $this->letosniRocnik) {
            throw new ChybaArchivu("Rok musí být mezi 2000 a {$this->letosniRocnik}.");
        }
        $rok = (int) $rok;
        $youtubeId = null;
        if (trim($odkazNaYoutube) !== '') {
            $youtubeId = YoutubeOdkaz::idZOdkazu($odkazNaYoutube)
                ?? throw new ChybaArchivu('Odkaz na YouTube nepoznávám, vlož adresu videa (youtu.be/…, youtube.com/watch?v=…) nebo jeho ID.');
        }
        $idHlavniAkce = $idAkce !== null
            ? $this->overenaAkceKSparovani($idAkce, $idPrednasky)
            : null;
        $stejnaPrednaska = dbOneCol(<<<'SQL'
            SELECT id_prednasky FROM archiv_prednasek WHERE rok = $0 AND nazev = $1 AND ($2 IS NULL OR id_prednasky != $2)
            SQL, [
            0 => $rok,
            1 => $nazev,
            2 => $idPrednasky,
        ]);
        if ($stejnaPrednaska) {
            throw new ChybaArchivu("Přednáška „{$nazev}“ z roku {$rok} už v archivu je.");
        }
        $prednasejici = $this->overeniPrednasejici($prednasejici);
        $klicovaSlova = self::bezDuplicit(array_map(static fn (string $slovo) => trim((string) preg_replace('~\s+~u', ' ', $slovo)), $klicovaSlova));
        foreach ($klicovaSlova as $slovo) {
            if (mb_strlen($slovo) > 255) {
                throw new ChybaArchivu("Klíčové slovo „{$slovo}“ je delší než 255 znaků.");
            }
        }

        dbBegin();
        try {
            if ($idPrednasky === null) {
                $idPrednasky = $this->repository->vlozPrednasku($nazev, $rok, $youtubeId, $idHlavniAkce, $skryta);
            } else {
                dbQuery(<<<'SQL'
                    UPDATE archiv_prednasek
                    SET nazev = $0, rok = $1, youtube_id = $2, skryta = $3, id_akce = $4
                    WHERE id_prednasky = $5
                    SQL, [
                    0 => $nazev,
                    1 => $rok,
                    2 => $youtubeId,
                    3 => (int) $skryta,
                    4 => $idHlavniAkce,
                    5 => $idPrednasky,
                ]);
            }
            $idsPrednasejicich = [];
            foreach ($prednasejici as ['jmeno' => $jmeno, 'idUzivatele' => $idUzivatele, 'menitUzivatele' => $menitUzivatele]) {
                $idsPrednasejicich[] = $this->idPrednasejiciho($jmeno, $idUzivatele, $menitUzivatele);
            }
            $this->repository->nastavPrednasejici($idPrednasky, $idsPrednasejicich);
            $this->repository->nastavKlicovaSlova($idPrednasky, array_map($this->repository->idKlicovehoSlova(...), $klicovaSlova));
            $this->uklidNepouzite();
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }

        return $idPrednasky;
    }

    public function nastavSkrytou(int $idPrednasky, bool $skryta): void
    {
        dbQuery(<<<'SQL'
            UPDATE archiv_prednasek SET skryta = $0 WHERE id_prednasky = $1
            SQL, [
            0 => (int) $skryta,
            1 => $idPrednasky,
        ]);
    }

    /**
     * Cards of the program for pairing: one item per card (its main activity), found by title, optionally in a year.
     *
     * @return list<array{value: int, label: string}> as the admin omnibox wants it
     */
    public function hledejAktivity(string $hledat, ?int $rok): array
    {
        $hledat = trim($hledat);
        if (mb_strlen($hledat) < 2) {
            return [];
        }
        $radky = dbFetchAll(<<<'SQL'
            SELECT COALESCE(akce_instance.id_hlavni_akce, akce_seznam.id_akce) AS id_karty,
                MIN(akce_seznam.nazev_akce) AS nazev, MIN(akce_seznam.rok) AS rok, MIN(akce_typy.typ_1p) AS typ,
                MAX(archiv_prednasek.nazev) AS v_archivu
            FROM akce_seznam
            LEFT JOIN akce_instance ON akce_instance.id_instance = akce_seznam.patri_pod
            LEFT JOIN akce_typy ON akce_typy.id_typu = akce_seznam.typ
            LEFT JOIN akce_seznam AS karta
                ON karta.id_akce = akce_seznam.id_akce OR (akce_seznam.patri_pod IS NOT NULL AND karta.patri_pod = akce_seznam.patri_pod)
            LEFT JOIN archiv_prednasek ON archiv_prednasek.id_akce = karta.id_akce
            WHERE akce_seznam.nazev_akce LIKE $0
                AND ($1 IS NULL OR akce_seznam.rok = $1)
            GROUP BY id_karty
            ORDER BY rok DESC, nazev
            LIMIT 30
            SQL, [
            0 => '%' . addcslashes($hledat, '%_\\') . '%',
            1 => $rok,
        ]);

        return array_map(static fn (array $radek) => [
            'value' => (int) $radek['id_karty'],
            'label' => NazevPrednasky::uprav((string) $radek['nazev']) . " ({$radek['rok']}, {$radek['typ']}, ID {$radek['id_karty']})"
                . (isset($radek['v_archivu']) ? ' – v archivu jako „' . $radek['v_archivu'] . '“' : ''),
        ], $radky);
    }

    /**
     * @return array{nazev: string, rok: int, typ: string}|null
     */
    public function aktivita(int $idAkce): ?array
    {
        $radek = dbOneLine(<<<'SQL'
            SELECT akce_seznam.nazev_akce, akce_seznam.rok, akce_typy.typ_1p
            FROM akce_seznam
            LEFT JOIN akce_typy ON akce_typy.id_typu = akce_seznam.typ
            WHERE akce_seznam.id_akce = $0
            SQL, [
            0 => $idAkce,
        ]);

        return $radek
            ? [
                'nazev' => NazevPrednasky::uprav((string) $radek['nazev_akce']),
                'rok'   => (int) $radek['rok'],
                'typ'   => (string) $radek['typ_1p'],
            ]
            : null;
    }

    /**
     * The main activity of the card; the card must not belong to another lecture already.
     */
    private function overenaAkceKSparovani(int $idAkce, ?int $idPrednasky): int
    {
        $akce = dbOneLine(<<<'SQL'
            SELECT akce_seznam.id_akce, akce_seznam.patri_pod, akce_instance.id_hlavni_akce
            FROM akce_seznam
            LEFT JOIN akce_instance ON akce_instance.id_instance = akce_seznam.patri_pod
            WHERE akce_seznam.id_akce = $0
            SQL, [
            0 => $idAkce,
        ]);
        if (! $akce) {
            throw new ChybaArchivu("Aktivita {$idAkce} v programu není.");
        }
        $idHlavniAkce = (int) ($akce['id_hlavni_akce'] ?? $akce['id_akce']);
        $jinaPrednaska = dbOneLine(<<<'SQL'
            SELECT archiv_prednasek.nazev, archiv_prednasek.rok
            FROM archiv_prednasek
            JOIN akce_seznam ON akce_seznam.id_akce = archiv_prednasek.id_akce
            WHERE (akce_seznam.id_akce = $0 OR ($1 IS NOT NULL AND akce_seznam.patri_pod = $1))
                AND ($2 IS NULL OR archiv_prednasek.id_prednasky != $2)
            SQL, [
            0 => $idAkce,
            1 => $akce['patri_pod'] ?? null,
            2 => $idPrednasky,
        ]);
        if ($jinaPrednaska) {
            throw new ChybaArchivu("Karta programu už patří přednášce „{$jinaPrednaska['nazev']}“ ({$jinaPrednaska['rok']}).");
        }

        return $idHlavniAkce;
    }

    /**
     * @param list<array{jmeno: string, idUzivatele: int|null, menitUzivatele: bool}> $prednasejici
     *
     * @return list<array{jmeno: string, idUzivatele: int|null, menitUzivatele: bool}>
     */
    private function overeniPrednasejici(array $prednasejici): array
    {
        $overeni = [];
        $jmena = [];
        foreach ($prednasejici as ['jmeno' => $jmeno, 'idUzivatele' => $idUzivatele, 'menitUzivatele' => $menitUzivatele]) {
            $jmeno = trim((string) preg_replace('~\s+~u', ' ', $jmeno));
            if ($jmeno === '') {
                if ($idUzivatele !== null) {
                    throw new ChybaArchivu('Přednášející napojený na uživatele musí mít jméno pro web.');
                }
                continue;
            }
            if (mb_strlen($jmeno) > 255) {
                throw new ChybaArchivu("Jméno „{$jmeno}“ je delší než 255 znaků.");
            }
            if ($idUzivatele !== null && ! dbOneCol('SELECT 1 FROM uzivatele_hodnoty WHERE id_uzivatele = $0', [
                0 => $idUzivatele,
            ])) {
                throw new ChybaArchivu("Uživatel {$idUzivatele} neexistuje.");
            }
            if (in_array(mb_strtolower($jmeno), $jmena, true)) {
                continue;
            }
            $jmena[] = mb_strtolower($jmeno);
            $overeni[] = [
                'jmeno'          => $jmeno,
                'idUzivatele'    => $idUzivatele,
                'menitUzivatele' => $menitUzivatele,
            ];
        }

        return $overeni;
    }

    private function idPrednasejiciho(string $jmeno, ?int $idUzivatele, bool $menitUzivatele): int
    {
        $idPrednasejiciho = $this->repository->idPrednasejicihoPodleJmena($jmeno);
        if ($idPrednasejiciho === null) {
            return $this->repository->vlozPrednasejiciho($jmeno, $idUzivatele);
        }
        if (! $menitUzivatele) {
            return $idPrednasejiciho;
        }
        // the same speaker in every lecture, a change of his link applies to all of them
        dbQuery(<<<'SQL'
            UPDATE archiv_prednasek_prednasejici SET id_uzivatele = $0 WHERE id_prednasejiciho = $1
            SQL, [
            0 => $idUzivatele,
            1 => $idPrednasejiciho,
        ]);

        return $idPrednasejiciho;
    }

    private function uklidNepouzite(): void
    {
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek_prednasejici
            WHERE id_prednasejiciho NOT IN (SELECT id_prednasejiciho FROM archiv_prednasek_prednaska_prednasejici)
            SQL);
        dbQuery(<<<'SQL'
            DELETE FROM archiv_prednasek_klicove_slovo
            WHERE id_klicoveho_slova NOT IN (SELECT id_klicoveho_slova FROM archiv_prednasek_prednaska_klicove_slovo)
            SQL);
    }

    /**
     * @param list<string> $hodnoty
     *
     * @return list<string> without empty ones and duplicates regardless of case, in their order
     */
    private static function bezDuplicit(array $hodnoty): array
    {
        $vysledek = [];
        foreach ($hodnoty as $hodnota) {
            if ($hodnota !== '' && ! in_array(mb_strtolower($hodnota), array_map('mb_strtolower', $vysledek), true)) {
                $vysledek[] = $hodnota;
            }
        }

        return $vysledek;
    }

    private static function bitNeboNull(?bool $hodnota): ?int
    {
        return $hodnota === null
            ? null
            : (int) $hodnota;
    }
}
