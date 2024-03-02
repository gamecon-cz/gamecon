<?php

use Gamecon\Role\Role;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Cas\DateTimeCz;

header('Content-type: application/json');
$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

// TODO: vykopírované z finance-report-ubytovani.php asi zbytečně moc dat dává.

// GET
$res = [];

$o = dbQuery(<<<SQL
SELECT
    uzivatele.id_uzivatele,
    uzivatele.login_uzivatele,
    uzivatele.jmeno_uzivatele,
    uzivatele.prijmeni_uzivatele,
    GROUP_CONCAT(DISTINCT IF(
        predmety.nazev LIKE CONVERT('Spacák%' USING utf8) COLLATE utf8_czech_ci,
        'Spacák',
        SUBSTR(predmety.nazev,1, LOCATE(' ', predmety.nazev))
    )) as typ,
    IF (COUNT(predmety.nazev) != (MAX(predmety.ubytovani_den) - MIN(predmety.ubytovani_den) +1 /* od 0 do 4, tedy 5 dní max */),
        GROUP_CONCAT(predmety.nazev),
        ''
    ) AS mezera_v_ubytovani,
    MIN(predmety.ubytovani_den) as prvni_noc,
    MAX(predmety.ubytovani_den) as posledni_noc,
    GROUP_CONCAT(DISTINCT IF(ubytovani.pokoj = '', NULL, ubytovani.pokoj)) as pokoj,
    uzivatele.ubytovan_s,
    '' AS pozice, -- placeholder kvůli pořadí, hodnotu dáme později, viz PHP foreach dále
    '' AS datum_narozeni, -- placeholder
    uzivatele.mesto_uzivatele,
    uzivatele.ulice_a_cp_uzivatele,
    uzivatele.typ_dokladu_totoznosti AS typ_dokladu,
    '' AS cislo_dokladu,
    uzivatele.statni_obcanstvi
FROM uzivatele_hodnoty uzivatele
JOIN platne_role_uzivatelu
    ON uzivatele.id_uzivatele=platne_role_uzivatelu.id_uzivatele AND platne_role_uzivatelu.id_role=$0 -- přihlášení na gc
JOIN shop_nakupy nakupy
    ON nakupy.id_uzivatele=uzivatele.id_uzivatele AND nakupy.rok=$1 -- nákupy tento rok
JOIN shop_predmety predmety
    ON predmety.id_predmetu=nakupy.id_predmetu AND predmety.typ=$2 -- info o předmětech k nákupům
LEFT JOIN ubytovani
    ON ubytovani.id_uzivatele=uzivatele.id_uzivatele  -- info o číslech pokoje
        AND ubytovani.rok=$1
        AND ubytovani.den = predmety.ubytovani_den
GROUP BY uzivatele.id_uzivatele
ORDER BY id_uzivatele
SQL,
    [
        Role::PRIHLASEN_NA_LETOSNI_GC,
        ROCNIK,
        TypPredmetu::UBYTOVANI,
    ],
);

$vystup = [];
while ($r = mysqli_fetch_assoc($o)) {
    $u                   = Uzivatel::zId($r['id_uzivatele']);
    $r['pozice']         = $u->status(false);
    $r['datum_narozeni'] = $u->datumNarozeni()->format(DateTimeCz::FORMAT_DATUM_STANDARD);
    $r['cislo_dokladu']  = $u->cisloOp();
    $vystup[]            = $r;
}

$res = $vystup;

// if ($_SERVER["REQUEST_METHOD"] == "POST") {
//   $body = postBody();
// }

echo json_encode($res, $config);
