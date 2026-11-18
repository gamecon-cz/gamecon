<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

use Gamecon\Aktivita\Korektura\Exceptions\ChybaApiAi;
use Gamecon\Aktivita\Korektura\Exceptions\KorekturuNelzeVyridit;
use Gamecon\Aktivita\Korektura\Exceptions\OdesilaniAiUzBezi;
use Gamecon\Cache\ProgramStaticFileGenerator;
use Gamecon\Cache\ProgramStaticFileType;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Proofreading of activity texts by an AI: the texts go out in packages, by hand through an AI chat
 * ({@see SouborKorektur}) or through the AI's API ({@see DavkyAi}), the answer comes back ({@see OdpovedAi})
 * and the proofreader accepts, edits or rejects each proposal. Only an accepted one changes the activity.
 */
class KorekturyTextu
{
    public const VYCHOZI_VELIKOST_BALIKU = 25;

    // a proofreading that rewrites more than this is suspicious
    private const float VELKY_PODIL_ZMEN = 0.3;

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly KorekturyTextuRepository $repository = new KorekturyTextuRepository(),
        // null without the API, the texts then go through a file only
        private readonly ?DavkyAi $davkyAi = null,
    ) {
    }

    public function jeAiPripojena(): bool
    {
        return $this->davkyAi !== null;
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

        return (new SouborKorektur())->vytvor($korektury, $this->drivejsiTexty($korektury), $rocnik);
    }

    /**
     * Sends the texts waiting for the AI's answer to the AI's API, each activity in a request of its own,
     * all in one batch. Texts already waiting in a batch stay there.
     *
     * @param int|null $balik null for all packages
     *
     * @return int how many activities went out
     *
     * @throws ChybaApiAi
     * @throws OdesilaniAiUzBezi
     */
    public function posliAi(?int $balik): int
    {
        $davkyAi = $this->pripojeneDavkyAi();

        // read inside the lock, so that a sending that ran meanwhile has marked its texts already
        return $this->repository->vZamkuOdesilaniAi(function () use ($davkyAi, $balik): int {
            $korektury = array_values(array_filter(
                $this->repository->dejCekajiciNaOdpoved($balik),
                static fn (KorekturaTextu $korektura) => $korektura->idDavkyAi === null,
            ));
            if ($korektury === []) {
                return 0;
            }

            $souborKorektur = new SouborKorektur();
            $drivejsi = $this->drivejsiTexty($korektury);
            $zpravy = [];
            foreach ($korektury as $korektura) {
                $zpravy[self::idPozadavku($korektura)] = $souborKorektur->blokAktivity(
                    $korektura,
                    $drivejsi[$korektura->idAktivity] ?? null,
                );
            }

            $idDavky = $davkyAi->odesli($souborKorektur->pokyny($this->systemoveNastaveni->rocnik()), $zpravy);
            $this->repository->oznacOdeslaneAi(
                array_map(static fn (KorekturaTextu $korektura) => $korektura->id, $korektury),
                $idDavky,
            );

            return count($korektury);
        });
    }

    /**
     * Imports the answers of the AI batches that have ended, as if they came in a file. The texts a batch
     * brought no proposal for (an error, a refusal, a cut answer) go back among the texts to send. A batch whose
     * answers cannot be read now does not hold up the others, it is asked again next time.
     */
    public function nactiOdpovediAi(): VysledekNacteniAi
    {
        $davkyAi = $this->pripojeneDavkyAi();
        $navrhy = [];
        $varovani = [];
        $chyby = [];
        $skonceneDavky = [];
        foreach ($this->repository->dejDavkyAi() as $idDavky) {
            $cekajici = $this->repository->dejCekajiciVDavceAi($idDavky);
            if ($cekajici === []) {
                // its texts were settled meanwhile (a proposal from the chat approved or rejected)
                $this->zrusDavkuAi($idDavky);
                continue;
            }
            try {
                $odpovedi = $davkyAi->dejOdpovedi($idDavky);
            } catch (ChybaApiAi $chybaApiAi) {
                if ($chybaApiAi->jeNeznamaDavka()) {
                    $this->repository->uvolniZDavkyAi($idDavky);
                    $chyby[] = "Dávku AI {$idDavky} už API nezná, aktivity, které na ni čekaly, jdou poslat znovu. ({$chybaApiAi->getMessage()})";
                    continue;
                }
                $chyby[] = "Odpovědi AI z dávky {$idDavky} se nepodařilo načíst, zkusím to znovu při dalším otevření stránky. ({$chybaApiAi->getMessage()})";
                if ($chybaApiAi->jeChybaCelehoApi()) {
                    // the other batches would end the same, and each would let the proofreader wait
                    break;
                }
                continue;
            }
            if ($odpovedi === null) {
                continue;
            }
            $skonceneDavky[] = $idDavky;
            foreach ($cekajici as $korektura) {
                // a text answered from the chat meanwhile keeps that proposal when the batch brings none
                $coZbyva = $korektura->stav === StavKorekturyEnum::EXPORTOVANO
                    ? 'aktivita zůstává k odeslání'
                    : 'zůstává návrh z chatu';
                $odpoved = $odpovedi[self::idPozadavku($korektura)]
                    ?? OdpovedAiNaPozadavek::chyba('v odpovědi AI chybí');
                if ($odpoved->text === null) {
                    $varovani[] = "„{$korektura->puvodni->nazev}“: {$odpoved->chyba}, {$coZbyva}.";
                    continue;
                }
                $odpovedAi = OdpovedAi::rozparsuj($odpoved->text);
                foreach ($odpovedAi->varovani as $varovaniOdpovedi) {
                    $varovani[] = "„{$korektura->puvodni->nazev}“: {$varovaniOdpovedi}";
                }
                // one request is one activity, a block of another one would be a mistake of the AI
                $navrh = $odpovedAi->navrhy[$korektura->idAktivity] ?? null;
                if ($navrh === null) {
                    $varovani[] = "„{$korektura->puvodni->nazev}“: AI nevrátila její blok, {$coZbyva}.";
                    continue;
                }
                $navrhy[$korektura->idAktivity] = $navrh;
            }
        }

        $vysledekImportu = $skonceneDavky !== []
            ? $this->importujOdpoved(new OdpovedAi($navrhy, $varovani))
            : null;
        // only after the import, a text without a proposal is still waiting for the AI till then
        foreach ($skonceneDavky as $idDavky) {
            $this->repository->uvolniZDavkyAi($idDavky);
        }

        return new VysledekNacteniAi($vysledekImportu, $chyby);
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
        return $this->importujOdpoved(OdpovedAi::rozparsuj($odpoved));
    }

    private function importujOdpoved(OdpovedAi $odpovedAi): VysledekImportu
    {
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
     * @return array<int, CekajiciBalik> by package
     */
    public function dejCekajiciBaliky(): array
    {
        return $this->repository->dejCekajiciBaliky();
    }

    /**
     * A batch left with no text waiting for it is cancelled, so that the AI does not work for nothing.
     */
    public function zrusBalik(int $balik): int
    {
        $davkyBaliku = $this->repository->dejDavkyAi($balik);
        $pocet = $this->repository->zrusBalik($balik);
        foreach ($davkyBaliku as $idDavky) {
            if ($this->repository->dejCekajiciVDavceAi($idDavky) === []) {
                $this->zrusDavkuAi($idDavky);
            }
        }

        return $pocet;
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

    private function pripojeneDavkyAi(): DavkyAi
    {
        return $this->davkyAi
            ?? throw new \LogicException('Korektury nemají připojené API AI.');
    }

    /**
     * Nothing waits for the batch any more: it is cancelled, so that the AI does not work for nothing,
     * and its texts forget it.
     */
    private function zrusDavkuAi(string $idDavky): void
    {
        try {
            $this->davkyAi?->zrus($idDavky);
        } catch (ChybaApiAi $chybaApiAi) {
            // it may have ended meanwhile, nothing reads its answer anyway
            error_log("Korektury: dávku AI {$idDavky} se nepodařilo zrušit: {$chybaApiAi->getMessage()}");
        }
        $this->repository->uvolniZDavkyAi($idDavky);
    }

    /**
     * Unique within a batch, which is all the API asks (letters, digits, `_`, `-`, at most 64 characters).
     */
    private static function idPozadavku(KorekturaTextu $korektura): string
    {
        return "korektura-{$korektura->id}";
    }

    /**
     * @param array<KorekturaTextu> $korektury
     *
     * @return array<int, DrivejsiTexty> by activity ID
     */
    private function drivejsiTexty(array $korektury): array
    {
        $puvodni = [];
        foreach ($korektury as $korektura) {
            $puvodni[$korektura->idAktivity] = $korektura->puvodni;
        }

        return $this->repository->dejDrivejsiOpraveneTexty($puvodni, $this->systemoveNastaveni->rocnik());
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
