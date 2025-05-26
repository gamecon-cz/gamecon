<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var $systemoveNastaveni */

$report = Report::zSql(<<<SQL
select e.kod as kod, e.popis as posis, e.data as data
from (
SELECT MAX(d.poradi) as poradi, d.kod, MAX(d.nazev) AS popis, MAX(d.data) AS data
FROM ((
SELECT 0 AS poradi, 'Ir-Timestamp' AS kod, 'Timestamp reportu' AS nazev, NOW() AS data

UNION

SELECT 0 AS poradi, CONCAT('Ir-Ucast-', IF(r.rid IS NULL, 'Ucastnici', CASE r.rid
                                                            WHEN 'ORGANIZATOR_ZDARMA' THEN 'Org0'
                                                            WHEN 'PUL_ORG_UBYTKO' THEN 'OrgU'
                                                            WHEN 'PUL_ORG_TRICKO' THEN 'OrgT'
                                                            WHEN 'VYPRAVEC' THEN 'Vypraveci'
                                                            WHEN 'DOBROVOLNIK_SENIOR' THEN 'Dobrovolnici'
                                                            WHEN 'PARTNER' THEN 'Partneri'
                                                            WHEN 'BRIGADNIK' THEN 'Brigadnici'
                                                           END)) AS kod, 'Počet přihlášených účastníků dle kategorií' AS nazev, COUNT(u.id) AS data
FROM (SELECT DISTINCT id_uzivatele AS id
      FROM uzivatele_role
      JOIN role_seznam ON uzivatele_role.id_role = role_seznam.id_role
      WHERE typ_role = 'ucast'
        AND role_seznam.vyznam_role = 'PRIHLASEN'
        AND rocnik_role = aktualniRocnik()) u
LEFT JOIN (SELECT ur.id_uzivatele uid, rs.vyznam_role rid
           FROM uzivatele_role ur
           JOIN role_seznam rs ON ur.id_role = rs.id_role
           WHERE rs.rocnik_role IN (aktualniRocnik(), -1)
             AND rs.vyznam_role IN ('ORGANIZATOR_ZDARMA',
                                    'PUL_ORG_UBYTKO',
                                    'PUL_ORG_TRICKO',
                                    'VYPRAVEC',
                                    'DOBROVOLNIK_SENIOR',
                                    'PARTNER',
                                    'BRIGADNIK')) r ON r.uid = u.id
GROUP BY r.rid

UNION

SELECT 0 AS poradi, 'Vr-Vstupne' AS kod, 'Dobrovolné vstupné (sum CZK)' AS nazev, SUM(ALL sn.cena_nakupni) AS data
FROM shop_nakupy sn
JOIN shop_predmety sp ON sp.id_predmetu = sn.id_predmetu
WHERE sp.typ = 5 AND sn.rok = aktualniRocnik()

UNION

SELECT 0 AS poradi, 'Vr-Ubytovani-3L' AS kod, 'Prodané noci 3L (počet)' AS nazev, COUNT(sn.id_nakupu) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sp.id_predmetu = sn.id_predmetu
WHERE sp.typ = 2
  AND sn.rok = aktualniRocnik()
  AND sp.kod_predmetu IN ('3L_st',
                          '3L_ct',
                          '3L_pa',
                          '3L_so',
                          '3L_ne')
  AND (NOT (
      maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
      OR (maPravo(sn.id_uzivatele, 1015) AND sp.ubytovani_den = 0) -- ubytování zdarma středa
      OR (maPravo(sn.id_uzivatele, 1029) AND sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
      OR (maPravo(sn.id_uzivatele, 1030) AND sp.ubytovani_den = 2) -- ubytování zdarma pátek
      OR (maPravo(sn.id_uzivatele, 1031) AND sp.ubytovani_den = 3) -- ubytování zdarma sobota
      OR (maPravo(sn.id_uzivatele, 1018) AND sp.ubytovani_den = 4) -- ubytování zdarma neděle
      ))

UNION

SELECT 0 AS poradi, 'Vr-Ubytovani-2L' AS kod, 'Prodané noci 2L (počet)' AS nazev, COUNT(sn.id_nakupu) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sp.id_predmetu = sn.id_predmetu
WHERE sp.typ = 2
  AND sn.rok = aktualniRocnik()
  AND sp.kod_predmetu IN ('2L_st',
                          '2L_ct',
                          '2L_pa',
                          '2L_so',
                          '2L_ne')
  AND (NOT (
    maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
        OR (maPravo(sn.id_uzivatele, 1015) AND sp.ubytovani_den = 0) -- ubytování zdarma středa
        OR (maPravo(sn.id_uzivatele, 1029) AND sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
        OR (maPravo(sn.id_uzivatele, 1030) AND sp.ubytovani_den = 2) -- ubytování zdarma pátek
        OR (maPravo(sn.id_uzivatele, 1031) AND sp.ubytovani_den = 3) -- ubytování zdarma sobota
        OR (maPravo(sn.id_uzivatele, 1018) AND sp.ubytovani_den = 4) -- ubytování zdarma neděle
    ))

UNION

SELECT 0 AS poradi, 'Vr-Ubytovani-1L' AS kod, 'Prodané noci 1L (počet)' AS nazev, COUNT(sn.id_nakupu) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sp.id_predmetu = sn.id_predmetu
WHERE sp.typ = 2
  AND sn.rok = aktualniRocnik()
  AND sp.kod_predmetu IN ('1L_st',
                          '1L_ct',
                          '1L_pa',
                          '1L_so',
                          '1L_ne')
  AND (NOT (
    maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
        OR (maPravo(sn.id_uzivatele, 1015) AND sp.ubytovani_den = 0) -- ubytování zdarma středa
        OR (maPravo(sn.id_uzivatele, 1029) AND sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
        OR (maPravo(sn.id_uzivatele, 1030) AND sp.ubytovani_den = 2) -- ubytování zdarma pátek
        OR (maPravo(sn.id_uzivatele, 1031) AND sp.ubytovani_den = 3) -- ubytování zdarma sobota
        OR (maPravo(sn.id_uzivatele, 1018) AND sp.ubytovani_den = 4) -- ubytování zdarma neděle
    ))

UNION

SELECT 0 AS poradi, 'Vr-Ubytovani-spac' AS kod, 'Prodané noci spacáky (počet)' AS nazev, COUNT(sn.id_nakupu) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sp.id_predmetu = sn.id_predmetu
WHERE sp.typ = 2
  AND sn.rok = aktualniRocnik()
  AND sp.kod_predmetu IN ('spacak_st',
                          'spacak_ct',
                          'spacak_pa',
                          'spacak_so',
                          'spacak_ne')
  AND (NOT (
    maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
        OR (maPravo(sn.id_uzivatele, 1015) AND sp.ubytovani_den = 0) -- ubytování zdarma středa
        OR (maPravo(sn.id_uzivatele, 1029) AND sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
        OR (maPravo(sn.id_uzivatele, 1030) AND sp.ubytovani_den = 2) -- ubytování zdarma pátek
        OR (maPravo(sn.id_uzivatele, 1031) AND sp.ubytovani_den = 3) -- ubytování zdarma sobota
        OR (maPravo(sn.id_uzivatele, 1018) AND sp.ubytovani_den = 4) -- ubytování zdarma neděle
    ))

UNION

SELECT 0 AS poradi, a.kod, a.nazev, (SUM(a.data)) AS data FROM (
SELECT CONCAT('Nr-Zdarma-', IF(at.id_typu = 6, -- Wargaming
                                   IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   IF(at.id_typu = 7, -- Bonus
                                      IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) AS kod,
        'Cena za účast orgů zdarma na programu (s právem "Plná sleva na aktivity" na akci, která není "bez slev") (sum CZK)' AS nazev, (ase.cena) AS data
FROM akce_seznam ase
         JOIN akce_prihlaseni ap ON ase.id_akce = ap.id_akce
         JOIN akce_typy at ON ase.typ = at.id_typu
WHERE ase.rok = aktualniRocnik()
  AND ase.bez_slevy = 0
  AND (maPravo(ap.id_uzivatele, 1023)) -- právo Plná sleva na aktivity
  AND at.kod_typu IS NOT NULL
) a
GROUP BY a.kod

UNION

SELECT 0 AS poradi, a.kod, a.nazev, (SUM(a.data)) AS data FROM (
SELECT CONCAT('Vr-Storna-', IF(at.id_typu = 6, -- Wargaming
                                   IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   IF(at.id_typu = 7, -- Bonus
                                      IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) AS kod,
        'Storna za' AS nazev, ((ase.cena) / 2) AS data
FROM akce_seznam ase
         JOIN akce_prihlaseni_spec ap ON ase.id_akce = ap.id_akce
         JOIN akce_typy at ON ase.typ = at.id_typu
WHERE ase.rok = aktualniRocnik()
  AND (ase.bez_slevy = 1 OR (NOT maPravo(ap.id_uzivatele, 1023))) -- není zdarma
  AND ap.id_stavu_prihlaseni = 4 -- storno
  AND at.kod_typu IS NOT NULL
) a
GROUP BY a.kod

UNION

SELECT 0 AS poradi, a.kod, a.nazev, (SUM(a.data)) AS data FROM (
SELECT CONCAT('Ir-Std', IF(at.id_typu = 6, -- Wargaming
                                   IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   IF(at.id_typu = 7, -- Bonus
                                      IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) AS kod,
        'Počet aktivit přepočtený na standardní aktivitu' AS nazev, (delkaAktivityJakoNasobekStandardni(ase.id_akce)) AS data
FROM akce_seznam ase
JOIN akce_typy at ON ase.typ = at.id_typu
WHERE ase.rok = aktualniRocnik()
  AND at.kod_typu IS NOT NULL
) a
GROUP BY a.kod

UNION

SELECT 0 AS poradi, a.kod, a.nazev, (SUM(a.kapacita * a.dajns) / SUM(dajns)) AS data FROM (
SELECT CONCAT('Ir-Kapacita', IF(at.id_typu = 6, -- Wargaming
                                   IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   IF(at.id_typu = 7, -- Bonus
                                      IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) AS kod,
        'Průměrná kapacita aktivity, vážený průměr podle přepočtu na standardní aktivitu' AS nazev, ase.kapacita AS kapacita, delkaAktivityJakoNasobekStandardni(ase.id_akce) AS dajns
FROM akce_seznam ase
JOIN akce_typy at ON ase.typ = at.id_typu
WHERE ase.rok = aktualniRocnik()
  AND at.kod_typu IS NOT NULL
) a
GROUP BY a.kod

UNION

SELECT 0 AS poradi, a.kod, a.nazev, (SUM(a.vypraveci * a.dajns) / SUM(dajns)) AS data FROM (
SELECT CONCAT('Ir-PrumPocVyp-', IF(at.id_typu = 6, -- Wargaming
                                   IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   IF(at.id_typu = 7, -- Bonus
                                      IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) AS kod,
        'Prům. počet vypravěčů 1 aktivity, vážený průměr podle přepočtu na standardní aktivitu' AS nazev, (SELECT COUNT(*) FROM akce_organizatori ao WHERE ao.id_akce = ase.id_akce) AS vypraveci, delkaAktivityJakoNasobekStandardni(ase.id_akce) AS dajns
FROM akce_seznam ase
JOIN akce_typy at ON ase.typ = at.id_typu
WHERE ase.rok = aktualniRocnik()
  AND at.kod_typu IS NOT NULL
) a
GROUP BY a.kod

UNION

SELECT 0 AS poradi, a.kod, a.nazev, (SUM(a.data)) AS data FROM (
SELECT CONCAT('Ir-StdVypraveci-', IF(at.id_typu = 6, -- Wargaming
                                   IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   IF(at.id_typu = 7, -- Bonus
                                      IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) AS kod,
        'Vypravěčobloky (přepočtené standardní aktivity * počet lidí) vedené Vypravěči nebo Half-orgy' AS nazev, (delkaAktivityJakoNasobekStandardni(ase.id_akce)) AS data
FROM akce_organizatori ao
JOIN akce_seznam ase ON ase.id_akce = ao.id_akce
JOIN akce_typy at ON ase.typ = at.id_typu
WHERE ase.rok = aktualniRocnik()
  AND NOT EXISTS(SELECT 1 FROM uzivatele_role ur WHERE ur.id_uzivatele = ao.id_uzivatele AND ur.id_role = 2) -- není full-org
  AND at.kod_typu IS NOT NULL
) a
GROUP BY a.kod

UNION

SELECT 0 AS poradi, a.kod, a.nazev, (SUM(a.data)) AS data FROM (
SELECT CONCAT('Ir-StdVypOrgove-', IF(at.id_typu = 6, -- Wargaming
                                   IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   IF(at.id_typu = 7, -- Bonus
                                      IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) AS kod,
        'Vypravěčobloky (přepočtené standardní aktivity * počet lidí) vedené Orgy' AS nazev, (delkaAktivityJakoNasobekStandardni(ase.id_akce)) AS data
FROM akce_organizatori ao
JOIN akce_seznam ase ON ase.id_akce = ao.id_akce
JOIN akce_typy at ON ase.typ = at.id_typu
WHERE ase.rok = aktualniRocnik()
  AND EXISTS(SELECT 1 FROM uzivatele_role ur WHERE ur.id_uzivatele = ao.id_uzivatele AND ur.id_role = 2) -- není full-org
  AND at.kod_typu IS NOT NULL
) a
GROUP BY a.kod

UNION

SELECT 0 AS poradi, a.kod, a.nazev, (SUM(a.data)) AS data FROM (
SELECT CONCAT('Nr-Bonusy', IF(at.id_typu = 6, -- Wargaming
                                   IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   IF(at.id_typu = 7, -- Bonus
                                      IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) AS kod,
        'Suma bonusů za vedení aktivit u lidí bez práva "bez bonusu za vedení aktivit"' AS nazev, (delkaAktivityJakoNasobekStandardni(ase.id_akce) * systemoveNastaveni('BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU')) AS data
FROM akce_organizatori ao
JOIN akce_seznam ase ON ase.id_akce = ao.id_akce
JOIN akce_typy at ON ase.typ = at.id_typu
WHERE ase.rok = aktualniRocnik()
  AND NOT maPravo(ao.id_uzivatele, 1028) -- Bez bonusu za vedení aktivit
  AND at.kod_typu IS NOT NULL
) a
GROUP BY a.kod

UNION

SELECT 0 AS poradi, a.kod, a.nazev, (SUM(a.data)) AS data FROM (
SELECT CONCAT('Ir-Ucast', IF(at.id_typu = 6, -- Wargaming
                                   IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   IF(at.id_typu = 7, -- Bonus
                                      IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) AS kod,
        'Počet herních bloků zabraný hráči přepočtený na standardní aktivitu (bez ohledu na kategorii hráče)' AS nazev, (delkaAktivityJakoNasobekStandardni(ase.id_akce)) AS data
FROM akce_prihlaseni ap
JOIN akce_seznam ase ON ap.id_akce = ase.id_akce
JOIN akce_typy at ON ase.typ = at.id_typu
WHERE ase.rok = aktualniRocnik()
  AND at.kod_typu IS NOT NULL
) a
GROUP BY a.kod

UNION

SELECT 0 AS poradi, a.kod, a.nazev, (SUM(a.data)) AS data FROM (
SELECT CONCAT('Vr-Vynosy-', IF(at.id_typu = 6, -- Wargaming
                                   IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   IF(at.id_typu = 7, -- Bonus
                                      IF(EXISTS(SELECT 1 FROM akce_sjednocene_tagy ast WHERE ast.id_akce = ase.id_akce AND ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) AS kod,
        'Příjmy z aktivit, bez storn a bez lidí co mají účast zdarma' AS nazev, (ase.cena) AS data
FROM akce_prihlaseni ap
JOIN akce_seznam ase ON ap.id_akce = ase.id_akce
JOIN akce_typy at ON ase.typ = at.id_typu
WHERE ase.rok = aktualniRocnik()
  AND NOT maPravo(ap.id_uzivatele, 1023) -- plná sleva na aktivity
  AND at.kod_typu IS NOT NULL
) a
GROUP BY a.kod

UNION

SELECT 0 AS poradi, 'Vsechna tricka jsou TODO' AS kod, 'Vsechna tricka jsou TODO' AS nazev, 'TODO' AS data

UNION

SELECT 0 AS poradi, CONCAT('Vr-Kostky-', sp.kod_predmetu) AS kod, 'kostka prodeje - včetně zdarma - kusy' AS nazev, COUNT(*) AS data
FROM shop_nakupy sn
  JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
WHERE sn.rok = aktualniRocnik()
  AND sp.kod_predmetu LIKE '%kostk%'
GROUP BY sp.id_predmetu

UNION

SELECT 0 AS poradi, a.kod, a.nazev, COUNT(*) AS data
FROM (
  SELECT 'Ir-Kostky-CelkemZdarma' AS kod, 'Kolik z prodaných kostek (všech typů) je zdarma - kusy' AS nazev, 1 AS data
  FROM shop_nakupy sn
    JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
  WHERE sn.rok = aktualniRocnik()
    AND sp.kod_predmetu LIKE '%kostk%'
    AND maPravo(sn.id_uzivatele, 1003) -- kostka zdarma
  GROUP BY sn.id_uzivatele
) a

UNION

SELECT 0 AS poradi, CONCAT('Vr-Placky') AS kod, 'placky prodeje - včetně zdarma - kusy' AS nazev, COUNT(*) AS data
FROM shop_nakupy sn
  JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
WHERE sn.rok = aktualniRocnik()
  AND sp.kod_predmetu LIKE '%plack%'

UNION

SELECT 0 AS poradi, a.kod, a.nazev, COUNT(*) AS data
FROM (
  SELECT 'Ir-Placky-Zdarma' AS kod, 'Kolik z prodaných placek je zdarma - kusy' AS nazev, 1 AS data
  FROM shop_nakupy sn
    JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
  WHERE sn.rok = aktualniRocnik()
    AND sp.kod_predmetu LIKE '%plack%'
    AND maPravo(sn.id_uzivatele, 1002) -- kostka zdarma
  GROUP BY sn.id_uzivatele
) a

UNION

SELECT 0 AS poradi, CONCAT('Vr-Nicknacky') AS kod, 'nicknacky prodeje - kusy' AS nazev, COUNT(*) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
WHERE sn.rok = aktualniRocnik()
  AND sp.kod_predmetu LIKE '%nicknack%'

UNION

SELECT 0 AS poradi, CONCAT('Vr-Bloky') AS kod, 'bloky prodeje - kusy' AS nazev, COUNT(*) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
WHERE sn.rok = aktualniRocnik()
  AND sp.kod_predmetu LIKE '%blok%'

UNION

SELECT 0 AS poradi, CONCAT('Vr-Ponozky') AS kod, 'ponožky prodeje - kusy' AS nazev, COUNT(*) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
WHERE sn.rok = aktualniRocnik()
  AND sp.kod_predmetu LIKE '%ponozk%'

UNION

SELECT 0 AS poradi, CONCAT('Vr-Tasky') AS kod, 'tašky prodeje - kusy' AS nazev, COUNT(*) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
WHERE sn.rok = aktualniRocnik()
  AND sp.kod_predmetu LIKE '%task%'

UNION

SELECT 0 AS poradi, CONCAT('Xr-Jidla-Snidane') AS kod, 'snídaně placené - kusy' AS nazev, COUNT(*) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
WHERE sn.rok = aktualniRocnik()
  AND sp.kod_predmetu LIKE '%snidane%'
  AND NOT maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

UNION

SELECT 0 AS poradi, CONCAT('Xr-Jidla-Hlavni') AS kod, 'hl. jídla placené - kusy' AS nazev, COUNT(*) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
WHERE sn.rok = aktualniRocnik()
  AND (sp.kod_predmetu LIKE '%obed%' OR sp.kod_predmetu LIKE '%vecere%')
  AND NOT maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

UNION

SELECT 0 AS poradi, CONCAT('Nr-JidlaZdarma-Snidane') AS kod, 'snídaně zdarma - kusy' AS nazev, COUNT(*) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
WHERE sn.rok = aktualniRocnik()
  AND sp.kod_predmetu LIKE '%snidane%'
  AND maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

UNION

SELECT 0 AS poradi, CONCAT('Nr-JidlaZdarma-Hlavni') AS kod, 'hl. jídla zdarma - kusy' AS nazev, COUNT(*) AS data
FROM shop_nakupy sn
         JOIN shop_predmety sp ON sn.id_predmetu = sp.id_predmetu
WHERE sn.rok = aktualniRocnik()
  AND (sp.kod_predmetu LIKE '%obed%' OR sp.kod_predmetu LIKE '%vecere%')
  AND maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma
)
UNION ALL
(
SELECT 1   AS poradi, 'Ir-Timestamp'           AS kod, NULL AS nazev, NULL AS data UNION
SELECT 10  AS poradi, 'Ir-Ucast-Ucastnici'     AS kod, NULL AS nazev, NULL AS data UNION
SELECT 11  AS poradi, 'Ir-Ucast-Org0'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 12  AS poradi, 'Ir-Ucast-OrgU'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 13  AS poradi, 'Ir-Ucast-OrgT'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 14  AS poradi, 'Ir-Ucast-Vypraveci'     AS kod, NULL AS nazev, NULL AS data UNION
SELECT 15  AS poradi, 'Ir-Ucast-Dobrovolnici'  AS kod, NULL AS nazev, NULL AS data UNION
SELECT 16  AS poradi, 'Ir-Ucast-Partneri'      AS kod, NULL AS nazev, NULL AS data UNION
SELECT 17  AS poradi, 'Ir-Ucast-Brigadnici'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 20  AS poradi, 'Vr-Vstupne'             AS kod, NULL AS nazev, NULL AS data UNION
SELECT 30  AS poradi, 'Vr-Ubytovani-3L'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 31  AS poradi, 'Vr-Ubytovani-2L'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 32  AS poradi, 'Vr-Ubytovani-1L'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 33  AS poradi, 'Vr-Ubytovani-spac'      AS kod, NULL AS nazev, NULL AS data UNION
SELECT 40  AS poradi, 'Nr-Zdarma-RPG'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 41  AS poradi, 'Nr-Zdarma-LKD'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 42  AS poradi, 'Nr-Zdarma-DrD'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 43  AS poradi, 'Nr-Zdarma-Larp'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 44  AS poradi, 'Nr-Zdarma-Turn'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 45  AS poradi, 'Nr-Zdarma-Epic'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 46  AS poradi, 'Nr-Zdarma-WGhry'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 47  AS poradi, 'Nr-Zdarma-WGmal'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 48  AS poradi, 'Nr-Zdarma-AHry'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 49  AS poradi, 'Nr-Zdarma-AHEsc'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 60  AS poradi, 'Vr-Storna-RPG'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 61  AS poradi, 'Vr-Storna-LKD'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 62  AS poradi, 'Vr-Storna-DrD'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 63  AS poradi, 'Vr-Storna-Larp'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 64  AS poradi, 'Vr-Storna-Turn'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 65  AS poradi, 'Vr-Storna-Epic'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 66  AS poradi, 'Vr-Storna-WGhry'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 67  AS poradi, 'Vr-Storna-WGmal'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 68  AS poradi, 'Vr-Storna-AHry'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 69  AS poradi, 'Vr-Storna-AHEsc'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 80  AS poradi, 'Ir-StdRPG'              AS kod, NULL AS nazev, NULL AS data UNION
SELECT 81  AS poradi, 'Ir-StdLKD'              AS kod, NULL AS nazev, NULL AS data UNION
SELECT 82  AS poradi, 'Ir-StdDrD'              AS kod, NULL AS nazev, NULL AS data UNION
SELECT 83  AS poradi, 'Ir-StdLarp'             AS kod, NULL AS nazev, NULL AS data UNION
SELECT 84  AS poradi, 'Ir-StdTurn'             AS kod, NULL AS nazev, NULL AS data UNION
SELECT 85  AS poradi, 'Ir-StdEpic'             AS kod, NULL AS nazev, NULL AS data UNION
SELECT 86  AS poradi, 'Ir-StdWGHry'            AS kod, NULL AS nazev, NULL AS data UNION
SELECT 87  AS poradi, 'Ir-StdWGmal'            AS kod, NULL AS nazev, NULL AS data UNION
SELECT 88  AS poradi, 'Ir-StdAHry'             AS kod, NULL AS nazev, NULL AS data UNION
SELECT 89  AS poradi, 'Ir-StdAHEsc'            AS kod, NULL AS nazev, NULL AS data UNION
SELECT 90  AS poradi, 'Ir-StdPred'            AS kod, NULL AS nazev, NULL AS data UNION
SELECT 100 AS poradi, 'Ir-KapacitaRPG'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 101 AS poradi, 'Ir-KapacitaLKD'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 102 AS poradi, 'Ir-KapacitaDrD'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 103 AS poradi, 'Ir-KapacitaLarp'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 104 AS poradi, 'Ir-KapacitaTurn'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 105 AS poradi, 'Ir-KapacitaEpic'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 106 AS poradi, 'Ir-KapacitaWGhry'       AS kod, NULL AS nazev, NULL AS data UNION
SELECT 107 AS poradi, 'Ir-KapacitaWGmal'       AS kod, NULL AS nazev, NULL AS data UNION
SELECT 108 AS poradi, 'Ir-KapacitaAHry'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 109 AS poradi, 'Ir-KapacitaAHEsc'       AS kod, NULL AS nazev, NULL AS data UNION
SELECT 110 AS poradi, 'Ir-KapacitaPred'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 120 AS poradi, 'Ir-PrumPocVyp-RPG'      AS kod, NULL AS nazev, NULL AS data UNION
SELECT 121 AS poradi, 'Ir-PrumPocVyp-LKD'      AS kod, NULL AS nazev, NULL AS data UNION
SELECT 122 AS poradi, 'Ir-PrumPocVyp-DrD'      AS kod, NULL AS nazev, NULL AS data UNION
SELECT 123 AS poradi, 'Ir-PrumPocVyp-Larp'     AS kod, NULL AS nazev, NULL AS data UNION
SELECT 124 AS poradi, 'Ir-PrumPocVyp-Turn'     AS kod, NULL AS nazev, NULL AS data UNION
SELECT 125 AS poradi, 'Ir-PrumPocVyp-Epic'     AS kod, NULL AS nazev, NULL AS data UNION
SELECT 126 AS poradi, 'Ir-PrumPocVyp-WGhry'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 127 AS poradi, 'Ir-PrumPocVyp-WGmal'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 128 AS poradi, 'Ir-PrumPocVyp-AHry'     AS kod, NULL AS nazev, NULL AS data UNION
SELECT 129 AS poradi, 'Ir-PrumPocVyp-Pred'     AS kod, NULL AS nazev, NULL AS data UNION
SELECT 140 AS poradi, 'Ir-StdVypraveci-RPG'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 141 AS poradi, 'Ir-StdVypraveci-LKD'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 142 AS poradi, 'Ir-StdVypraveci-DrD'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 143 AS poradi, 'Ir-StdVypraveci-Larp'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 144 AS poradi, 'Ir-StdVypraveci-Turn'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 145 AS poradi, 'Ir-StdVypraveci-Epic'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 146 AS poradi, 'Ir-StdVypraveci-WGhry'  AS kod, NULL AS nazev, NULL AS data UNION
SELECT 147 AS poradi, 'Ir-StdVypraveci-WGmal'  AS kod, NULL AS nazev, NULL AS data UNION
SELECT 148 AS poradi, 'Ir-StdVypraveci-AHry'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 149 AS poradi, 'Ir-StdVypraveci-Pred'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 160 AS poradi, 'Ir-StdVypOrgove-RPG'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 161 AS poradi, 'Ir-StdVypOrgove-LKD'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 162 AS poradi, 'Ir-StdVypOrgove-DrD'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 163 AS poradi, 'Ir-StdVypOrgove-Larp'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 164 AS poradi, 'Ir-StdVypOrgove-Turn'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 165 AS poradi, 'Ir-StdVypOrgove-Epic'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 166 AS poradi, 'Ir-StdVypOrgove-WGhry'  AS kod, NULL AS nazev, NULL AS data UNION
SELECT 167 AS poradi, 'Ir-StdVypOrgove-WGmal'  AS kod, NULL AS nazev, NULL AS data UNION
SELECT 168 AS poradi, 'Ir-StdVypOrgove-AHry'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 169 AS poradi, 'Ir-StdVypOrgove-Pred'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 180 AS poradi, 'Nr-BonusyRPG'           AS kod, NULL AS nazev, NULL AS data UNION
SELECT 181 AS poradi, 'Nr-BonusyLKD'           AS kod, NULL AS nazev, NULL AS data UNION
SELECT 182 AS poradi, 'Nr-BonusyDrD'           AS kod, NULL AS nazev, NULL AS data UNION
SELECT 183 AS poradi, 'Nr-BonusyLarp'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 184 AS poradi, 'Nr-BonusyTurn'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 185 AS poradi, 'Nr-BonusyEpic'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 186 AS poradi, 'Nr-BonusyWGhry'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 187 AS poradi, 'Nr-BonusyWGmal'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 188 AS poradi, 'Nr-BonusyAHry'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 189 AS poradi, 'Nr-BonusyPred'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 200 AS poradi, 'Ir-UcastRPG'            AS kod, NULL AS nazev, NULL AS data UNION
SELECT 201 AS poradi, 'Ir-UcastLKD'            AS kod, NULL AS nazev, NULL AS data UNION
SELECT 202 AS poradi, 'Ir-UcastDrD'            AS kod, NULL AS nazev, NULL AS data UNION
SELECT 203 AS poradi, 'Ir-UcastLarp'           AS kod, NULL AS nazev, NULL AS data UNION
SELECT 204 AS poradi, 'Ir-UcastTurn'           AS kod, NULL AS nazev, NULL AS data UNION
SELECT 205 AS poradi, 'Ir-UcastEpic'           AS kod, NULL AS nazev, NULL AS data UNION
SELECT 206 AS poradi, 'Ir-UcastWGhry'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 207 AS poradi, 'Ir-UcastWGmal'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 208 AS poradi, 'Ir-UcastAHry'           AS kod, NULL AS nazev, NULL AS data UNION
SELECT 209 AS poradi, 'Ir-UcastAHEsc'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 220 AS poradi, 'Vr-Vynosy-RPG'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 221 AS poradi, 'Vr-Vynosy-LKD'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 222 AS poradi, 'Vr-Vynosy-DrD'          AS kod, NULL AS nazev, NULL AS data UNION
SELECT 223 AS poradi, 'Vr-Vynosy-Larp'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 224 AS poradi, 'Vr-Vynosy-Turn'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 225 AS poradi, 'Vr-Vynosy-Epic'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 226 AS poradi, 'Vr-Vynosy-WGhry'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 227 AS poradi, 'Vr-Vynosy-WGmal'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 228 AS poradi, 'Vr-Vynosy-AHry'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 229 AS poradi, 'Vr-Vynosy-AHEsc'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 240 AS poradi, 'Xr-Tricka-Zaklad'       AS kod, NULL AS nazev, NULL AS data UNION
SELECT 241 AS poradi, 'Xr-Tricka-Sleva'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 242 AS poradi, 'Nr-TrickaZdarma-Org'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 243 AS poradi, 'Nr-TrickaZdarma-Vyp'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 244 AS poradi, 'Nr-TrickaZdarma-Dobr'   AS kod, NULL AS nazev, NULL AS data UNION
SELECT 245 AS poradi, 'Xr-Tilka-Zaklad'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 246 AS poradi, 'Xr-Tilka-Sleva'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 247 AS poradi, 'Nr-TilkaZdarma-Org'     AS kod, NULL AS nazev, NULL AS data UNION
SELECT 248 AS poradi, 'Nr-TilkaZdarma-Vyp'     AS kod, NULL AS nazev, NULL AS data UNION
SELECT 249 AS poradi, 'Nr-TilkaZdarma-Dobr'    AS kod, NULL AS nazev, NULL AS data UNION
SELECT 260 AS poradi, 'Vr-KostkyDrevo'         AS kod, NULL AS nazev, NULL AS data UNION
SELECT 261 AS poradi, 'Vr-KostkyOld'           AS kod, NULL AS nazev, NULL AS data UNION
SELECT 262 AS poradi, 'Vr-Kostky'              AS kod, NULL AS nazev, NULL AS data UNION
SELECT 263 AS poradi, 'Ir-KostkyZdarma'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 264 AS poradi, 'Vr-Placky'              AS kod, NULL AS nazev, NULL AS data UNION
SELECT 265 AS poradi, 'Ir-PlackyZdarma'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 266 AS poradi, 'Vr-Nicknacky'           AS kod, NULL AS nazev, NULL AS data UNION
SELECT 267 AS poradi, 'Vr-Bloky'               AS kod, NULL AS nazev, NULL AS data UNION
SELECT 268 AS poradi, 'Vr-Ponozky'             AS kod, NULL AS nazev, NULL AS data UNION
SELECT 269 AS poradi, 'Vr-Tasky'               AS kod, NULL AS nazev, NULL AS data UNION
SELECT 280 AS poradi, 'Xr-Jidla-Snidane'       AS kod, NULL AS nazev, NULL AS data UNION
SELECT 281 AS poradi, 'Xr-Jidla-Hlavni'        AS kod, NULL AS nazev, NULL AS data UNION
SELECT 282 AS poradi, 'Nr-JidlaZdarma-Snidane' AS kod, NULL AS nazev, NULL AS data UNION
SELECT 283 AS poradi, 'Nr-JidlaZdarma-Hlavni'  AS kod, NULL AS nazev, NULL AS data
)) d
GROUP BY kod
) e
order by e.poradi asc
SQL,
);

$report->tFormat(get('format'));
