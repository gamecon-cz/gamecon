<?php

use App\Enum\ProductTagCode;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\XTemplate\XTemplate;

require __DIR__ . '/sdilene-hlavicky.php';

$t = new XTemplate(__DIR__ . '/stravenky.xtpl');

$systemoveNastaveni ??= SystemoveNastaveni::zGlobals();

$rocnik   = $systemoveNastaveni->rocnik();
$prvniDen = DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK;

$o = dbQuery(<<<SQL
    SELECT
      shop_predmety.nazev,
      FIELD(SUBSTRING(TRIM(shop_predmety.nazev), POSITION(' ' IN TRIM(shop_predmety.nazev)) + 1), 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle') AS poradi_dne,
      FIELD(SUBSTRING(TRIM(shop_predmety.nazev), 1, POSITION(' ' IN TRIM(shop_predmety.nazev)) - 1), 'Snídaně', 'Oběd', 'Večeře') AS poradi_jidla
    FROM shop_predmety
    JOIN product_product_tag ON product_product_tag.product_id = shop_predmety.id_predmetu
    JOIN product_tag ON product_tag.id = product_product_tag.tag_id AND product_tag.code = $0
    -- an archived product belongs to the year it was archived, one still on offer to this year
    WHERE COALESCE(YEAR(shop_predmety.archived_at), {$rocnik}) = {$rocnik}
      AND shop_predmety.ubytovani_den >= {$prvniDen}
    ORDER BY poradi_dne DESC, poradi_jidla DESC
SQL,
    [0 => ProductTagCode::JIDLO->value],
);

$jidla = [];
while ($r = $o->fetch(PDO::FETCH_ASSOC)) {
    $jidla[] = $r;
}

$pocetJidel   = count($jidla);
$pocetBunek   = 24; // 3×8 grid = one page
$pocetOpakovani = $pocetJidel > 0 ? (int)ceil($pocetBunek / $pocetJidel) : 0;

$res = [];
for ($i = 0; $i < $pocetOpakovani; $i++) {
    foreach ($jidla as $jidlo) {
        $res[] = [
            'id_uzivatele'    => (string)$i,
            'login_uzivatele' => 'Bianco stravenka',
            'nazev'           => $jidlo['nazev'],
            'poradi_dne'      => $jidlo['poradi_dne'],
            'poradi_jidla'    => $jidlo['poradi_jidla'],
        ];
    }
}

$res = array_slice($res, 0, $pocetBunek);

$config = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$t->assign("data", json_encode($res, $config));
$t->parse('stravenky.obsah');
$t->parse('stravenky');
$t->out('stravenky');
