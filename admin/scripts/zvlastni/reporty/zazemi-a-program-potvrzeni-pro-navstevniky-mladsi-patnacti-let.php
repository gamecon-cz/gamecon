<?php
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Role\Role;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura;

$idSystemUzivatele = Uzivatel::SYSTEM;

$mysqliResult = dbQuery(<<<SQL
SELECT id_uzivatele,
       login_uzivatele,
       jmeno_uzivatele,
       prijmeni_uzivatele,
       ulice_a_cp_uzivatele,
       mesto_uzivatele,
       stat_uzivatele,
       psc_uzivatele,
       telefon_uzivatele,
       datum_narozeni,
       email1_uzivatele,
       nechce_maily,
       mrtvy_mail,
       forum_razeni,
       zustatek,
       pohlavi,
       registrovan,
       ubytovan_s,
       poznamka,
       pomoc_typ,
       pomoc_vice,
       op,
       potvrzeni_zakonneho_zastupce,
       (SELECT 'prihlasen'
        FROM platne_role_uzivatelu
        WHERE uzivatele_hodnoty.id_uzivatele = platne_role_uzivatelu.id_uzivatele
          AND platne_role_uzivatelu.id_role = $2
        ) AS prihlasen_na_gc
FROM uzivatele_hodnoty
WHERE id_uzivatele != {$idSystemUzivatele}
    AND (z_rychloregistrace = 0 OR datum_narozeni != DATE(registrovan))
    AND (YEAR($1) - YEAR(datum_narozeni) -
       IF(DATE_FORMAT($1, '%m%d') < DATE_FORMAT(datum_narozeni, '%m%d'), 1, 0)
    ) < 15
ORDER BY prihlasen_na_gc DESC,
         COALESCE(potvrzeni_zakonneho_zastupce, '0001-01-01') ASC,
         registrovan DESC;
SQL
    , [1 => GC_BEZI_OD, 2 => Role::PRIHLASEN_NA_LETOSNI_GC],
);

$data = [];
while ($row = mysqli_fetch_assoc($mysqliResult)) {
    $row[UzivateleHodnotySqlStruktura::OP] = (string)$row[UzivateleHodnotySqlStruktura::OP] !== ''
        ? Sifrovatko::desifruj($row[UzivateleHodnotySqlStruktura::OP])
        : '';
    $data[]                        = $row;
}
Report::zPole($data)->tFormat(get('format'));
