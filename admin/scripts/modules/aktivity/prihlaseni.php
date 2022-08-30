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
  SELECT a.nazev_akce as nazevAktivity, a.id_akce as id, (a.kapacita+a.kapacita_m+a.kapacita_f) as kapacita, a.zacatek, a.konec, a.team_nazev,
    u.login_uzivatele as nick, u.jmeno_uzivatele as jmeno, u.id_uzivatele,
    u.prijmeni_uzivatele as prijmeni, u.email1_uzivatele as mail, u.telefon_uzivatele as telefon,
    GROUP_CONCAT(org.login_uzivatele) AS orgove,
    CONCAT(l.nazev,', ',l.dvere) as mistnost,
    MAX(log.kdy) datum_prihlaseni
  FROM akce_seznam a
  LEFT JOIN akce_prihlaseni p ON (a.id_akce=p.id_akce)
  LEFT JOIN uzivatele_hodnoty u ON (p.id_uzivatele=u.id_uzivatele)
  LEFT JOIN akce_prihlaseni_log log ON (log.id_akce=a.id_akce AND log.id_uzivatele=u.id_uzivatele)
  LEFT JOIN akce_organizatori ao ON (ao.id_akce = a.id_akce)
  LEFT JOIN uzivatele_hodnoty org ON (org.id_uzivatele = ao.id_uzivatele)
  LEFT JOIN akce_lokace l ON (l.id_lokace = a.lokace)
  WHERE a.rok = $0
  AND a.zacatek
  AND a.typ = $1
  GROUP BY u.id_uzivatele, a.id_akce
  ORDER BY a.zacatek, a.nazev_akce, a.id_akce, p.id_uzivatele
SQL,
    [0 => ROK, 1 => get('typ')]
);

$totoPrihlaseni  = mysqli_fetch_assoc($odpoved);
$dalsiPrihlaseni = mysqli_fetch_assoc($odpoved);
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
    if ($totoPrihlaseni['id'] != $dalsiPrihlaseni['id']) {
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
