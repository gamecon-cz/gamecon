<?php
require __DIR__ . '/sdilene-hlavicky.php';

$report = Report::zSql(<<<SQL
SELECT
    uzivatele_hodnoty.login_uzivatele,
    uzivatele_hodnoty.jmeno_uzivatele,
    uzivatele_hodnoty.prijmeni_uzivatele,
    uzivatele_hodnoty.email1_uzivatele,
    uzivatele_hodnoty.telefon_uzivatele,
    akce_seznam.team_nazev,
    org.login_uzivatele,
    akce_seznam.zacatek AS '1KOLO',
    semifinale.zacatek AS '2KOLO'
FROM akce_seznam
JOIN akce_prihlaseni ON akce_prihlaseni.id_akce = akce_seznam.id_akce
JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = akce_prihlaseni.id_uzivatele
LEFT JOIN akce_organizatori ON akce_organizatori.id_akce = akce_seznam.id_akce
LEFT JOIN uzivatele_hodnoty AS org ON org.id_uzivatele = akce_organizatori.id_uzivatele
LEFT JOIN (
    SELECT xap.id_uzivatele, xa.zacatek
    FROM akce_seznam xa
    JOIN akce_prihlaseni xap ON xap.id_akce = xa.id_akce
    WHERE
        xa.rok = $0 AND
        xa.typ = $1 AND
        xa.nazev_akce LIKE '%semifinále%'
) semifinale ON semifinale.id_uzivatele = uzivatele_hodnoty.id_uzivatele
WHERE
    akce_seznam.rok = $0 AND
    akce_seznam.typ = $1 AND
    akce_seznam.cena > 0 -- detekce základního kola
SQL
    , [0 => ROCNIK, 1 => \Gamecon\Aktivita\TypAktivity::DRD]
);
$report->tFormat(get('format'));
