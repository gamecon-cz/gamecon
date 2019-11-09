<?php

require_once('sdilene-hlavicky.php');

$o = dbQuery('
  SELECT
    a.id_akce,
    a.nazev_akce,
    count(p.id_uzivatele) as prihlaseno,
    count(if(p.id_stavu_prihlaseni = 2, 1, null)) as prihlaseno_nahradniku,
    a.kapacita+a.kapacita_m+a.kapacita_f as kapacita,
    a.rok,
    a.zacatek,
    t.typ_1p as typ,
    if(a.typ = 10, 1, 0) as technicka,
    a.cena,
    TIMESTAMPDIFF(HOUR, a.zacatek, a.konec) as delka
  FROM akce_seznam a
  LEFT JOIN akce_prihlaseni p ON (a.id_akce=p.id_akce)
  LEFT JOIN akce_typy t ON (a.typ=t.id_typu)
  GROUP BY a.id_akce
  ORDER BY a.typ, a.nazev_akce, a.rok
');

$p = [];
while($r = mysqli_fetch_assoc($o)) {
  $a = Aktivita::zId($r['id_akce']);
  $r['priznany_bonus'] = $a ? Finance::bonusZaAktivitu($a) * count($a->organizatori()) : 0;
  $p[] = $r;
}

$report = Report::zPole($p);
$format = get('format') == 'html' ? 'tHtml' : 'tCsv';
$report->$format();
