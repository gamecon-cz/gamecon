<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

use Gamecon\Aktivita\RocnikyAktivit;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\ArchivPrednasek\SqlStruktura\PrednaskaSqlStruktura as PrednaskaSql;

/**
 * Lectures of the public archive (/prednasky/archiv): not hidden, from the newest year, by Czech alphabet within a year.
 */
final class PrednaskyNaWebu
{
    /**
     * Tags of these categories (and their subcategories) say who may come, not what the lecture is about.
     */
    private const string KATEGORIE_OMEZENI = 'omezení';

    /**
     * @var array<int, list<int>> id of the type => years its line page offers
     */
    private array $nabizeneRocniky = [];

    /**
     * @var array<int, TypAktivity|null>
     */
    private array $typy = [];

    public function __construct(
        private readonly int $letosniRocnik,
        private readonly TypAktivity $liniePrednasek,
    ) {
    }

    /**
     * @return list<PrednaskaNaWebu>
     */
    public function getPrednasky(): array
    {
        $radky = dbFetchAll(<<<'SQL'
            SELECT archiv_prednasek.id_prednasky, archiv_prednasek.nazev, archiv_prednasek.rok, archiv_prednasek.youtube_id,
                archiv_prednasek.id_akce, akce_seznam.typ, akce_seznam.stav, akce_seznam.rok AS rok_akce,
                akce_seznam.popis_kratky, akce_seznam.popis,
                -- the card of a group of instances has the address of one of them
                COALESCE(
                    NULLIF(akce_seznam.url_akce, ''),
                    (SELECT MAX(instance.url_akce) FROM akce_seznam AS instance WHERE instance.patri_pod = akce_seznam.patri_pod)
                ) AS url_akce
            FROM archiv_prednasek
            LEFT JOIN akce_seznam ON akce_seznam.id_akce = archiv_prednasek.id_akce
            WHERE archiv_prednasek.skryta = 0
            SQL);
        if ($radky === []) {
            return [];
        }

        $prednasejici = $this->prednasejiciPodlePrednasek();
        $klicovaSlova = $this->klicovaSlovaPodlePrednasek();
        $idsAkci = array_values(array_filter(array_map(
            static fn (array $radek) => isset($radek[PrednaskaSql::ID_AKCE])
                ? (int) $radek[PrednaskaSql::ID_AKCE]
                : null,
            $radky,
        )));
        $stitkyProgramu = $this->stitkyProgramuPodleAkci($idsAkci);

        $prednasky = [];
        foreach ($radky as $radek) {
            $id = (int) $radek[PrednaskaSql::ID_PREDNASKY];
            $rok = (int) $radek[PrednaskaSql::ROK];
            $idAkce = isset($radek[PrednaskaSql::ID_AKCE])
                ? (int) $radek[PrednaskaSql::ID_AKCE]
                : null;
            $kratkaAnotace = $idAkce !== null
                ? trim((string) $radek['popis_kratky'])
                : '';
            $dlouhaAnotace = $idAkce !== null
                ? trim((string) $radek['popis'])
                : '';

            $prednasky[] = new PrednaskaNaWebu(
                id: $id,
                nazev: (string) $radek[PrednaskaSql::NAZEV],
                rok: $rok,
                youtubeId: $radek[PrednaskaSql::YOUTUBE_ID] ?: null,
                prednasejici: $prednasejici[$id] ?? [],
                stitky: $this->sloucene($klicovaSlova[$id] ?? [], $idAkce !== null ? ($stitkyProgramu[$idAkce] ?? []) : []),
                kratkaAnotace: $kratkaAnotace !== ''
                    ? $kratkaAnotace
                    : null,
                dlouhaAnotace: $dlouhaAnotace !== ''
                    ? markdown($dlouhaAnotace)
                    : null,
                urlRoku: $this->urlRoku($rok),
                urlProgramu: $idAkce !== null
                    ? $this->urlProgramu((int) $radek['typ'], (int) $radek['stav'], (int) $radek['rok_akce'], (string) $radek['url_akce'])
                    : null,
            );
        }

        $collator = new \Collator('cs');
        usort(
            $prednasky,
            static fn (PrednaskaNaWebu $a, PrednaskaNaWebu $b) => $b->rok <=> $a->rok ?: $collator->compare($a->nazev, $b->nazev),
        );

        return $prednasky;
    }

    /**
     * The year on the line page of lectures, but only a year its switcher offers; another one would quietly switch
     * to the current year there (2008 is not in the program, 2020 GameCon did not take place).
     */
    private function urlRoku(int $rok): ?string
    {
        if (! in_array($rok, $this->nabizeneRocniky($this->liniePrednasek->id()), true)) {
            return null;
        }

        return $this->liniePrednasek->url() . ($rok === $this->letosniRocnik
            ? ''
            : '?rocnik=' . $rok);
    }

    /**
     * The card on the page of the line of the activity in its year, if that page shows it (a visible state,
     * not a finished technical one, a year its switcher offers).
     */
    private function urlProgramu(int $idTypu, int $stav, int $rok, string $urlAkce): ?string
    {
        $typ = $this->typy[$idTypu] ??= TypAktivity::zId($idTypu);
        if ($typ === null
            || ! in_array($stav, StavAktivity::bezneViditelneStavy(), true)
            || (TypAktivity::jeInterniDleId($idTypu) && in_array($stav, StavAktivity::probehnuteStavy(), true))
            || ! in_array($rok, $this->nabizeneRocniky($idTypu), true)
        ) {
            return null;
        }

        return $typ->url()
            . ($rok === $this->letosniRocnik
                ? ''
                : '?rocnik=' . $rok)
            . ($urlAkce !== ''
                ? '#' . $urlAkce
                : '');
    }

    /**
     * @return list<int>
     */
    private function nabizeneRocniky(int $idTypu): array
    {
        return $this->nabizeneRocniky[$idTypu] ??= RocnikyAktivit::getNabizene($this->letosniRocnik, $idTypu);
    }

    /**
     * @param list<string>                                  $klicovaSlova
     * @param list<array{nazev: string, kategorie: string}> $stitkyProgramu
     *
     * @return list<StitekNaWebu>
     */
    private function sloucene(array $klicovaSlova, array $stitkyProgramu): array
    {
        $stitky = [];
        foreach ($klicovaSlova as $nazev) {
            $stitky[StitekNaWebu::getKlic($nazev)] ??= new StitekNaWebu(StitekNaWebu::getKlic($nazev), $nazev, StitekNaWebu::SKUPINA_KLICOVA_SLOVA);
        }
        foreach ($stitkyProgramu as ['nazev' => $nazev, 'kategorie' => $kategorie]) {
            $stitky[StitekNaWebu::getKlic($nazev)] ??= new StitekNaWebu(StitekNaWebu::getKlic($nazev), $nazev, $kategorie);
        }

        return array_values($stitky);
    }

    /**
     * Speakers with what Uzivatel::url() reads of their users, in one query: the page shows every speaker and
     * a whole user for each of them would be a waste.
     *
     * @return array<int, list<PrednasejiciNaWebu>> id of the lecture => speakers in their order
     */
    private function prednasejiciPodlePrednasek(): array
    {
        $podlePrednasek = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT archiv_prednasek_prednaska_prednasejici.id_prednasky, archiv_prednasek_prednasejici.id_prednasejiciho,
                archiv_prednasek_prednasejici.jmeno, uzivatele_hodnoty.id_uzivatele, uzivatele_hodnoty.login_uzivatele,
                uzivatele_hodnoty.jmeno_uzivatele, uzivatele_hodnoty.prijmeni_uzivatele,
                (
                    SELECT uzivatele_url.url
                    FROM uzivatele_url
                    WHERE uzivatele_url.id_uzivatele = uzivatele_hodnoty.id_uzivatele
                    ORDER BY uzivatele_url.id_url_uzivatele DESC
                    LIMIT 1
                ) AS url
            FROM archiv_prednasek_prednaska_prednasejici
            JOIN archiv_prednasek_prednasejici
                ON archiv_prednasek_prednasejici.id_prednasejiciho = archiv_prednasek_prednaska_prednasejici.id_prednasejiciho
            JOIN archiv_prednasek ON archiv_prednasek.id_prednasky = archiv_prednasek_prednaska_prednasejici.id_prednasky
            LEFT JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = archiv_prednasek_prednasejici.id_uzivatele
            WHERE archiv_prednasek.skryta = 0
            ORDER BY archiv_prednasek_prednaska_prednasejici.id_prednasky, archiv_prednasek_prednaska_prednasejici.poradi
            SQL) as $radek) {
            $podlePrednasek[(int) $radek['id_prednasky']][] = new PrednasejiciNaWebu(
                id: (int) $radek['id_prednasejiciho'],
                jmeno: (string) $radek['jmeno'],
                // same as the line page: a profile only for an account with a name, a group or a company has none
                urlMedailonku: isset($radek['id_uzivatele']) && \Uzivatel::urlZjisti($radek) !== null
                    ? (string) $radek['id_uzivatele']
                    : null,
            );
        }

        return $podlePrednasek;
    }

    /**
     * @return array<int, list<string>> id of the lecture => keywords by Czech alphabet
     */
    private function klicovaSlovaPodlePrednasek(): array
    {
        $collator = new \Collator('cs');
        $podlePrednasek = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT archiv_prednasek_prednaska_klicove_slovo.id_prednasky, archiv_prednasek_klicove_slovo.nazev
            FROM archiv_prednasek_prednaska_klicove_slovo
            JOIN archiv_prednasek_klicove_slovo
                ON archiv_prednasek_klicove_slovo.id_klicoveho_slova = archiv_prednasek_prednaska_klicove_slovo.id_klicoveho_slova
            SQL) as $radek) {
            $podlePrednasek[(int) $radek['id_prednasky']][] = (string) $radek['nazev'];
        }
        foreach ($podlePrednasek as &$slova) {
            $collator->sort($slova);
        }
        unset($slova);

        return $podlePrednasek;
    }

    /**
     * @param list<int> $idsAkci
     *
     * @return array<int, list<array{nazev: string, kategorie: string}>> id of the activity => its tags, by the order of
     *                                                                   the category and by name like on the line page, without restrictions (age, height)
     */
    private function stitkyProgramuPodleAkci(array $idsAkci): array
    {
        if ($idsAkci === []) {
            return [];
        }
        $podleAkci = [];
        foreach (dbFetchAll(<<<'SQL'
            SELECT akce_sjednocene_tagy.id_akce, sjednocene_tagy.nazev, kategorie_sjednocenych_tagu.nazev AS kategorie
            FROM akce_sjednocene_tagy
            JOIN sjednocene_tagy ON sjednocene_tagy.id = akce_sjednocene_tagy.id_tagu
            JOIN kategorie_sjednocenych_tagu ON kategorie_sjednocenych_tagu.id = sjednocene_tagy.id_kategorie_tagu
            LEFT JOIN kategorie_sjednocenych_tagu AS hlavni_kategorie
                ON hlavni_kategorie.id = kategorie_sjednocenych_tagu.id_hlavni_kategorie
            WHERE akce_sjednocene_tagy.id_akce IN ($0)
                AND kategorie_sjednocenych_tagu.nazev != $1
                AND (hlavni_kategorie.nazev IS NULL OR hlavni_kategorie.nazev != $1)
            ORDER BY kategorie_sjednocenych_tagu.poradi, sjednocene_tagy.nazev
            SQL, [
            0 => $idsAkci,
            1 => self::KATEGORIE_OMEZENI,
        ]) as $radek) {
            $podleAkci[(int) $radek['id_akce']][] = [
                'nazev'     => (string) $radek['nazev'],
                'kategorie' => (string) $radek['kategorie'],
            ];
        }

        return $podleAkci;
    }
}
