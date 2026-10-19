<?php
declare(strict_types=1);

// Pouští se přes `porovnej-ceny.sh`, který skript zkopíruje do `symfony/var/` — odtud
// cesta k zavaděči sedí. Spuštěný rovnou z `bin-diff/` na ní spadne.
require __DIR__ . '/../../nastaveni/zavadec.php';

const UZIVATEL = 6586;
$role = [
    'bez role'           => null,
    'GC2026_VYPRAVEC'    => -202600006,
    'GC2026_PARTNER'     => -202600013,
    'GC2026_BRIGADNIK'   => -202600025,
    'ORGANIZATOR_ZDARMA' => 2,
    'PUL_ORG_UBYTKO'     => 21,
];

// Predmety, ktere chceme ocenit: par jidel, tricko, merch, ubytovani.
// Legacy má `typ` ve sloupci a `Cenik::cena()` ho čte; nová větev typ nemá, kategorie je štítek
// produktu a `Cenik::cena()` čte jeho kód.
$kodKategoriePodleTypu = [1 => 'predmet', 2 => 'ubytovani', 3 => 'tricko', 4 => 'jidlo', 5 => 'vstupne'];
$typVeSloupci = (bool) dbOneLine("SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = 'shop_predmety' AND column_name = 'typ'");
// Z každého typu pár kusů — jinak `LIMIT` sežerou kostky a jídlo se vůbec neporovná.
$predmety = [];
foreach ([1 => 'tričko', 2 => 'ubytování', 3 => 'vstupné', 4 => 'jídlo', 5 => 'merch'] as $typ => $popisTypu) {
    $radky = $typVeSloupci
        ? dbFetchAll("SELECT * FROM shop_predmety WHERE typ = $typ ORDER BY id_predmetu DESC LIMIT 8")
        : dbFetchAll(<<<'SQL'
            SELECT shop_predmety.*, product_tag.code AS kategorie
            FROM shop_predmety
            INNER JOIN product_product_tag ON product_product_tag.product_id = shop_predmety.id_predmetu
            INNER JOIN product_tag ON product_tag.id = product_product_tag.tag_id AND product_tag.code = $0
            ORDER BY shop_predmety.id_predmetu DESC
            LIMIT 8
            SQL,
            [0 => $kodKategoriePodleTypu[$typ]],
        );
    foreach ($radky as $r) {
        $r['_typ'] = $popisTypu;
        $predmety[] = $r;
    }
}

// Role se probandovi přepisují dokola, aby se ocenil každou z nich. Bez zálohy by po
// doběhnutí zůstal bez rolí — a protože `porovnej-ceny.sh` pouští tenhle skript i na
// legacy větvi, rozbilo by to referenční stranu porovnání, ne jen testovací data.
$puvodniRole = dbFetchAll(
    'SELECT id_role, posazen, posadil FROM uzivatele_role WHERE id_uzivatele = $1',
    [1 => UZIVATEL],
);

$vratPuvodniRole = static function () use ($puvodniRole) {
    dbQuery('DELETE FROM uzivatele_role WHERE id_uzivatele = $1', [1 => UZIVATEL]);
    foreach ($puvodniRole as $puvodni) {
        dbQuery(
            'INSERT INTO uzivatele_role SET id_uzivatele = $1, id_role = $2, posazen = $3, posadil = $4',
            [1 => UZIVATEL, 2 => $puvodni['id_role'], 3 => $puvodni['posazen'], 4 => $puvodni['posadil']],
        );
    }
};

// Fatal uprostřed měření by jinak nechal probanda s rolí, kterou mu nastavil skript.
// Běh navíc na konci neuškodí — obnovuje se na absolutní hodnoty ze zálohy — ale ať
// se zbytečně neopakuje, hlídá se, jestli už proběhl.
$roleVraceny = false;
$vratRoleJednou = static function () use ($vratPuvodniRole, &$roleVraceny) {
    if ($roleVraceny) {
        return;
    }
    $roleVraceny = true;
    $vratPuvodniRole();
};
register_shutdown_function($vratRoleJednou);

$vysledek = [];
foreach ($role as $nazevRole => $idRole) {
    dbQuery('DELETE FROM uzivatele_role WHERE id_uzivatele = $1', [1 => UZIVATEL]);
    if ($idRole !== null) {
        dbQuery('INSERT INTO uzivatele_role SET id_uzivatele = $1, id_role = $2', [1 => UZIVATEL, 2 => $idRole]);
    }
    $u = \Uzivatel::zId(UZIVATEL, true);
    $cenik = new \Gamecon\Uzivatel\Cenik(
        $u,
        $u->finance(),
        \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zGlobals(),
    );
    foreach ($predmety as $r) {
        $nazev = $r['nazev'];
        try {
            $cena = $cenik->cena($r)->finalPrice;
        } catch (\Throwable $e) {
            $cena = 'CHYBA: ' . $e->getMessage();
        }
        $vysledek[] = sprintf('%-20s | %-10s | %6d | %-34s | %s', $nazevRole, $r['_typ'], $r['id_predmetu'], mb_substr($nazev, 0, 34), is_string($cena) ? $cena : number_format((float) $cena, 2, '.', ''));
    }
}
$vratRoleJednou();
echo implode("\n", $vysledek), "\n";
