<?php

require_once __DIR__ . '/sdilene-hlavicky.php';

$o = dbQuery(<<<SQL
SELECT * FROM
(
    SELECT
        akce_seznam.id_akce,
        akce_seznam.nazev_akce,
        akce_seznam.bez_slevy,
        akce_seznam.teamova,
        COUNT(dorazili.id_uzivatele) AS prihlaseno_celkem, -- NEVIM co Petr chce
        COUNT(IF(dorazili.id_stavu_prihlaseni IN ($1, $2), 1, NULL)) AS dorazilo_celkem,
        COUNT(IF(dorazili.id_stavu_prihlaseni = $2, 1, NULL)) AS dorazilo_nahradniku,
        COUNT(IF(nepritomni.id_stavu_prihlaseni = $3, 1, NULL)) AS nedorazilo,
        COUNT(IF(nepritomni.id_stavu_prihlaseni = $4, 1, NULL)) AS pozde_zrusilo,
        COUNT(IF(nepritomni.id_stavu_prihlaseni = $5, 1, NULL)) AS sledujicich, -- NEVIM co Petr chce
        akce_seznam.kapacita+akce_seznam.kapacita_m+akce_seznam.kapacita_f AS celkova_kapacita,
        akce_seznam.kapacita AS univerzalni_kapacita,
        akce_seznam.kapacita_f AS kapacita_zen,
        akce_seznam.kapacita_m AS kapacita_muzu,
        akce_seznam.rok,
        akce_seznam.zacatek,
        akce_typy.typ_1p AS typ,
        IF(akce_seznam.typ = $6, 1, 0) AS technicka,
        akce_seznam.cena,
        FORMAT(TIMESTAMPDIFF(SECOND, akce_seznam.zacatek, akce_seznam.konec) / 3600, 1) AS delka_v_hodinach
    FROM akce_seznam
    LEFT JOIN akce_prihlaseni AS dorazili ON akce_seznam.id_akce=dorazili.id_akce
    LEFT JOIN akce_prihlaseni_spec AS nepritomni ON akce_seznam.id_akce=nepritomni.id_akce
    LEFT JOIN akce_typy ON akce_seznam.typ=akce_typy.id_typu
    GROUP BY akce_seznam.id_akce
) AS aktivity
ORDER BY typ, nazev_akce, zacatek
SQL
  ,
  [
    Aktivita::PRIHLASEN_A_DORAZIL,
    Aktivita::DORAZIL_JAKO_NAHRADNIK,
    Aktivita::PRIHLASEN_ALE_NEDORAZIL,
    Aktivita::POZDE_ZRUSIL,
    Aktivita::SLEDUJICI,
    Typ::TECHNICKA,
  ]
);

$p = [];
while ($r = mysqli_fetch_assoc($o)) {
    $a = Aktivita::zId($r['id_akce']);
    $bonusZaAktivitu = Finance::bonusZaAktivitu($a);
    $organizatoriSBonusemZaAktivitu = Finance::nechOrganizatorySBonusemZaVedeniAktivit($a->organizatori());
    $r['suma_priznanych_bonusu_vypravecum'] = $a
        ? $bonusZaAktivitu * count($organizatoriSBonusemZaAktivitu)
        : 0;
    $r['priznany_bonus_jednomu_vypraveci'] = $a
        ? $bonusZaAktivitu
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
$format = get('format') === 'html' ? 'tHtml' : 'tCsv';
$report->$format();
