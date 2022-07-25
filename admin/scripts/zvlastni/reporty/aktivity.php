<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;

require __DIR__ . '/sdilene-hlavicky.php';

// dorazili
$prihlasen = StavPrihlaseni::PRIHLASEN; // tohle je k něčemu leda v případě, že nějaká aktivita ještě nebyla uzavřena - což by se mělo stávat jen před a během Gameconu (a možná u technických aktivit)
$prihlasenADorazil = StavPrihlaseni::PRIHLASEN_A_DORAZIL;
$dorazilJakoNahradnik = StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK;
// nepritomni
$prihlasenAleNedorazil = StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL;
$pozdeZrusil = StavPrihlaseni::POZDE_ZRUSIL;
$sledujici = StavPrihlaseni::SLEDUJICI;

$technicka = TypAktivity::TECHNICKA;

$o = dbQuery(<<<SQL
SELECT * FROM
(
    SELECT
        akce_seznam.id_akce,
        akce_seznam.nazev_akce,
        akce_seznam.bez_slevy,
        akce_seznam.teamova,
        (
            COUNT(IF(akce_prihlaseni_vse.id_stavu_prihlaseni IN ({$prihlasen}, {$prihlasenADorazil}), 1, NULL)) +
            COUNT(IF(akce_prihlaseni_vse.id_stavu_prihlaseni IN ({$prihlasenAleNedorazil}, {$pozdeZrusil}), 1, NULL))
        ) AS prihlaseno_predem,
        COUNT(IF(akce_prihlaseni_vse.id_stavu_prihlaseni IN ({$prihlasenADorazil}), 1, NULL)) AS dorazilo_predem_prihlasenych,
        COUNT(IF(akce_prihlaseni_vse.id_stavu_prihlaseni = {$dorazilJakoNahradnik}, 1, NULL)) AS dorazilo_nahradniku,
        COUNT(IF(akce_prihlaseni_vse.id_stavu_prihlaseni IN ({$prihlasenADorazil}, {$dorazilJakoNahradnik}), 1, NULL)) AS dorazilo_celkem,
        COUNT(IF(akce_prihlaseni_vse.id_stavu_prihlaseni = {$prihlasenAleNedorazil}, 1, NULL)) AS nedorazilo_predem_prihlasenych,
        COUNT(IF(akce_prihlaseni_vse.id_stavu_prihlaseni = {$pozdeZrusil}, 1, NULL)) AS pozde_zrusilo,
        COUNT(IF(akce_prihlaseni_vse.id_stavu_prihlaseni = {$sledujici}, 1, NULL)) AS zustalo_sledujicich,
        akce_seznam.kapacita+akce_seznam.kapacita_m+akce_seznam.kapacita_f AS celkova_kapacita,
        akce_seznam.kapacita AS univerzalni_kapacita,
        akce_seznam.kapacita_f AS kapacita_zen,
        akce_seznam.kapacita_m AS kapacita_muzu,
        akce_seznam.rok,
        akce_seznam.zacatek,
        akce_typy.typ_1p AS typ,
        IF(akce_seznam.typ = {$technicka}, 1, 0) AS technicka,
        akce_seznam.cena,
        FORMAT(TIMESTAMPDIFF(SECOND, akce_seznam.zacatek, akce_seznam.konec) / 3600, 1) AS delka_v_hodinach
    FROM akce_seznam
    LEFT JOIN (SELECT * FROM akce_prihlaseni UNION ALL SELECT * FROM akce_prihlaseni_spec) AS akce_prihlaseni_vse
        ON akce_seznam.id_akce = akce_prihlaseni_vse.id_akce
    LEFT JOIN akce_typy ON akce_seznam.typ=akce_typy.id_typu
    GROUP BY akce_seznam.id_akce
) AS aktivity
ORDER BY typ, nazev_akce, zacatek
SQL
);

$p = [];
while ($r = mysqli_fetch_assoc($o)) {
    $a = Aktivita::zId($r['id_akce']);
    $bonusZaAktivitu = \Gamecon\Uzivatel\Finance::bonusZaAktivitu($a);
    $organizatoriSBonusemZaAktivitu = \Gamecon\Uzivatel\Finance::nechOrganizatorySBonusemZaVedeniAktivit($a->organizatori());
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

Report::zPole($p)->tFormat(get('format'));
