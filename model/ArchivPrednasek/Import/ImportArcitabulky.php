<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek\Import;

use Gamecon\ArchivPrednasek\ArchivPrednasekRepository;
use Gamecon\ArchivPrednasek\NazevPrednasky;

/**
 * Writes the unified spreadsheet and the lectures added from the program into the archive.
 * Pairing and user links are fixed in DataArcitabulky and only checked here, nothing is searched for.
 */
final class ImportArcitabulky
{
    private readonly ArchivPrednasekRepository $repository;

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
     * @param array<string, array{0: int, 1: string}>                                     $uzivatele name => [user id, login]
     * @param list<array{0: string, 1: int, 2: int, 3: string}>                           $parovani  [title, year, activity id, title in the program]
     * @param list<array{0: string, 1: int, 2: int, 3: string, 4: bool, 5: list<string>}> $zProgramu
     */
    public function __construct(
        private readonly ?\mysqli $spojeni = null,
        private readonly array $uzivatele = DataArcitabulky::UZIVATELE,
        private readonly array $parovani = DataArcitabulky::PAROVANI,
        private readonly array $zProgramu = DataArcitabulky::Z_PROGRAMU,
    ) {
        $this->repository = new ArchivPrednasekRepository($spojeni);
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
            $idPrednasky = $this->repository->vlozPrednasku($radek->nazev, $radek->rok, $radek->youtubeId, $idAkce);
            $this->repository->nastavPrednasejici($idPrednasky, array_map($this->idPrednasejiciho(...), $radek->prednasejici));
            $this->repository->nastavKlicovaSlova($idPrednasky, array_map($this->repository->idKlicovehoSlova(...), $radek->klicovaSlova));
        }
        // a pairing that matches no row would mean the unification changed after the pairing was approved
        foreach ($parovaniPodleKlice as $klic => [$idAkce]) {
            $this->zpravy[] = "Párování „{$klic}“ s aktivitou {$idAkce} neodpovídá žádné přednášce z tabulky";
        }

        foreach ($this->zProgramu as [$nazev, $rok, $idAkceZDat, $nazevVProgramu, $sparovat, $jmena]) {
            $idAkce = $sparovat
                ? $this->overenaAkce($akce, $idAkceZDat, $rok, $nazevVProgramu, $nazev)
                : null;
            $idPrednasky = $this->repository->vlozPrednasku($nazev, $rok, null, $idAkce);
            $this->repository->nastavPrednasejici($idPrednasky, array_map($this->idPrednasejiciho(...), $jmena));
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

        return $this->prednasejici[$klic] = $this->repository->idPrednasejicihoPodleJmena($jmeno)
            ?? $this->repository->vlozPrednasejiciho($jmeno, $this->overenyUzivatel($jmeno));
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
            foreach ($this->uzivatele as $jmenoPrednasejiciho => [$idUzivatele, $login]) {
                if (! isset($loginy[$idUzivatele])) {
                    $this->zpravy[] = "Přednášející „{$jmenoPrednasejiciho}“ zůstal bez uživatele: uživatel {$idUzivatele} v databázi není";
                } elseif ($loginy[$idUzivatele] !== $login) {
                    $this->zpravy[] = "Přednášející „{$jmenoPrednasejiciho}“ zůstal bez uživatele: uživatel {$idUzivatele} má login „{$loginy[$idUzivatele]}“, čekal jsem „{$login}“";
                } else {
                    $this->overeniUzivatele[$jmenoPrednasejiciho] = $idUzivatele;
                }
            }
        }

        return $this->overeniUzivatele[$jmeno] ?? null;
    }
}
