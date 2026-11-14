<?php

declare(strict_types=1);

namespace Gamecon\Report;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\KategorieNeplatice;

/**
 * Letošní účastníci s kategorií neplatiče pro nejbližší hromadné odhlašování,
 * aby se dali před odhlášením projít pohledem. Kdo nic nedluží (kategorie 7), v reportu není.
 *
 * Datum poslední platby a přihlášení na GC je bez času, jak chtěla karta 1280.
 *
 * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
 * https://trello.com/c/dof82xp5/1280-report-neplati%C4%8D%C5%AF
 * https://docs.google.com/document/d/1pP3mp9piPNAl1IKCC5YYe92zzeFdTLDMiT-xrUhVLdQ/edit
 */
class FinanceReportNeplaticu
{
    /**
     * Jediný seznam sloupců. Z něj jsou hlavičky i pořadí buněk v řádcích, protože
     * Report zapisuje buňky podle pozice, ne podle klíče.
     */
    private const array SLOUPCE = [
        'id_uzivatele',
        'nick',
        'jmeno_uzivatele',
        'prijmeni_uzivatele',
        'uzivatel_v_adminu',
        'email',
        'role',
        'suma_plateb',
        'kategorie_neplatice',
        'aktualni_zustatek',
        'datum_posledni_platby',
        'prihlaseni_na_letosni_gc',
        'hromadne_odhlaseni',
    ];

    /**
     * V HTML vede do adminu odkaz z ID, sloupec s adresou by tam byl navíc.
     */
    private const array SLOUPCE_JEN_MIMO_HTML = ['uzivatel_v_adminu'];

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
    }

    public function exportuj(?string $format): void
    {
        $report = \Report::zPoli(
            $this->getSloupce($format),
            $this->getRadky(\Uzivatel::zPrihlasenych(), $format),
        );

        $report->tFormat(
            $format,
            'datum_hromadneho_odhlasovani_'
            . $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy()->format(DateTimeCz::FORMAT_DB)
            . '-' . $report->nazevReportuZRequestu(),
            (new KonfiguraceReportu())->setStrippingTagsInCsv(false),
        );
    }

    /**
     * @return list<string>
     */
    public function getSloupce(?string $format): array
    {
        return $this->isHtml($format)
            ? array_values(array_diff(self::SLOUPCE, self::SLOUPCE_JEN_MIMO_HTML))
            : self::SLOUPCE;
    }

    /**
     * Řádky seřazené podle kategorie neplatiče, zůstatku a ID, klíče v pořadí podle {@see getSloupce()}.
     *
     * @param iterable<\Uzivatel> $ucastnici
     *
     * @return list<array<string, int|float|string|null>>
     */
    public function getRadky(
        iterable $ucastnici,
        ?string $format,
    ): array {
        $hodnotyUcastniku = [];
        foreach ($ucastnici as $ucastnik) {
            $finance = $ucastnik->finance();
            $kategorieNeplatice = $finance->kategorieNeplatice();
            $ciselnaKategorie = $kategorieNeplatice->ciselnaKategoriiNeplatice();
            if ($ciselnaKategorie === KategorieNeplatice::NEDLUZNIK) {
                continue;
            }

            $datumPosledniPlatby = $finance->datumPosledniPlatby();

            $hodnotyUcastniku[] = [
                'id_uzivatele'             => $ucastnik->id(),
                'nick'                     => $ucastnik->nick(),
                'jmeno_uzivatele'          => $ucastnik->krestniJmeno(),
                'prijmeni_uzivatele'       => $ucastnik->prijmeni(),
                'uzivatel_v_adminu'        => $this->getUrlUzivatele($ucastnik->id()),
                'email'                    => (string) $ucastnik->mail(),
                'role'                     => $this->getRole($ucastnik),
                'suma_plateb'              => $finance->sumaPlateb($this->systemoveNastaveni->rocnik()),
                'kategorie_neplatice'      => $ciselnaKategorie,
                'aktualni_zustatek'        => $finance->stav(),
                'datum_posledni_platby'    => $datumPosledniPlatby
                    ? (new DateTimeCz($datumPosledniPlatby))->format(DateTimeCz::FORMAT_DATUM_DB)
                    : '',
                'prihlaseni_na_letosni_gc' => $ucastnik->kdySeRegistrovalNaLetosniGc()
                    ?->format(DateTimeCz::FORMAT_DATUM_DB) ?? '',
                'hromadne_odhlaseni'       => $kategorieNeplatice->zacatekVlnyOdhlasovani()
                    ?->format(DateTimeCz::FORMAT_DB) ?? '',
            ];
        }

        usort($hodnotyUcastniku, static function (array $nejakyUcastnik, array $jinyUcastnik): int {
            return [
                $nejakyUcastnik['kategorie_neplatice'] ?? PHP_INT_MAX,
                $nejakyUcastnik['aktualni_zustatek'],
                $nejakyUcastnik['id_uzivatele'],
            ] <=> [
                $jinyUcastnik['kategorie_neplatice'] ?? PHP_INT_MAX,
                $jinyUcastnik['aktualni_zustatek'],
                $jinyUcastnik['id_uzivatele'],
            ];
        });

        $sloupce = $this->getSloupce($format);
        $isHtml = $this->isHtml($format);

        return array_map(
            function (array $hodnoty) use ($sloupce, $isHtml): array {
                $radek = [];
                foreach ($sloupce as $sloupec) {
                    $radek[$sloupec] = $hodnoty[$sloupec];
                }

                return $isHtml
                    ? $this->doHtml($radek, $hodnoty['uzivatel_v_adminu'])
                    : $radek;
            },
            $hodnotyUcastniku,
        );
    }

    /**
     * Stejně jako {@see \Report::tFormat()}, jinak by si „html “ s mezerou report vypsal jako HTML,
     * ale buňky by neescapoval.
     */
    private function isHtml(?string $format): bool
    {
        return trim((string) $format) === 'html';
    }

    private function getRole(\Uzivatel $ucastnik): string
    {
        return implode(
            ',',
            array_map(
                static fn (int $role) => Role::nazevRolePodleId($role),
                array_filter(
                    $ucastnik->dejIdsRoli(),
                    static fn (int $role) => ! Role::jeToUcastNaGc($role),
                ),
            ),
        );
    }

    private function getUrlUzivatele(int $idUzivatele): string
    {
        return URL_ADMIN . '/uzivatel?pracovni_uzivatel=' . $idUzivatele;
    }

    /**
     * HTML report vypisuje buňky bez escapování, proto tady escapujeme všechny
     * a odkazy skládáme až z escapovaných hodnot.
     *
     * @param array<string, int|float|string|null> $radek
     *
     * @return array<string, string>
     */
    private function doHtml(
        array $radek,
        string $urlUzivatele,
    ): array {
        $radek = array_map(
            static fn (int|float|string|null $bunka): string => htmlspecialchars((string) $bunka),
            $radek,
        );
        $url = htmlspecialchars($urlUzivatele);

        $radek['id_uzivatele'] = "<a target='_blank' href='{$url}'>{$radek['id_uzivatele']}</a>";
        if ($radek['email'] !== '') {
            $radek['email'] = "<a href='mailto:{$radek['email']}'>{$radek['email']}</a>";
        }

        return $radek;
    }
}
