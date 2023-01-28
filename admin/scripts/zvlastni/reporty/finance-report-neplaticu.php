<?php declare(strict_types=1);

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;

require_once __DIR__ . '/sdilene-hlavicky.php';

/**
 * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
 *
 * https://docs.google.com/document/d/1pP3mp9piPNAl1IKCC5YYe92zzeFdTLDMiT-xrUhVLdQ/edit
 */

$formatReportu = get('format');

$data = [];
foreach (Uzivatel::zPrihlasenych() as $letosniUcastnik) {
    $ucastnikData = [];

    $ucastnikData['id_uzivatele'] = $letosniUcastnik->id();
    $urlUzivatele                 = URL_ADMIN . '/uzivatel?pracovni_uzivatel=' . $letosniUcastnik->id();

    if ($formatReportu === 'html') {
        $ucastnikData['jmeno'] = "<a target='_blank' href='$urlUzivatele'>{$letosniUcastnik->jmenoNick()}</a>";
    } else {
        $ucastnikData['jmeno']             = $letosniUcastnik->jmenoNick();
        $ucastnikData['uzivatel_v_adminu'] = $urlUzivatele;
    }

    $ucastnikData['email']   = $formatReportu === 'html' && $letosniUcastnik->mail()
        ? "<a href='mailto:{$letosniUcastnik->mail()}'>{$letosniUcastnik->mail()}</a>"
        : $letosniUcastnik->mail();
    $ucastnikData['telefon'] = $formatReportu === 'html' && $letosniUcastnik->telefon()
        ? "<a href='tel:{$letosniUcastnik->telefon()}'>{$letosniUcastnik->telefon()}</a>"
        : $letosniUcastnik->telefon();

    $ucastnikData['role'] = implode(
        ',',
        array_map(
            static function (int $zidle) {
                return \Gamecon\Zidle::nazevZidle($zidle);
            },
            array_filter(
                $letosniUcastnik->dejIdsZidli(),
                static function (int $zidle) {
                    return !\Gamecon\Zidle::jeToRocnikovaUdalostNaGc($zidle);
                }
            )
        ));

    $finance            = $letosniUcastnik->finance();
    $kategorieNeplatice = $finance->kategorieNeplatice();

    $ucastnikData['suma_plateb']         = $finance->sumaPlateb(ROK);
    $ucastnikData['kategorie_neplatice'] = $kategorieNeplatice->dejCiselnouKategoriiNeplatice();

    $ucastnikData['aktualni_zustatek']     = $finance->stav();
    $ucastnikData['datum_posledni_platby'] = $finance->datumPosledniPlatby()
        ? (new \Gamecon\Cas\DateTimeCz($finance->datumPosledniPlatby()))->formatCasStandard()
        : '';

    $ucastnikData['prihlaseni_na_letosni_gc'] = $letosniUcastnik->kdySeRegistrovalNaLetosniGc()
        ? $letosniUcastnik->kdySeRegistrovalNaLetosniGc()->format(DateTimeCz::FORMAT_DATUM_A_CAS_STANDARD)
        : '';

    $ucastnikData['hromadne_odhlaseni'] = $kategorieNeplatice->zacatekVlnyOdhlasovani()
        ? $kategorieNeplatice->zacatekVlnyOdhlasovani()->format(DateTimeCz::FORMAT_DATUM_A_CAS_STANDARD)
        : '';

    $data[] = $ucastnikData;
}

usort($data, static function (array $nejakyUcastnik, array $jinyUcastnik) {
    $rozdilneKategorie = ($nejakyUcastnik['kategorie_neplatice'] ?? PHP_INT_MAX) <=> ($jinyUcastnik['kategorie_neplatice'] ?? PHP_INT_MAX);
    if ($rozdilneKategorie !== 0) {
        return $rozdilneKategorie;
    }
    $rozdilneZustatky = $nejakyUcastnik['aktualni_zustatek'] <=> $jinyUcastnik['aktualni_zustatek'];
    if ($rozdilneZustatky !== 0) {
        return $rozdilneZustatky;
    }
    return $nejakyUcastnik['id_uzivatele'] <=> $jinyUcastnik['id_uzivatele'];
});

$report = !empty($data)
    ? Report::zPole($data)
    : Report::zPoli([], []);

$report->tFormat(
    $formatReportu,
    'datum_hromadneho_odhlasovani_'
    . DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani()->format(DateTimeCz::FORMAT_DB)
    . '-' . $report->nazevReportuZRequestu()
);
