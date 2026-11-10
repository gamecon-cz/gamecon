<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * After a GameCon ends, its lectures go into the archive by themselves: one lecture per card of the program
 * of the type lecture that took place, with its title, year, pairing and speakers (the organisers). Keywords and
 * the YouTube link are added by an administrator.
 *
 * Every year is filled in only once (archiv_prednasek_doplnene_rocniky): existing lectures are never changed,
 * a hidden one does not come back and a card whose pairing an administrator cancelled is not added again.
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
     * a card with such a tag is not a lecture (live play, a stream)
     */
    private const array STITKY_NEPREDNASKY = ['Živé hraní'];

    /**
     * a workshop without one of these tags is not a lecture
     */
    private const array STITKY_PREDNASKY = ['Talk', 'Panelová diskuse', 'Debata'];
    private const string STITEK_WORKSHOP = 'Workshop';

    private readonly ArchivPrednasekRepository $repository;

    public function __construct(
        private readonly int $letosniRocnik,
        private readonly \DateTimeInterface $ted,
        private readonly \DateTimeInterface $gcBeziDo,
    ) {
        $this->repository = new ArchivPrednasekRepository();
    }

    public static function zNastaveni(SystemoveNastaveni $systemoveNastaveni): self
    {
        return new self($systemoveNastaveni->rocnik(), $systemoveNastaveni->ted(), $systemoveNastaveni->gcBeziDo());
    }

    /**
     * Years whose GameCon ended and that are not filled in yet; the past year counts even after New Year,
     * when the current year is already the next one (záměr: 2026 must get in right after the deploy).
     *
     * @return list<int>
     */
    public function rocnikyKDoplneni(): array
    {
        $posledniSkonceny = $this->ted > $this->gcBeziDo
            ? $this->letosniRocnik
            : $this->letosniRocnik - 1;
        if ($posledniSkonceny < self::PRVNI_ROCNIK) {
            return [];
        }
        $doplnene = array_map('intval', dbOneArray(<<<'SQL'
            SELECT rok FROM archiv_prednasek_doplnene_rocniky
            SQL));

        return array_values(array_diff(range(self::PRVNI_ROCNIK, $posledniSkonceny), $doplnene));
    }

    /**
     * @return list<string> what happened, for the log of the cron
     */
    public function doplnSkoncene(): array
    {
        $zpravy = [];
        foreach ($this->rocnikyKDoplneni() as $rok) {
            $zpravy = [...$zpravy, ...$this->doplnRocnik($rok)];
        }

        return $zpravy;
    }

    /**
     * @return list<string> what happened, for the log of the cron
     */
    public function doplnRocnik(int $rok): array
    {
        $zpravy = [];
        dbBegin();
        try {
            foreach ($this->kartyKDoplneni($rok) as $karta) {
                $stejnaPrednaska = dbOneCol(<<<'SQL'
                    SELECT id_prednasky FROM archiv_prednasek WHERE rok = $0 AND nazev = $1
                    SQL, [
                    0 => $rok,
                    1 => $karta['nazev'],
                ]);
                if ($stejnaPrednaska) {
                    $zpravy[] = "Archiv přednášek {$rok}: „{$karta['nazev']}“ (aktivita {$karta['idAkce']}) přeskočena, přednáška toho jména v archivu už je";
                    continue;
                }
                $idPrednasky = $this->repository->vlozPrednasku($karta['nazev'], $rok, null, $karta['idAkce'], $karta['neprednaska']);
                $this->repository->nastavPrednasejici(
                    $idPrednasky,
                    array_map($this->idPrednasejicihoUzivatele(...), $karta['organizatori']),
                );
                $zpravy[] = "Archiv přednášek {$rok}: přidána „{$karta['nazev']}“ (aktivita {$karta['idAkce']})"
                    . ($karta['neprednaska'] ? ', skrytá, nevypadá na přednášku' : '');
            }
            dbQuery(<<<'SQL'
                INSERT INTO archiv_prednasek_doplnene_rocniky (rok) VALUES ($0)
                SQL, [
                0 => $rok,
            ]);
            dbCommit();
        } catch (\Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }

        return $zpravy;
    }

    /**
     * Cards of the type lecture in the year where at least one term took place and none of the activities
     * of the card is in the archive yet.
     *
     * @return list<array{idAkce: int, nazev: string, neprednaska: bool, organizatori: list<array<string, mixed>>}>
     */
    private function kartyKDoplneni(int $rok): array
    {
        $karty = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT COALESCE(akce_instance.id_hlavni_akce, akce_seznam.id_akce) AS id_karty,
                akce_seznam.id_akce, akce_seznam.nazev_akce, akce_seznam.cena, akce_seznam.stav
            FROM akce_seznam
            LEFT JOIN akce_instance ON akce_instance.id_instance = akce_seznam.patri_pod
            WHERE akce_seznam.rok = $0 AND akce_seznam.typ = $1
            ORDER BY akce_seznam.id_akce
            SQL, [
            0 => $rok,
            1 => TypAktivity::PREDNASKA,
        ]) as $radek) {
            $idKarty = (int) $radek['id_karty'];
            $karty[$idKarty] ??= [
                'ids'      => [],
                'nazev'    => (string) $radek['nazev_akce'],
                'probehla' => false,
                'placena'  => false,
            ];
            $karty[$idKarty]['ids'][] = (int) $radek['id_akce'];
            if ((int) $radek['id_akce'] === $idKarty) {
                $karty[$idKarty]['nazev'] = (string) $radek['nazev_akce'];
            }
            $karty[$idKarty]['probehla'] = $karty[$idKarty]['probehla']
                || in_array((int) $radek['stav'], StavAktivity::probehnuteStavy(), true);
            $karty[$idKarty]['placena'] = $karty[$idKarty]['placena'] || (int) $radek['cena'] > 0;
        }
        $karty = array_filter($karty, static fn (array $karta) => $karta['probehla']);
        if ($karty === []) {
            return [];
        }

        $vArchivu = array_map('intval', dbOneArray(<<<'SQL'
            SELECT id_akce FROM archiv_prednasek WHERE id_akce IN ($0)
            SQL, [
            0 => array_merge(...array_column($karty, 'ids')),
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

        $kDoplneni = [];
        foreach ($karty as $idKarty => $karta) {
            if (array_intersect($karta['ids'], $vArchivu) !== []) {
                continue;
            }
            $nazev = NazevPrednasky::uprav($karta['nazev']);
            $kDoplneni[] = [
                'idAkce'       => $idKarty,
                'nazev'        => $nazev,
                'neprednaska'  => self::nevypadaNaPrednasku($nazev, $karta['placena'], $stitky[$idKarty] ?? []),
                'organizatori' => dbFetchAll(<<<'SQL'
                    SELECT DISTINCT uzivatele_hodnoty.*
                    FROM akce_organizatori
                    JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = akce_organizatori.id_uzivatele
                    WHERE akce_organizatori.id_akce IN ($0)
                    ORDER BY uzivatele_hodnoty.id_uzivatele
                    SQL, [
                    0 => $karta['ids'],
                ]),
            ];
        }

        return $kDoplneni;
    }

    /**
     * Type lecture holds also live play, paid workshops or a cancelled activity; such a card goes into the archive
     * hidden, so that an administrator sees it and shows it with one click if it is a lecture after all (záměr).
     *
     * @param list<string> $stitky
     */
    public static function nevypadaNaPrednasku(string $nazev, bool $placena, array $stitky): bool
    {
        return $placena
            || mb_stripos($nazev, 'zrušeno') !== false
            || array_intersect($stitky, self::STITKY_NEPREDNASKY) !== []
            || (in_array(self::STITEK_WORKSHOP, $stitky, true) && array_intersect($stitky, self::STITKY_PREDNASKY) === []);
    }

    /**
     * The speaker already linked to the organiser, otherwise a new one by his name on the web.
     *
     * @param array<string, mixed> $organizator a row of uzivatele_hodnoty
     */
    private function idPrednasejicihoUzivatele(array $organizator): int
    {
        $idUzivatele = (int) $organizator['id_uzivatele'];
        $idPrednasejiciho = dbOneCol(<<<'SQL'
            SELECT id_prednasejiciho
            FROM archiv_prednasek_prednasejici
            WHERE id_uzivatele = $0
            ORDER BY id_prednasejiciho
            LIMIT 1
            SQL, [
            0 => $idUzivatele,
        ]);

        return $idPrednasejiciho
            ? (int) $idPrednasejiciho
            : $this->repository->vlozPrednasejiciho(\Uzivatel::jmenoNaWebuZjisti($organizator), $idUzivatele);
    }
}
