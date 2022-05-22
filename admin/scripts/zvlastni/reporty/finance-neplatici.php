<?php
require_once __DIR__ . '/sdilene-hlavicky.php';

/** https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF */

$formatReportu = get('format');

$idsLetosnichUcastniku = dbOneArray(<<<SQL
SELECT id_uzivatele
FROM r_uzivatele_zidle
WHERE id_zidle <= $0
SQL,
    [ZIDLE_PRIHLASEN]
);

$data = [];
foreach ($idsLetosnichUcastniku as $idLetosnihoUcastnika) {
    $ucastnikData = [];
    $letosniUcastnik = Uzivatel::zId($idLetosnihoUcastnika);
    $ucastnikData['id_uzivatele'] = $letosniUcastnik->id();
    $ucastnikData['email'] = $formatReportu === 'html' && $letosniUcastnik->mail()
        ? "<a href='mailto:{$letosniUcastnik->mail()}'>{$letosniUcastnik->mail()}</a>"
        : $letosniUcastnik->mail();
    $ucastnikData['telefon'] = $formatReportu === 'html' && $letosniUcastnik->telefon()
        ? "<a href='tel:{$letosniUcastnik->telefon()}'>{$letosniUcastnik->telefon()}</a>"
        : $letosniUcastnik->telefon();
    $ucastnikData['aktualni_zustatek'] = $letosniUcastnik->finance()->stav();
    $ucastnikData['datum_posledni_platby'] = $letosniUcastnik->finance()->datumPosledniPlatby()
        ? (new \Gamecon\Cas\DateTimeCz($letosniUcastnik->finance()->datumPosledniPlatby()))->formatCasStandard()
        : '';
    $ucastnikData['kategorie_neplatice'] = $letosniUcastnik->finance()->dejKategoriiNeplatice()->;
    $urlUzivatele = URL_ADMIN . '/uvod?pracovni_uzivatel=' . $letosniUcastnik->id();
    $ucastnikData['uzivatel_v_adminu'] = $formatReportu === 'html'
        ? "<a target='_blank' href='$urlUzivatele'>$urlUzivatele</a>"
        : $urlUzivatele;
    $data[] = $ucastnikData;
}

$report = !empty($data)
    ? Report::zPole($data)
    : Report::zPoli([], []);
$report->tFormat($formatReportu);
