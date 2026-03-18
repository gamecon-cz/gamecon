<?php
require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Role\Role;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$rocnik              = $systemoveNastaveni->rocnik();
$dorazil             = StavPrihlaseni::PRIHLASEN_A_DORAZIL;
$dorazilJakoNahradik = StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK;
$idRolePritomen      = Role::PRITOMEN_NA_LETOSNIM_GC($rocnik);

$report = Report::zSql(<<<SQL
SELECT uzivatele_hodnoty.id_uzivatele,
       uzivatele_hodnoty.login_uzivatele AS login,
       uzivatele_hodnoty.jmeno_uzivatele AS jmeno,
       uzivatele_hodnoty.prijmeni_uzivatele AS prijmeni,
       uzivatele_hodnoty.email1_uzivatele AS email,
       uzivatele_hodnoty.telefon_uzivatele AS telefon,
       GROUP_CONCAT(DISTINCT akce_seznam.nazev_akce ORDER BY akce_seznam.nazev_akce SEPARATOR ', ') AS aktivity
FROM akce_prihlaseni
JOIN akce_seznam ON akce_seznam.id_akce = akce_prihlaseni.id_akce AND akce_seznam.rok = $rocnik
JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = akce_prihlaseni.id_uzivatele
WHERE akce_prihlaseni.id_stavu_prihlaseni IN ($dorazil, $dorazilJakoNahradik)
    AND NOT EXISTS(
        SELECT 1
        FROM platne_role_uzivatelu
        WHERE platne_role_uzivatelu.id_uzivatele = akce_prihlaseni.id_uzivatele
            AND platne_role_uzivatelu.id_role = $idRolePritomen
    )
GROUP BY uzivatele_hodnoty.id_uzivatele
ORDER BY uzivatele_hodnoty.prijmeni_uzivatele, uzivatele_hodnoty.jmeno_uzivatele
SQL,
);
$report->tFormat(get('format'));
