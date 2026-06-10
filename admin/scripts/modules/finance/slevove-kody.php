<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Cas\DateTimeCz;

/**
 * nazev: Slevové poukazy 🎟️
 * pravo: 108
 * submenu_group: 5
 */

/** @var Uzivatel $u */

require_once __DIR__ . '/../../zvlastni/reporty/_slevovy_poukaz.php';

// --- zpracování akcí ---

if (post('vygenerovat')) {
    $novyKod = vygenerujSlevovyKod($u->id());
    oznameni('Byl vygenerován nový poukaz: ' . $novyKod . '. V seznamu níže ho můžeš zobrazit a vytisknout.');
}

if (post('zneplatnitId')) {
    $id = (int)post('zneplatnitId');
    // zneplatnit jde jen dosud nepoužitý kód – použitý už slevu udělil a měnit ho nemá smysl
    $zmeneno = dbAffectedOrNumRows(dbQuery(
        'UPDATE slevove_kody SET invalidated = 1 WHERE id = $0 AND usedAt IS NULL',
        [$id],
    ));
    if ($zmeneno) {
        oznameni('Poukaz byl zneplatněn.');
    } else {
        chyba('Poukaz nešlo zneplatnit – buď neexistuje, nebo už byl uplatněný.');
    }
}

if (post('obnovitId')) {
    $id = (int)post('obnovitId');
    $zmeneno = dbAffectedOrNumRows(dbQuery(
        'UPDATE slevove_kody SET invalidated = 0 WHERE id = $0 AND usedAt IS NULL',
        [$id],
    ));
    if ($zmeneno) {
        oznameni('Platnost poukazu byla obnovena.');
    } else {
        chyba('Platnost poukazu nešlo obnovit - buď neexistuje, nebo už byl uplatněný.');
    }
}

if (post('poznamkaId')) {
    $id       = (int)post('poznamkaId');
    $poznamka = trim((string)post('poznamka'));
    dbQuery(
        'UPDATE slevove_kody SET poznamka = $0 WHERE id = $1',
        [$poznamka === '' ? null : $poznamka, $id],
    );
    oznameni('Poznámka byla uložena.');
}

// --- výpis ---

$x = new XTemplate(__DIR__ . '/slevove-kody.xtpl');
$x->assign('urlAdmin', URL_ADMIN);

$kody = dbQuery(<<<SQL
SELECT
    slevove_kody.id,
    slevove_kody.kod,
    slevove_kody.createdAt,
    slevove_kody.usedAt,
    slevove_kody.invalidated,
    slevove_kody.poznamka,
    vytvoril.jmeno_uzivatele    AS vytvoril_jmeno,
    vytvoril.prijmeni_uzivatele AS vytvoril_prijmeni,
    vytvoril.login_uzivatele    AS vytvoril_login,
    pouzil.jmeno_uzivatele      AS pouzil_jmeno,
    pouzil.prijmeni_uzivatele   AS pouzil_prijmeni,
    pouzil.login_uzivatele      AS pouzil_login
FROM slevove_kody
LEFT JOIN uzivatele_hodnoty AS vytvoril ON vytvoril.id_uzivatele = slevove_kody.createdBy
LEFT JOIN uzivatele_hodnoty AS pouzil   ON pouzil.id_uzivatele = slevove_kody.usedBy
ORDER BY slevove_kody.createdAt DESC, slevove_kody.id DESC
SQL);

$celkem    = 0;
$volnych   = 0;
$pouzitych = 0;

while ($r = mysqli_fetch_assoc($kody)) {
    $celkem++;

    $vytvoril = Uzivatel::jmenoNickZjisti([
        'jmeno_uzivatele'    => $r['vytvoril_jmeno'],
        'prijmeni_uzivatele' => $r['vytvoril_prijmeni'],
        'login_uzivatele'    => $r['vytvoril_login'],
    ]);

    if ($r['usedAt'] !== null) {
        $pouzitych++;
        $stav     = 'Použitý';
        $stavTrida = 'slevoveKody_stav-pouzity';
        $pouzil   = Uzivatel::jmenoNickZjisti([
            'jmeno_uzivatele'    => $r['pouzil_jmeno'],
            'prijmeni_uzivatele' => $r['pouzil_prijmeni'],
            'login_uzivatele'    => $r['pouzil_login'],
        ]);
        $pouzitoKdy = DateTimeCz::createFromInterface(new \DateTime($r['usedAt']))->formatCasStandard();
        $pouzito    = htmlspecialchars((string)$pouzil) . '<br><small>' . $pouzitoKdy . '</small>';
    } elseif ($r['invalidated']) {
        $stav      = 'Zneplatněný';
        $stavTrida = 'slevoveKody_stav-zneplatneny';
        $pouzito   = '–';
    } else {
        $volnych++;
        $stav      = 'Platný';
        $stavTrida = 'slevoveKody_stav-platny';
        $pouzito   = '–';
    }

    $x->assign([
        'id'         => (int)$r['id'],
        'kod'        => htmlspecialchars((string)$r['kod']),
        'vytvoril'   => htmlspecialchars((string)$vytvoril),
        'vytvoreno'  => DateTimeCz::createFromInterface(new \DateTime($r['createdAt']))->formatCasStandard(),
        'stav'       => $stav,
        'stavTrida'  => $stavTrida,
        'pouzito'    => $pouzito,
        'poznamka'   => htmlspecialchars((string)$r['poznamka']),
    ]);

    // akce vpravo: zneplatnit jen volný, obnovit jen zneplatněný (a vždy jen nepoužitý)
    if ($r['usedAt'] === null && !$r['invalidated']) {
        $x->parse('slevoveKody.kod.zneplatnit');
    } elseif ($r['usedAt'] === null && $r['invalidated']) {
        $x->parse('slevoveKody.kod.obnovit');
    }
    // u použitého poukazu zůstává v akcích jen odkaz „Zobrazit poukaz“

    $x->parse('slevoveKody.kod');
}

if ($celkem === 0) {
    $x->parse('slevoveKody.zadne');
}

$x->assign([
    'celkem'    => $celkem,
    'volnych'   => $volnych,
    'pouzitych' => $pouzitych,
]);

$x->parse('slevoveKody');
$x->out('slevoveKody');
