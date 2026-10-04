<?php

use App\Enum\ProductTagCode;
use Gamecon\Role\Role;
use Gamecon\Pravo;

require __DIR__ . '/sdilene-hlavicky.php';

$sledovaneRole = dbOneArray('SELECT id_role FROM prava_role WHERE id_prava = $0', [Pravo::VYPISOVAT_V_REPORTU_NEUBYTOVANYCH]);
if (empty($sledovaneRole)) {
    die('Žádná role nemá nastaveno právo, aby se vypisovala v tomto reportu.');
}

$sledovaneRoleSql = implode(',', $sledovaneRole);

$report = Report::zSql('
  SELECT
    u.id_uzivatele,
    u.login_uzivatele,
    u.jmeno_uzivatele,
    u.prijmeni_uzivatele,
    u.email1_uzivatele,
    u.telefon_uzivatele,
    zs.nazev_role,
    akt. sekce,
    IF(zp.id_role, "", "ne") as přihlášen,
    IF(ub.id_uzivatele, "", "ne") as ubytován,
    IF(akt.id_uzivatele, "", "ne") as "vede aktivity"
  FROM platne_role_uzivatelu z
  JOIN role_seznam zs USING (id_role)
  LEFT JOIN platne_role_uzivatelu zp ON (z.id_uzivatele = zp.id_uzivatele AND zp.id_role = ' . Role::PRIHLASEN_NA_LETOSNI_GC . ')
  JOIN uzivatele_hodnoty u ON (u.id_uzivatele = z.id_uzivatele)
  LEFT JOIN (
      SELECT id_uzivatele, GROUP_CONCAT(DISTINCT at.typ_1pmn SEPARATOR ", ") as sekce
      FROM akce_organizatori ao
      JOIN akce_seznam a ON (ao.id_akce = a.id_akce AND a.rok = ' . ROCNIK . ')
      JOIN akce_typy at ON (a.typ = at.id_typu)
      GROUP BY id_uzivatele
    ) akt ON (akt.id_uzivatele = z.id_uzivatele)
  LEFT JOIN (
      SELECT id_uzivatele FROM shop_nakupy sn
      JOIN product_variant ON (product_variant.id = sn.variant_id)
      JOIN product_product_tag ON (product_product_tag.product_id = product_variant.product_id)
      JOIN product_tag ON (product_tag.id = product_product_tag.tag_id AND product_tag.code = $0)
      WHERE sn.rok = ' . ROCNIK . '
      GROUP BY sn.id_uzivatele
    ) ub ON (ub.id_uzivatele = z.id_uzivatele)
  WHERE z.id_role IN (' . $sledovaneRoleSql . ') AND (
      zp.id_role IS NULL OR
      ub.id_uzivatele IS NULL OR
      akt.id_uzivatele IS NULL AND z.id_role = ' . Role::LETOSNI_VYPRAVEC . '
    )
  GROUP BY u.id_uzivatele
  ORDER BY sekce, nazev_role, prijmeni_uzivatele, jmeno_uzivatele
', [0 => ProductTagCode::UBYTOVANI->value]);

$report->tFormat(get('format'));
