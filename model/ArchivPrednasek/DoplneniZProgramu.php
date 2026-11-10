<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * After a GameCon ends, its lectures go into the archive by themselves: one lecture per card of the program
 * of the type lecture that took place, with its title, year, pairing and speakers (the organisers). Keywords and
 * the YouTube link are added by an administrator.
 *
 * Every card is decided only once (archiv_prednasek_zpracovane_aktivity): existing lectures are never changed,
 * a hidden one does not come back and a card whose pairing an administrator cancelled is not added again. A card
 * that finishes later is added by a later run; a card that failed is tried again after a day.
 *
 * @see admin/cron/jobs/archiv_prednasek_po_gc.php run by the hourly cron
 */
final class DoplneniZProgramu
{
    /**
     * the first year the spreadsheet import did not cover; older years are decided by people (záměr)
     */
    public const int PRVNI_ROCNIK = 2026;

    /**
     * a failed card waits this long for another attempt, so that a failure that repeats mails once a day,
     * not with every hourly cron
     */
    public const string OPAKOVAT_CHYBU_ZA = '1 day';

    /**
     * a card with such a tag is not a lecture (live play, a stream)
     */
    private const array STITKY_NEPREDNASKY = ['Živé hraní'];

    /**
     * a workshop without one of these tags is not a lecture
     */
    private const array STITKY_PREDNASKY = ['Talk', 'Panelová diskuse', 'Debata'];
    private const string STITEK_WORKSHOP = 'Workshop';

    /**
     * titles that are not a lecture, without regard to case, the way they were called in the program of 2009–2022;
     * a title is already unified by NazevPrednasky::uprav() (single spaces, trimmed)
     */
    private const array NAZVY_NEPREDNASKY = [
        // cancelled: ZRUŠENO, zrušená, ZRUŠENÉ, zrušenou, …; every form of the participle starts with „zrušen“ and
        // only the noun continues with „í“ (Zrušení nevolnictví v RPG)
        '~(?<!\pL)zrušen(?!í)~iu',
        // the opening and the closing of the festival (Slavnostní zahájení, Zakončení GC 2014, Zahájení Parconu)
        // as the whole title, so that „Zahájení kampaně v RPG“ stays a lecture
        '~^(?:slavnostní )?(?:zahájení|zakončení)(?: (?:gc|gamecon\pL*|parcon\pL*|festivalu|\d{4}))*$~iu',
        // announcements of results (Vyhlašování výsledků Ceny Karla Čapka)
        '~(?<!\pL)vyhl(?:ášení|ašování) výsledků(?!\pL)~iu',
        // competitions by the noun (Vyhlášení literární soutěže, Postřeh! (soutěž)), so that „Jak navrhnout soutěžní
        // deskovku“ stays a lecture
        '~(?<!\pL)soutěž(?:e|i|í|ích|ím|emi)?(?!\pL)~iu',
        // Oběd s organizátory
        '~(?<!\pL)oběd(?!\pL)~iu',
    ];

    /**
     * states that only the public program shows; an activity in one of them was in the program before it started
     */
    private const array STAVY_V_PROGRAMU = [StavAktivity::PUBLIKOVANA, StavAktivity::PRIPRAVENA, StavAktivity::AKTIVOVANA];

    public function __construct(
        private readonly int $letosniRocnik,
        private readonly \DateTimeInterface $ted,
        private readonly \DateTimeInterface $gcBeziDo,
        private readonly ArchivPrednasekRepository $repository = new ArchivPrednasekRepository(),
    ) {
    }

    public static function zNastaveni(SystemoveNastaveni $systemoveNastaveni): self
    {
        return new self($systemoveNastaveni->rocnik(), $systemoveNastaveni->ted(), $systemoveNastaveni->gcBeziDo());
    }

    /**
     * Years whose GameCon ended: the current one after its end, otherwise up to the past one. GC_BEZI_DO counts only
     * when it is in the current year: on ostra it is a custom value for every year, which stays from the past year
     * after the rollover until someone changes it, and someone can set the next one before the rollover. The end of
     * the current year is then computed, as the default of the setting is. The past year counts even after New Year,
     * when the current year is already the next one (záměr: 2026 must get in right after the deploy).
     *
     * @return list<int>
     */
    public function getSkonceneRocniky(): array
    {
        $letosniKonec = (int) $this->gcBeziDo->format('Y') === $this->letosniRocnik
            ? $this->gcBeziDo
            : DateTimeGamecon::spocitejKonecGameconu($this->letosniRocnik);
        $posledniSkonceny = $this->ted > $letosniKonec
            ? $this->letosniRocnik
            : $this->letosniRocnik - 1;

        return $posledniSkonceny < self::PRVNI_ROCNIK
            ? []
            : range(self::PRVNI_ROCNIK, $posledniSkonceny);
    }

    /**
     * Each card in its own transaction, so that one that fails does not stop the others.
     *
     * @param callable(string): void $zaloguj what happened, for the log of the cron
     *
     * @throws \RuntimeException when a card failed; the others are in, the failed ones are tried again after a day
     */
    public function doplnSkoncene(callable $zaloguj): void
    {
        $rocniky = $this->getSkonceneRocniky();
        if ($rocniky === []) {
            return;
        }
        $chyby = [];
        $prvniChyba = null;
        foreach ($this->getKartyKDoplneni($rocniky) as $karta) {
            try {
                $zprava = $this->doplnKartu($karta);
            } catch (\Throwable $throwable) {
                try {
                    $this->zaznamenejChybu($karta['ids'], $throwable);
                    $dalsiPokus = 'další pokus za den';
                } catch (\Throwable $chybaZaznamu) {
                    // the card is not held back for a day then, but the others still get their turn
                    $dalsiPokus = "chybu se nepodařilo zaznamenat ({$chybaZaznamu->getMessage()}), další pokus s příštím během";
                }
                $zprava = self::popis($karta) . " se nepodařilo založit, {$dalsiPokus}: {$throwable->getMessage()}";
                $chyby[] = $zprava;
                $prvniChyba ??= $throwable;
            }
            if ($zprava !== null) {
                $zaloguj($zprava);
            }
        }
        if ($chyby !== []) {
            throw new \RuntimeException(implode("\n", $chyby), 0, $prvniChyba);
        }
    }

    /**
     * @param array{idKarty: int, ids: list<int>, rok: int, nazev: string, vArchivu: bool, duvodSkryti: string|null,
     *     organizatori: list<array<string, mixed>>} $karta
     *
     * @return string|null what happened, null when there is nothing to tell
     */
    private function doplnKartu(array $karta): ?string
    {
        dbBegin();
        try {
            // the card is marked first: another run that is processing it at the same time waits on its rows here
            // and then leaves the card alone
            if (! $this->oznacZpracovanou($karta['ids'])) {
                dbRollback();

                return null;
            }
            $zprava = $this->zalozPrednasku($karta);
            dbCommit();

            return $zprava;
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }
    }

    /**
     * @param array{idKarty: int, ids: list<int>, rok: int, nazev: string, vArchivu: bool, duvodSkryti: string|null,
     *     organizatori: list<array<string, mixed>>} $karta
     */
    private function zalozPrednasku(array $karta): ?string
    {
        if ($karta['vArchivu']) {
            return null;
        }
        if ($this->repository->getIdPrednaskyPodleNazvu($karta['nazev'], $karta['rok']) !== null) {
            return self::popis($karta) . ' přeskočena, přednáška toho jména v archivu už je';
        }
        $idPrednasky = $this->repository->vlozPrednasku($karta['nazev'], $karta['rok'], null, $karta['idKarty'], $karta['duvodSkryti'] !== null);
        $this->repository->nastavPrednasejici(
            $idPrednasky,
            array_map($this->najdiNeboVlozPrednasejiciho(...), $karta['organizatori']),
        );

        return self::popis($karta) . ' přidána' . ($karta['duvodSkryti'] !== null ? ", skrytá, {$karta['duvodSkryti']}" : '');
    }

    /**
     * @param array{idKarty: int, rok: int, nazev: string} $karta
     */
    private static function popis(array $karta): string
    {
        return "Archiv přednášek {$karta['rok']}: „{$karta['nazev']}“ (aktivita {$karta['idKarty']})";
    }

    /**
     * Marks every activity of the card as processed and replaces a failure from before. One activity at a time in the
     * order of ids: two runs then lock the rows in the same order, so the second one waits for the first and finds
     * the card processed instead of a deadlock, and the affected rows of each statement tell whether another run
     * processed that activity (of one statement for all they would not). Only the key of the row is locked, no gap.
     *
     * @param list<int> $idsAkci
     *
     * @return bool false when another run processed the card in the meantime, or one of its activities was deleted;
     *              the next run loads the card again
     */
    private function oznacZpracovanou(array $idsAkci): bool
    {
        sort($idsAkci);
        foreach ($idsAkci as $idAkce) {
            // 1 = marked, 2 = a failure replaced, 0 = processed by another run (nothing changed) or no such activity
            $vysledek = dbQuery(<<<'SQL'
                INSERT INTO archiv_prednasek_zpracovane_aktivity (id_akce, zpracovano)
                SELECT akce_seznam.id_akce, $1
                FROM akce_seznam
                WHERE akce_seznam.id_akce = $0
                ON DUPLICATE KEY UPDATE
                    zpracovano = IF(archiv_prednasek_zpracovane_aktivity.chyba IS NULL, archiv_prednasek_zpracovane_aktivity.zpracovano, VALUES(zpracovano)),
                    chyba = NULL
                SQL, [
                0 => $idAkce,
                1 => $this->ted->format('Y-m-d H:i:s'),
            ]);
            if (dbAffectedOrNumRows($vysledek) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * An activity that another run processed in the meantime stays processed. The rows are locked in the order of
     * ids, as oznacZpracovanou() locks them.
     *
     * @param list<int> $idsAkci
     */
    private function zaznamenejChybu(array $idsAkci, \Throwable $throwable): void
    {
        // a message of the database can carry bytes of the failed value that are not UTF-8
        $chyba = mb_substr(mb_scrub($throwable->getMessage() ?: $throwable::class, 'UTF-8'), 0, 255);
        dbQuery(<<<'SQL'
            INSERT INTO archiv_prednasek_zpracovane_aktivity (id_akce, zpracovano, chyba)
            SELECT akce_seznam.id_akce, $1, $2
            FROM akce_seznam
            WHERE akce_seznam.id_akce IN ($0)
            ORDER BY akce_seznam.id_akce
            ON DUPLICATE KEY UPDATE
                zpracovano = IF(archiv_prednasek_zpracovane_aktivity.chyba IS NULL, archiv_prednasek_zpracovane_aktivity.zpracovano, VALUES(zpracovano)),
                chyba = IF(archiv_prednasek_zpracovane_aktivity.chyba IS NULL, NULL, VALUES(chyba))
            SQL, [
            0 => $idsAkci,
            1 => $this->ted->format('Y-m-d H:i:s'),
            2 => $chyba,
        ]);
    }

    /**
     * Cards of the type lecture in the years where at least one term took place and that are not decided yet.
     * The card is its main activity (akce_instance.id_hlavni_akce), its activities are the main one and its terms.
     * A decided card has a row for every one of its activities, the main one included, so the query leaves it out
     * by the row of its main activity; oznacZpracovanou() checks all of them.
     *
     * @param list<int> $rocniky
     *
     * @return list<array{idKarty: int, ids: list<int>, rok: int, nazev: string, vArchivu: bool, duvodSkryti: string|null,
     *     organizatori: list<array<string, mixed>>}>
     */
    private function getKartyKDoplneni(array $rocniky): array
    {
        $karty = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT COALESCE(akce_instance.id_hlavni_akce, akce_seznam.id_akce) AS id_karty,
                akce_seznam.id_akce, akce_seznam.rok, akce_seznam.nazev_akce, akce_seznam.cena, akce_seznam.stav
            FROM akce_seznam
            LEFT JOIN akce_instance ON akce_instance.id_instance = akce_seznam.patri_pod
            LEFT JOIN archiv_prednasek_zpracovane_aktivity
                ON archiv_prednasek_zpracovane_aktivity.id_akce = COALESCE(akce_instance.id_hlavni_akce, akce_seznam.id_akce)
                    AND (archiv_prednasek_zpracovane_aktivity.chyba IS NULL OR archiv_prednasek_zpracovane_aktivity.zpracovano > $2)
            WHERE akce_seznam.rok IN ($0) AND akce_seznam.typ = $1
                AND archiv_prednasek_zpracovane_aktivity.id_akce IS NULL
            ORDER BY akce_seznam.id_akce
            SQL, [
            0 => $rocniky,
            1 => TypAktivity::PREDNASKA,
            2 => \DateTimeImmutable::createFromInterface($this->ted)->modify('-' . self::OPAKOVAT_CHYBU_ZA)->format('Y-m-d H:i:s'),
        ]) as $radek) {
            $idKarty = (int) $radek['id_karty'];
            $idAkce = (int) $radek['id_akce'];
            $karty[$idKarty] ??= [
                'ids'      => [$idKarty],
                'rok'      => (int) $radek['rok'],
                'nazev'    => (string) $radek['nazev_akce'],
                'probehla' => false,
                'placena'  => false,
            ];
            if ($idAkce === $idKarty) {
                $karty[$idKarty]['rok'] = (int) $radek['rok'];
                $karty[$idKarty]['nazev'] = (string) $radek['nazev_akce'];
            } else {
                $karty[$idKarty]['ids'][] = $idAkce;
            }
            $karty[$idKarty]['probehla'] = $karty[$idKarty]['probehla']
                || in_array((int) $radek['stav'], StavAktivity::probehnuteStavy(), true);
            $karty[$idKarty]['placena'] = $karty[$idKarty]['placena'] || (int) $radek['cena'] > 0;
        }
        $karty = array_filter($karty, static fn (array $karta) => $karta['probehla']);
        if ($karty === []) {
            return [];
        }

        $idsAkci = array_merge(...array_column($karty, 'ids'));
        $vArchivu = $this->repository->getSparovaneAktivity($idsAkci);
        // the hourly cron locks every activity that started, even one that was never public (NOVA); someone
        // signing up, or a state of the public program in the log, is what shows that the card was in the program
        // (aktivuj(), publikuj() and priprav() do not write into the log, only the bulk activation and the locking
        // do; every lecture of 2022–2026 has a sign-up)
        $vProgramu = array_map('intval', dbFetchColumn(<<<'SQL'
            SELECT id_akce FROM akce_prihlaseni_log WHERE id_akce IN ($0)
            UNION
            SELECT id_akce FROM akce_stavy_log WHERE id_akce IN ($0) AND id_stav IN ($1)
            SQL, [
            0 => $idsAkci,
            1 => self::STAVY_V_PROGRAMU,
        ]));
        $stitky = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT akce_sjednocene_tagy.id_akce, sjednocene_tagy.nazev
            FROM akce_sjednocene_tagy
            JOIN sjednocene_tagy ON sjednocene_tagy.id = akce_sjednocene_tagy.id_tagu
            WHERE akce_sjednocene_tagy.id_akce IN ($0)
            SQL, [
            0 => array_keys($karty),
        ]) as $radek) {
            $stitky[(int) $radek['id_akce']][] = (string) $radek['nazev'];
        }
        $organizatori = $this->getOrganizatoriKaret($karty);

        $kDoplneni = [];
        foreach ($karty as $idKarty => $karta) {
            $nazev = NazevPrednasky::uprav($karta['nazev']);
            $duvodSkryti = match (true) {
                ! self::isPrednaska($nazev, $karta['placena'], $stitky[$idKarty] ?? []) => 'nevypadá na přednášku',
                array_intersect($karta['ids'], $vProgramu) === []                       => 'v programu asi nebyla, nikdo se na ni nepřihlásil',
                default                                                                 => null,
            };
            $kDoplneni[] = [
                'idKarty'      => $idKarty,
                'ids'          => $karta['ids'],
                'rok'          => $karta['rok'],
                'nazev'        => $nazev,
                'vArchivu'     => array_intersect($karta['ids'], $vArchivu) !== [],
                'duvodSkryti'  => $duvodSkryti,
                'organizatori' => $organizatori[$idKarty] ?? [],
            ];
        }

        return $kDoplneni;
    }

    /**
     * Organisers of all terms of each card, by user id, with what jmenoNaWebuZjisti() reads.
     *
     * @param array<int, array{ids: list<int>}> $karty by the id of the card
     *
     * @return array<int, list<array<string, mixed>>> by the id of the card
     */
    private function getOrganizatoriKaret(array $karty): array
    {
        $kartaAktivity = [];
        foreach ($karty as $idKarty => $karta) {
            foreach ($karta['ids'] as $idAkce) {
                $kartaAktivity[$idAkce] = $idKarty;
            }
        }
        $organizatori = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT akce_organizatori.id_akce, uzivatele_hodnoty.id_uzivatele, uzivatele_hodnoty.login_uzivatele,
                uzivatele_hodnoty.jmeno_uzivatele, uzivatele_hodnoty.prijmeni_uzivatele, uzivatele_hodnoty.zpusob_zobrazeni_na_webu
            FROM akce_organizatori
            JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = akce_organizatori.id_uzivatele
            WHERE akce_organizatori.id_akce IN ($0)
            ORDER BY uzivatele_hodnoty.id_uzivatele
            SQL, [
            0 => array_keys($kartaAktivity),
        ]) as $radek) {
            $organizatori[$kartaAktivity[(int) $radek['id_akce']]][(int) $radek['id_uzivatele']] = $radek;
        }

        return array_map('array_values', $organizatori);
    }

    /**
     * Type lecture holds also live play, paid workshops, the organisational program or a cancelled activity; such
     * a card goes into the archive hidden, so that an administrator sees it and shows it with one click if it is
     * a lecture after all (záměr).
     *
     * @param list<string> $stitky
     */
    public static function isPrednaska(string $nazev, bool $placena, array $stitky): bool
    {
        foreach (self::NAZVY_NEPREDNASKY as $vzor) {
            if (preg_match($vzor, $nazev)) {
                return false;
            }
        }

        return ! $placena
            && array_intersect($stitky, self::STITKY_NEPREDNASKY) === []
            && (! in_array(self::STITEK_WORKSHOP, $stitky, true) || array_intersect($stitky, self::STITKY_PREDNASKY) !== []);
    }

    /**
     * The speaker already linked to the organiser, otherwise the speaker of the same name (záměr: the same name is
     * the same speaker, and a group or company stays without a user even when it organises from its own account),
     * otherwise a new one by the organiser's name on the web, linked to him.
     *
     * @param array<string, mixed> $organizator a row of uzivatele_hodnoty
     */
    private function najdiNeboVlozPrednasejiciho(array $organizator): int
    {
        $idUzivatele = (int) $organizator['id_uzivatele'];
        $jmeno = mb_substr(\Uzivatel::jmenoNaWebuZjisti($organizator), 0, 255);

        return $this->repository->getIdPrednasejicihoPodleUzivatele($idUzivatele)
            ?? $this->repository->getIdPrednasejicihoPodleJmena($jmeno)
            ?? $this->repository->vlozPrednasejiciho($jmeno, $idUzivatele);
    }
}
