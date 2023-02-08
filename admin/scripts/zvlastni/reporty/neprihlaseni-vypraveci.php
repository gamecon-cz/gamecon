<?php

use Gamecon\Shop\Shop;
use Gamecon\Role\Zidle;

require __DIR__ . '/sdilene-hlavicky.php';

$sledovaneZidle = dbOneArray('SELECT id_zidle FROM r_prava_zidle WHERE id_prava = $0', [P_REPORT_NEUBYTOVANI]);
if (empty($sledovaneZidle)) {
    die('Žádná židle nemá nastaveno právo, aby se vypisovala v tomto reportu.');
}

$sledovaneZidleSql = implode(',', $sledovaneZidle);

$r = Report::zSql('
  SELECT
    u.id_uzivatele,
    u.login_uzivatele,
    u.jmeno_uzivatele,
    u.prijmeni_uzivatele,
    u.email1_uzivatele,
    u.telefon_uzivatele,
    zs.jmeno_zidle,
    akt. sekce,
    IF(zp.id_zidle, "", "ne") as přihlášen,
    IF(ub.id_uzivatele, "", "ne") as ubytován,
    IF(akt.id_uzivatele, "", "ne") as "vede aktivity"
  FROM platne_zidle_uzivatelu z
  JOIN r_zidle_soupis zs USING (id_zidle)
  LEFT JOIN platne_zidle_uzivatelu zp ON (z.id_uzivatele = zp.id_uzivatele AND zp.id_zidle = ' . Zidle::PRIHLASEN_NA_LETOSNI_GC . ')
  JOIN uzivatele_hodnoty u ON (u.id_uzivatele = z.id_uzivatele)
  LEFT JOIN (
      SELECT id_uzivatele, GROUP_CONCAT(DISTINCT at.typ_1pmn SEPARATOR ", ") as sekce
      FROM akce_organizatori ao
      JOIN akce_seznam a ON (ao.id_akce = a.id_akce AND a.rok = ' . ROK . ')
      JOIN akce_typy at ON (a.typ = at.id_typu)
      GROUP BY id_uzivatele
    ) akt ON (akt.id_uzivatele = z.id_uzivatele)
  LEFT JOIN (
      SELECT id_uzivatele FROM shop_nakupy sn
      JOIN shop_predmety sp ON (sp.id_predmetu = sn.id_predmetu AND sp.typ = ' . Shop::UBYTOVANI . ')
      WHERE sn.rok = ' . ROK . '
      GROUP BY sn.id_uzivatele
    ) ub ON (ub.id_uzivatele = z.id_uzivatele)
  WHERE z.id_zidle IN (' . $sledovaneZidleSql . ') AND (
      zp.id_zidle IS NULL OR
      ub.id_uzivatele IS NULL OR
      akt.id_uzivatele IS NULL AND z.id_zidle = ' . Zidle::LETOSNI_VYPRAVEC . '
    )
  GROUP BY u.id_uzivatele
  ORDER BY sekce, jmeno_zidle, prijmeni_uzivatele, jmeno_uzivatele
');

$r->tHtml();
