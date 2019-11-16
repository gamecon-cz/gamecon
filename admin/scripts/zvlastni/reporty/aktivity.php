<?php

require_once('sdilene-hlavicky.php');

$o = dbQuery(<<<SQL
  SELECT * FROM
    (SELECT
      a.id_akce,
      a.nazev_akce,
      count(p.id_uzivatele) as prihlaseno_celkem,
      count(if(p.id_stavu_prihlaseni IN ($1, $2), 1, null)) as dorazilo_celkem,
      count(if(p.id_stavu_prihlaseni = $2, 1, null)) as dorazilo_nahradniku,
      count(if(p.id_stavu_prihlaseni = $3, 1, null)) as nedorazilo,
      count(if(p.id_stavu_prihlaseni = $4, 1, null)) as pozde_zrusilo,
      count(if(p.id_stavu_prihlaseni = $5, 1, null)) as sledujicich,
      a.kapacita+a.kapacita_m+a.kapacita_f as celkova_kapacita,
      a.kapacita as univerzalni_kapacita,
      a.kapacita_f as kapacita_zen,
      a.kapacita_m as kapacita_muzu,
      a.rok,
      a.zacatek,
      t.typ_1p as typ,
      if(a.typ = $6, 1, 0) as technicka,
      a.cena,
      FORMAT(TIMESTAMPDIFF(SECOND, a.zacatek, a.konec) / 3600, 1) as delka_v_hodinach
    FROM akce_seznam a
    LEFT JOIN akce_prihlaseni p ON (a.id_akce=p.id_akce)
    LEFT JOIN akce_typy t ON (a.typ=t.id_typu)
    GROUP BY a.id_akce
    ) AS aktivity
  ORDER BY typ, nazev_akce, zacatek
SQL
  ,
  [
    Aktivita::DORAZIL,
    Aktivita::DORAZIL_NAHRADNIK,
    Aktivita::DORAZIL_NAHRADNIK,
    Aktivita::NEDORAZIL,
    Aktivita::POZDE_ZRUSIL,
    Aktivita::NAHRADNIK, // ve skutecnosti tohle znamena "sledujici"
    Aktivita::TECHNICKA,
  ]
);

$p = [];
while ($r = mysqli_fetch_assoc($o)) {
  $a = Aktivita::zId($r['id_akce']);
  $r['suma_priznanych_bonusu_vypravecum'] = $a
    ? Finance::slevaZaAktivitu($a) * count($a->organizatori())
    : 0;
  $r['priznany_bonus_jednomu_vypraveci'] = $a
    ? Finance::slevaZaAktivitu($a)
    : 0;
  $r['vypraveci_jmena'] = '';
  $r['vypraveci_ids'] = '';
  if ($a) {
    $vypraveciJmena = [];
    $vypraveciIds = [];
    foreach ($a->organizatori() as $vypravec) {
      $vypraveciJmena[] = $vypravec->jmenoNick();
      $vypraveciIds[] = $vypravec->id();
    }
    $r['vypraveci_jmena'] = implode(', ', $vypraveciJmena);
    $r['vypraveci_ids'] = implode(', ', $vypraveciIds);
  }
  $p[] = $r;
}

$report = Report::zPole($p);
$format = get('format') == 'html' ? 'tHtml' : 'tCsv';
$report->$format();
