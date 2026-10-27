<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

use Gamecon\Aktivita\Korektura\Exceptions\KorekturuNelzeVyridit;
use Gamecon\Cache\ProgramStaticFileGenerator;
use Gamecon\Cache\ProgramStaticFileType;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Proofreading of activity texts by hand through an AI chat: the texts go out in packages
 * ({@see SouborKorektur}), the answer comes back ({@see OdpovedAi}) and the proofreader accepts,
 * edits or rejects each proposal. Only an accepted one changes the activity.
 */
class KorekturyTextu
{
    public const VYCHOZI_VELIKOST_BALIKU = 25;

    // a proofreading that rewrites more than this is suspicious
    private const float VELKY_PODIL_ZMEN = 0.3;

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly KorekturyTextuRepository $repository = new KorekturyTextuRepository(),
    ) {
    }

    public function pocetKExportu(): int
    {
        return count($this->repository->dejTextyKExportu($this->systemoveNastaveni->rocnik()));
    }

    /**
     * @return array<int> numbers of the new packages
     */
    public function exportuj(int $velikostBaliku, int $exportoval): array
    {
        return $this->repository->vytvorBaliky(
            $this->repository->dejTextyKExportu($this->systemoveNastaveni->rocnik()),
            $velikostBaliku,
            $exportoval,
        );
    }

    /**
     * @param int|null $balik null for all packages waiting for the AI's answer in one file
     */
    public function soubor(?int $balik): string
    {
        $rocnik = $this->systemoveNastaveni->rocnik();
        $korektury = $this->repository->dejCekajiciNaOdpoved($balik);
        $puvodni = [];
        foreach ($korektury as $korektura) {
            $puvodni[$korektura->idAktivity] = $korektura->puvodni;
        }

        return (new SouborKorektur())->vytvor(
            $korektury,
            $this->repository->dejDrivejsiOpraveneTexty($puvodni, $rocnik),
            $rocnik,
        );
    }

    public function nazevSouboru(?int $balik): string
    {
        return (new SouborKorektur())->nazevSouboru($this->systemoveNastaveni->rocnik(), $balik);
    }

    /**
     * An answer can come in parts and again, a later proposal for an activity replaces the earlier one
     * until the proofreader settles it.
     */
    public function importuj(string $odpoved): VysledekImportu
    {
        $odpovedAi = OdpovedAi::rozparsuj($odpoved);
        $varovani = $odpovedAi->varovani;
        $otevrene = $this->repository->dejOtevrene(array_keys($odpovedAi->navrhy));

        $pocetNavrhu = 0;
        $pocetBezZmen = 0;
        foreach ($odpovedAi->navrhy as $idAktivity => $navrh) {
            $korektura = $otevrene[$idAktivity] ?? null;
            if ($korektura === null) {
                $varovani[] = "Aktivita {$idAktivity} nečeká na odpověď AI (nebyla v exportu, nebo už je vyřízená), přeskakuji ji.";
                continue;
            }

            $puvodni = $korektura->puvodni;
            $nazev = $this->zmena($navrh->nazev, $puvodni->nazev, 'název', TextyAktivity::MAX_DELKA_NAZVU, $puvodni->nazev, $varovani);
            $popisKratky = $this->zmena($navrh->popisKratky, $puvodni->popisKratky, 'krátký popis', TextyAktivity::MAX_DELKA_POPISU_KRATKEHO, $puvodni->nazev, $varovani);
            $popis = $this->zmena($navrh->popis, $puvodni->popis, 'popis', null, $puvodni->nazev, $varovani);
            $bezZmen = $nazev === null && $popisKratky === null && $popis === null;
            $zmeny = $navrh->zmeny !== null && ! $bezZmen
                ? TextyAktivity::normalizuj($navrh->zmeny)
                : '';

            $this->repository->ulozNavrh(
                $korektura->id,
                $nazev,
                $popisKratky,
                $popis,
                $zmeny !== ''
                    ? $zmeny
                    : null,
            );
            ++$pocetNavrhu;
            if ($bezZmen) {
                ++$pocetBezZmen;
            }
        }

        return new VysledekImportu($pocetNavrhu, $pocetBezZmen, $varovani);
    }

    /**
     * Saves the texts the proofreader sent, the AI's proposal or their own edit of it,
     * to the whole family of instances and marks them as proofread.
     */
    public function prijmi(int $idKorektury, TextyAktivity $texty, int $vyridil): void
    {
        $korektura = $this->dejNavrzenou($idKorektury);
        $aktualni = $this->repository->dejAktualniTexty([$korektura->idAktivity])[$korektura->idAktivity] ?? null;
        $branicPrijeti = $this->varovaniBranicPrijeti($aktualni, $korektura);
        if ($aktualni === null || $branicPrijeti !== []) {
            throw new KorekturuNelzeVyridit(implode(' ', $branicPrijeti));
        }

        $texty = $texty->normalizovane();
        if ($texty->nazev === '') {
            throw new KorekturuNelzeVyridit('Název aktivity nesmí být prázdný.');
        }
        if (mb_strlen($texty->nazev) > TextyAktivity::MAX_DELKA_NAZVU) {
            throw new KorekturuNelzeVyridit('Název aktivity smí mít nejvýš ' . TextyAktivity::MAX_DELKA_NAZVU . ' znaků.');
        }
        if (mb_strlen($texty->popisKratky) > TextyAktivity::MAX_DELKA_POPISU_KRATKEHO) {
            throw new KorekturuNelzeVyridit('Krátký popis smí mít nejvýš ' . TextyAktivity::MAX_DELKA_POPISU_KRATKEHO . ' znaků.');
        }
        // a text left as it is stays byte for byte, its line ends included
        $texty = new TextyAktivity(
            $texty->nazev === TextyAktivity::normalizuj($aktualni->nazev)
                ? $aktualni->nazev
                : $texty->nazev,
            $texty->popisKratky === TextyAktivity::normalizuj($aktualni->popisKratky)
                ? $aktualni->popisKratky
                : $texty->popisKratky,
            $texty->popis === TextyAktivity::normalizuj($aktualni->popis)
                ? $aktualni->popis
                : $texty->popis,
        );

        dbBegin();
        try {
            $this->repository->ulozOpraveneTexty($korektura->idAktivity, $texty);
            $this->repository->vyrid($idKorektury, StavKorekturyEnum::PRIJATO, $vyridil);
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }

        $programStaticFilesGenerator = new ProgramStaticFileGenerator($this->systemoveNastaveni);
        $programStaticFilesGenerator->touchDirtyFlag(ProgramStaticFileType::AKTIVITY, tryStartWorker: false);
        $programStaticFilesGenerator->touchDirtyFlag(ProgramStaticFileType::POPISY, tryStartWorker: false);
    }

    /**
     * The activity stays as it is and not proofread, it can go to the AI again.
     */
    public function zamitni(int $idKorektury, int $vyridil): void
    {
        $this->dejNavrzenou($idKorektury);
        $this->repository->vyrid($idKorektury, StavKorekturyEnum::ZAMITNUTO, $vyridil);
    }

    /**
     * Marks as proofread the activities in which the AI found nothing to change.
     *
     * @return int how many
     */
    public function prijmiVsechnyBezZmen(int $vyridil): int
    {
        $bezZmen = array_filter(
            $this->repository->dejKeSchvaleni(null),
            static fn (KorekturaTextu $korektura) => ! $korektura->maNavrzeneZmeny(),
        );
        $aktualni = $this->repository->dejAktualniTexty(array_map(
            static fn (KorekturaTextu $korektura) => $korektura->idAktivity,
            $bezZmen,
        ));

        $pocet = 0;
        foreach ($bezZmen as $korektura) {
            if ($this->varovaniBranicPrijeti($aktualni[$korektura->idAktivity] ?? null, $korektura) === []) {
                $this->prijmi($korektura->id, $korektura->puvodni, $vyridil);
                ++$pocet;
            }
        }

        return $pocet;
    }

    /**
     * @return array<KorekturaTextu>
     */
    public function dejKeSchvaleni(int $limit): array
    {
        return $this->repository->dejKeSchvaleni($limit);
    }

    /**
     * @param array<int> $idsAktivit
     *
     * @return array<int, TextyAktivity> by activity ID
     */
    public function dejAktualniTexty(array $idsAktivit): array
    {
        return $this->repository->dejAktualniTexty($idsAktivit);
    }

    /**
     * @return array<int, int> number of activities by package
     */
    public function dejCekajiciBaliky(): array
    {
        return $this->repository->dejCekajiciBaliky();
    }

    public function zrusBalik(int $balik): int
    {
        return $this->repository->zrusBalik($balik);
    }

    public function pocetKeSchvaleni(): int
    {
        return $this->repository->pocetVeStavu(StavKorekturyEnum::NAVRZENO);
    }

    /**
     * What blocks accepting the proposal; while it is not empty, only rejecting is possible.
     *
     * @return array<string>
     */
    public function varovaniBranicPrijeti(?TextyAktivity $aktualni, KorekturaTextu $korektura): array
    {
        if ($aktualni === null) {
            return ['Aktivita už neexistuje.'];
        }
        if (! $aktualni->jeStejnyJako($korektura->puvodni)) {
            return ["Text aktivity „{$aktualni->nazev}“ se od exportu změnil. Návrh zamítni a pošli aktivitu na korekturu znovu."];
        }

        return [];
    }

    /**
     * What the proofreader should look at closely before accepting.
     *
     * @return array<string>
     */
    public function varovaniKeKontrole(KorekturaTextu $korektura): array
    {
        $puvodni = $korektura->puvodni->normalizovane();
        $navrh = $korektura->navrh()->normalizovane();
        $varovani = [];
        if ($this->odkazy($puvodni) !== $this->odkazy($navrh)) {
            $varovani[] = 'Návrh mění odkazy nebo adresy, zkontroluj je.';
        }
        $podilZmen = max(
            RozdilTextu::podilZmen($puvodni->nazev, $navrh->nazev),
            RozdilTextu::podilZmen($puvodni->popisKratky, $navrh->popisKratky),
            RozdilTextu::podilZmen($puvodni->popis, $navrh->popis),
        );
        if ($podilZmen > self::VELKY_PODIL_ZMEN) {
            $varovani[] = sprintf('Návrh mění velkou část textu (%d %%), zkontroluj, že nemění smysl.', round($podilZmen * 100));
        }

        return $varovani;
    }

    private function dejNavrzenou(int $idKorektury): KorekturaTextu
    {
        $korektura = $this->repository->dej($idKorektury);
        if ($korektura === null) {
            throw new KorekturuNelzeVyridit('Návrh korektury už neexistuje.');
        }
        if ($korektura->stav !== StavKorekturyEnum::NAVRZENO) {
            throw new KorekturuNelzeVyridit("Návrh korektury aktivity „{$korektura->puvodni->nazev}“ už je vyřízený.");
        }

        return $korektura;
    }

    /**
     * @param array<string> $varovani
     */
    private function zmena(
        ?string $navrh,
        string $puvodni,
        string $pole,
        ?int $maxDelka,
        string $nazevAktivity,
        array &$varovani,
    ): ?string {
        if ($navrh === null) {
            return null;
        }
        $navrh = TextyAktivity::normalizuj($navrh);
        if ($navrh === TextyAktivity::normalizuj($puvodni)) {
            return null;
        }
        if ($navrh === '') {
            $varovani[] = "„{$nazevAktivity}“: AI vrátila prázdný {$pole}, ponechávám původní.";

            return null;
        }
        if ($maxDelka !== null && mb_strlen($navrh) > $maxDelka) {
            $varovani[] = "„{$nazevAktivity}“: navržený {$pole} je delší než {$maxDelka} znaků, ponechávám původní.";

            return null;
        }

        return $navrh;
    }

    /**
     * @return array<string> sorted
     */
    private function odkazy(TextyAktivity $texty): array
    {
        preg_match_all(
            '~https?://[^\s<>"\')\]]+|\]\([^)\s]+\)~u',
            implode("\n", [$texty->nazev, $texty->popisKratky, $texty->popis]),
            $shody,
        );
        $odkazy = $shody[0];
        sort($odkazy);

        return $odkazy;
    }
}
