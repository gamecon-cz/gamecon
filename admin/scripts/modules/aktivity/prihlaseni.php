<?php

/**
 * Stránka pro přehled všech přihlášených na aktivity
 *
 * nazev: Seznam přihlášených
 * pravo: 102
 * submenu_group: 3
 * submenu_order: 1
 */

use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;

$xtpl2 = new XTemplate(__DIR__ . '/prihlaseni.xtpl');

$o = dbQuery('SELECT id_typu, typ_1pmn FROM akce_typy ORDER BY poradi, typ_1pmn');
while ($r = mysqli_fetch_assoc($o)) {
    $xtpl2->assign($r);
    $xtpl2->parse('prihlaseni.vyber');
}

if (!get('typ')) {
    $xtpl2->parse('prihlaseni');
    $xtpl2->out('prihlaseni');
    return;
}

$odpoved = dbQuery(<<<SQL
SELECT  akce_seznam.nazev_akce AS nazevAktivity, akce_seznam.id_akce AS id,
        (akce_seznam.kapacita + akce_seznam.kapacita_m + akce_seznam.kapacita_f) AS kapacita,
        akce_seznam.zacatek, akce_seznam.konec, akce_seznam.team_nazev,
        ucastnik.login_uzivatele AS nick, ucastnik.jmeno_uzivatele AS jmeno, ucastnik.id_uzivatele,
        ucastnik.prijmeni_uzivatele AS prijmeni, ucastnik.email1_uzivatele AS mail, ucastnik.telefon_uzivatele AS telefon,
        GROUP_CONCAT(organizator.login_uzivatele) AS orgove,
        CONCAT(akce_lokace.nazev,', ',akce_lokace.dvere) as mistnost,
        MAX(log.kdy) AS datum_prihlaseni
FROM akce_seznam
LEFT JOIN akce_prihlaseni ON akce_seznam.id_akce = akce_prihlaseni.id_akce
LEFT JOIN uzivatele_hodnoty AS ucastnik ON akce_prihlaseni.id_uzivatele = ucastnik.id_uzivatele
LEFT JOIN akce_prihlaseni_log AS log ON log.id_akce = akce_seznam.id_akce AND log.id_uzivatele = ucastnik.id_uzivatele
LEFT JOIN akce_organizatori ON akce_organizatori.id_akce = akce_seznam.id_akce
LEFT JOIN uzivatele_hodnoty AS organizator ON organizator.id_uzivatele = akce_organizatori.id_uzivatele
LEFT JOIN akce_lokace ON akce_lokace.id_lokace = akce_seznam.lokace
WHERE akce_seznam.rok = $0
    AND akce_seznam.zacatek
    AND akce_seznam.typ = $1
GROUP BY ucastnik.id_uzivatele, akce_seznam.id_akce, akce_seznam.nazev_akce, akce_seznam.zacatek
ORDER BY akce_seznam.zacatek, akce_seznam.nazev_akce, akce_seznam.id_akce, ucastnik.id_uzivatele
SQL,
    [0 => ROCNIK, 1 => get('typ')]
);

$totoPrihlaseni  = mysqli_fetch_assoc($odpoved) ?: [];
$dalsiPrihlaseni = mysqli_fetch_assoc($odpoved) ?: [];
$obsazenost      = 0;
$odd             = 0;
$maily           = [];
while ($totoPrihlaseni) {
    $xtpl2->assign($totoPrihlaseni);
    if ($totoPrihlaseni['id_uzivatele']) {
        $hrac  = Uzivatel::zId($totoPrihlaseni['id_uzivatele']);
        $datum = new DateTimeCz($totoPrihlaseni['zacatek']);
        $vek   = $hrac->vekKDatu($datum);
        if ($vek === null) $vek = "Nevyplnil";
        elseif ($vek >= 18) $vek = "18+";

        if ($totoPrihlaseni['datum_prihlaseni'] != null) {
            $prihlasen = new DateTimeCz($totoPrihlaseni['datum_prihlaseni']);
            $xtpl2->assign('datum_prihlaseni', $prihlasen->format('j.n. H:i'));
        }
        $xtpl2->assign('vek', $vek);
        $xtpl2->assign('odd', $odd ? $odd = '' : $odd = 'odd');
        $xtpl2->parse('prihlaseni.aktivita.lide.clovek');
        $maily[] = $totoPrihlaseni['mail'];
        $obsazenost++;
    }
    if (($totoPrihlaseni['id'] ?? null) != ($dalsiPrihlaseni['id'] ?? null)) {
        $xtpl2->assign('maily', implode('; ', $maily));
        $xtpl2->assign('cas', datum2($totoPrihlaseni));
        $xtpl2->assign('orgove', $totoPrihlaseni['orgove']);
        $xtpl2->assign('obsazenost', $obsazenost .
            ($totoPrihlaseni['kapacita'] ? '/' . $totoPrihlaseni['kapacita'] : ''));
        $xtpl2->assign('druzina', $totoPrihlaseni['team_nazev'] ? ($totoPrihlaseni['team_nazev'] . ' - ') : '');
        if ($obsazenost)
            $xtpl2->parse('prihlaseni.aktivita.lide');
        $xtpl2->parse('prihlaseni.aktivita');
        $obsazenost = 0;
        $odd        = 0;
        $maily      = [];
    }
    $totoPrihlaseni  = $dalsiPrihlaseni;
    $dalsiPrihlaseni = mysqli_fetch_assoc($odpoved);
}

$xtpl2->parse('prihlaseni');
$xtpl2->out('prihlaseni');
