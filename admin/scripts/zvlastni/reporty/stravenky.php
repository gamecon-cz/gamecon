<?php

use App\Entity\ProductTag;
use Gamecon\Role\Role;
use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura;

require __DIR__ . '/sdilene-hlavicky.php';

$t = new XTemplate(__DIR__ . '/stravenky.xtpl');

$systemoveNastaveni ??= SystemoveNastaveni::zGlobals();

$rolePrihlasenNaLetosniGc = Role::PRIHLASEN_NA_LETOSNI_GC;
$rocnik                   = $systemoveNastaveni->rocnik();
$typJidlo                 = Shop::JIDLO;
$idUzivatele              = (int)get('id_uzivatele')
    ?: null;
$uzivatelFiltrSql         = $idUzivatele
    ? "AND uzivatele.id_uzivatele = {$idUzivatele}"
    : '';
$typUbytovani             = Shop::UBYTOVANI;
$o                        = dbQuery(<<<SQL
    SELECT
      uzivatele.id_uzivatele, uzivatele.login_uzivatele, predmety.nazev,
      FIELD(SUBSTRING(TRIM(nazev), POSITION(' ' IN TRIM(nazev)) + 1), 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle') AS poradi_dne,
      FIELD(SUBSTRING(TRIM(nazev), 1, POSITION(' ' IN TRIM(nazev)) - 1), 'Snídaně', 'Oběd', 'Večeře') AS poradi_jidla
    FROM uzivatele_hodnoty AS uzivatele
    JOIN platne_role_uzivatelu AS role
        ON role.id_uzivatele = uzivatele.id_uzivatele AND role.id_role = {$rolePrihlasenNaLetosniGc}
    JOIN shop_nakupy AS nakupy
        ON nakupy.id_uzivatele = uzivatele.id_uzivatele AND nakupy.rok = {$rocnik}
    JOIN shop_predmety_s_typem AS predmety
        ON predmety.id_predmetu = nakupy.id_predmetu AND predmety.typ = {$typJidlo}
    LEFT JOIN shop_nakupy AS nakupy_ubytovani
        ON nakupy_ubytovani.id_uzivatele = uzivatele.id_uzivatele
        AND nakupy_ubytovani.rok = {$rocnik}
    LEFT JOIN shop_predmety_s_typem AS predmety_ubytovani
        ON predmety_ubytovani.id_predmetu = nakupy_ubytovani.id_predmetu
        AND predmety_ubytovani.typ = {$typUbytovani}
        AND predmety_ubytovani.podtyp = $0
        AND predmety_ubytovani.ubytovani_den = predmety.ubytovani_den
    WHERE TRUE {$uzivatelFiltrSql}
      AND NOT (
        TRIM(predmety.nazev) LIKE 'Snídaně%'
        AND predmety_ubytovani.id_predmetu IS NOT NULL
      )
    ORDER BY uzivatele.id_uzivatele,
             -- "bylo lepší, jak vedly zprava doleva, tj. naopak. Nalevo je totiž ID člověka a jak si stravenky postupně odtrhává, je lepší, když začne na druhé straně, aby měl pořád balíček stravenek se svým ID a jménem" Gandalf 10. červenec 2023 20:57
             poradi_dne DESC,
             poradi_jidla DESC
SQL,
    [0 => ProductTag::HOTEL],
);

$res = [];

while ($r = $o->fetch(PDO::FETCH_ASSOC)) {
    $res[] = $r;
}

if ($idUzivatele && !Uzivatel::zId($idUzivatele)) {
    $t->assign('idUzivatele', $idUzivatele);
    $t->parse('stravenky.nenalezen');
} elseif ($idUzivatele && $res === []) {
    $t->assign('idUzivatele', $idUzivatele);
    $t->parse('stravenky.zadnaJidla');
} else {
    $config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $t->assign("data", json_encode($res, $config));
    $t->parse('stravenky.obsah');
}
$t->parse('stravenky');
$t->out('stravenky');
