<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek\Import;

use Gamecon\ArchivPrednasek\NazevPrednasky;

/**
 * Writes the unified spreadsheet and the lectures added from the program into the archive.
 * Pairing and user links are fixed in DataArcitabulky and only checked here, nothing is searched for.
 *
 * The migration 2026-11-10-124023_archiv-prednasek-import.php runs it on every fresh database, before the migrations
 * that come after it, so the SQL is its own and stays as the schema of that migration was: through
 * ArchivPrednasekRepository a later column there would break the migration.
 */
final class ImportArcitabulky
{
    /**
     * @var array<string, int> lower-case name => id of the speaker
     */
    private array $prednasejici = [];

    /**
     * @var array<string, int>|null name of the speaker => checked user id
     */
    private ?array $overeniUzivatele = null;

    /**
     * @var list<string>
     */
    private array $zpravy = [];

    /**
     * @param array<string, array{0: int, 1: string}>                                     $uzivatele name => [user id, sha256 of the login]
     * @param list<array{0: string, 1: int, 2: int, 3: string}>                           $parovani  [title, year, activity id, title in the program]
     * @param list<array{0: string, 1: int, 2: int, 3: string, 4: bool, 5: list<string>}> $zProgramu
     */
    public function __construct(
        private readonly ?\mysqli $spojeni = null,
        private readonly array $uzivatele = DataArcitabulky::UZIVATELE,
        private readonly array $parovani = DataArcitabulky::PAROVANI,
        private readonly array $zProgramu = DataArcitabulky::Z_PROGRAMU,
    ) {
    }

    /**
     * @param list<RadekArcitabulky> $radky
     *
     * @return list<string> what stayed unpaired or unlinked, for the log
     */
    public function importuj(array $radky): array
    {
        $this->zpravy = [];
        $this->overeniUzivatele = null;

        $parovaniPodleKlice = [];
        foreach ($this->parovani as [$nazev, $rok, $idAkce, $nazevVProgramu]) {
            $parovaniPodleKlice[self::klic($nazev, $rok)] = [$idAkce, $nazevVProgramu];
        }
        $idsAkci = array_merge(
            array_column($this->parovani, 2),
            array_column(array_filter($this->zProgramu, static fn (array $zaznam) => $zaznam[4]), 2),
        );
        $akce = $this->nactiAkce($idsAkci);

        foreach ($radky as $radek) {
            $klic = self::klic($radek->nazev, $radek->rok);
            $idAkce = null;
            if (isset($parovaniPodleKlice[$klic])) {
                [$idAkceZDat, $nazevVProgramu] = $parovaniPodleKlice[$klic];
                $idAkce = $this->overenaAkce($akce, $idAkceZDat, $radek->rok, $nazevVProgramu, $radek->nazev);
                unset($parovaniPodleKlice[$klic]);
            }
            $idPrednasky = $this->vlozPrednasku($radek->nazev, $radek->rok, $radek->youtubeId, $idAkce);
            $this->pridejPrednasejici($idPrednasky, array_map($this->idPrednasejiciho(...), $radek->prednasejici));
            $this->pridejKlicovaSlova($idPrednasky, array_map($this->idKlicovehoSlova(...), $radek->klicovaSlova));
        }
        // a pairing that matches no row would mean the unification changed after the pairing was approved
        foreach ($parovaniPodleKlice as $klic => [$idAkce]) {
            $this->zpravy[] = "Párování „{$klic}“ s aktivitou {$idAkce} neodpovídá žádné přednášce z tabulky";
        }

        foreach ($this->zProgramu as [$nazev, $rok, $idAkceZDat, $nazevVProgramu, $sparovat, $jmena]) {
            $idAkce = $sparovat
                ? $this->overenaAkce($akce, $idAkceZDat, $rok, $nazevVProgramu, $nazev)
                : null;
            $idPrednasky = $this->vlozPrednasku($nazev, $rok, null, $idAkce);
            $this->pridejPrednasejici($idPrednasky, array_map($this->idPrednasejiciho(...), $jmena));
        }

        return $this->zpravy;
    }

    private static function klic(string $nazev, int $rok): string
    {
        return $rok . ' ' . mb_strtolower($nazev);
    }

    /**
     * @param list<int> $idsAkci
     *
     * @return array<int, array{rok: int, nazev: string}>
     */
    private function nactiAkce(array $idsAkci): array
    {
        $akce = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT id_akce, rok, nazev_akce
            FROM akce_seznam
            WHERE id_akce IN ($0)
            SQL, [
            0 => $idsAkci,
        ], $this->spojeni) as $radek) {
            $akce[(int) $radek['id_akce']] = [
                'rok'   => (int) $radek['rok'],
                'nazev' => (string) $radek['nazev_akce'],
            ];
        }

        return $akce;
    }

    /**
     * @param array<int, array{rok: int, nazev: string}> $akce
     */
    private function overenaAkce(array $akce, int $idAkce, int $rok, string $nazevVProgramu, string $nazevPrednasky): ?int
    {
        $popis = "Přednáška „{$nazevPrednasky}“ ({$rok}) zůstala nespárovaná: aktivita {$idAkce}";
        $nalezena = $akce[$idAkce] ?? null;
        if ($nalezena === null) {
            $this->zpravy[] = "{$popis} v databázi není";

            return null;
        }
        if ($nalezena['rok'] !== $rok) {
            $this->zpravy[] = "{$popis} je z roku {$nalezena['rok']}";

            return null;
        }
        // compared after unification, an extra space or a different dash on production is not another activity
        if (mb_strtolower(NazevPrednasky::uprav($nalezena['nazev'])) !== mb_strtolower(NazevPrednasky::uprav($nazevVProgramu))) {
            $this->zpravy[] = "{$popis} se jmenuje „{$nalezena['nazev']}“, čekal jsem „{$nazevVProgramu}“";

            return null;
        }

        return $idAkce;
    }

    private function idPrednasejiciho(string $jmeno): int
    {
        $klic = mb_strtolower($jmeno);
        if (isset($this->prednasejici[$klic])) {
            return $this->prednasejici[$klic];
        }

        $existujici = dbOneCol(<<<'SQL'
            SELECT id_prednasejiciho
            FROM archiv_prednasek_prednasejici
            WHERE jmeno = $0
            ORDER BY id_uzivatele IS NULL, id_prednasejiciho
            LIMIT 1
            SQL, [
            0 => $jmeno,
        ], $this->spojeni);
        if ($existujici !== null && $existujici !== false) {
            return $this->prednasejici[$klic] = (int) $existujici;
        }
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek_prednasejici (jmeno, id_uzivatele)
            VALUES ($0, $1)
            SQL, [
            0 => $jmeno,
            1 => $this->overenyUzivatel($jmeno),
        ], $this->spojeni);

        return $this->prednasejici[$klic] = $this->idVlozeneho();
    }

    private function vlozPrednasku(string $nazev, int $rok, ?string $youtubeId, ?int $idAkce): int
    {
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek (nazev, rok, youtube_id, id_akce)
            VALUES ($0, $1, $2, $3)
            SQL, [
            0 => $nazev,
            1 => $rok,
            2 => $youtubeId,
            3 => $idAkce,
        ], $this->spojeni);

        return $this->idVlozeneho();
    }

    /**
     * @param list<int> $idsPrednasejicich in the order they are shown
     */
    private function pridejPrednasejici(int $idPrednasky, array $idsPrednasejicich): void
    {
        foreach (array_values(array_unique($idsPrednasejicich)) as $poradi => $idPrednasejiciho) {
            dbQuery(<<<'SQL'
                INSERT INTO archiv_prednasek_prednaska_prednasejici (id_prednasky, id_prednasejiciho, poradi)
                VALUES ($0, $1, $2)
                SQL, [
                0 => $idPrednasky,
                1 => $idPrednasejiciho,
                2 => $poradi,
            ], $this->spojeni);
        }
    }

    /**
     * The name is unique without regard to case (collation), so „rpg“ finds „RPG“.
     */
    private function idKlicovehoSlova(string $nazev): int
    {
        $existujici = dbOneCol(<<<'SQL'
            SELECT id_klicoveho_slova FROM archiv_prednasek_klicove_slovo WHERE nazev = $0
            SQL, [
            0 => $nazev,
        ], $this->spojeni);
        if ($existujici !== null && $existujici !== false) {
            return (int) $existujici;
        }
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek_klicove_slovo (nazev) VALUES ($0)
            SQL, [
            0 => $nazev,
        ], $this->spojeni);

        return $this->idVlozeneho();
    }

    /**
     * @param list<int> $idsKlicovychSlov
     */
    private function pridejKlicovaSlova(int $idPrednasky, array $idsKlicovychSlov): void
    {
        foreach (array_values(array_unique($idsKlicovychSlov)) as $idKlicovehoSlova) {
            dbQuery(<<<'SQL'
                INSERT INTO archiv_prednasek_prednaska_klicove_slovo (id_prednasky, id_klicoveho_slova)
                VALUES ($0, $1)
                SQL, [
                0 => $idPrednasky,
                1 => $idKlicovehoSlova,
            ], $this->spojeni);
        }
    }

    private function idVlozeneho(): int
    {
        return (int) ($this->spojeni ?? dbConnect())->insert_id;
    }

    private function overenyUzivatel(string $jmeno): ?int
    {
        if ($this->overeniUzivatele === null) {
            $this->overeniUzivatele = [];
            $loginy = [];
            foreach (dbFetchAll(<<<'SQL'
                SELECT id_uzivatele, login_uzivatele
                FROM uzivatele_hodnoty
                WHERE id_uzivatele IN ($0)
                SQL, [
                0 => array_column($this->uzivatele, 0),
            ], $this->spojeni) as $radek) {
                $loginy[(int) $radek['id_uzivatele']] = (string) $radek['login_uzivatele'];
            }
            foreach ($this->uzivatele as $jmenoPrednasejiciho => [$idUzivatele, $hashLoginu]) {
                if (! isset($loginy[$idUzivatele])) {
                    $this->zpravy[] = "Přednášející „{$jmenoPrednasejiciho}“ zůstal bez uživatele: uživatel {$idUzivatele} v databázi není";
                } elseif (! hash_equals($hashLoginu, hash('sha256', $loginy[$idUzivatele]))) {
                    $this->zpravy[] = "Přednášející „{$jmenoPrednasejiciho}“ zůstal bez uživatele: uživatel {$idUzivatele} má login „{$loginy[$idUzivatele]}“, jiný než při schválení napojení";
                } else {
                    $this->overeniUzivatele[$jmenoPrednasejiciho] = $idUzivatele;
                }
            }
        }

        return $this->overeniUzivatele[$jmeno] ?? null;
    }
}
