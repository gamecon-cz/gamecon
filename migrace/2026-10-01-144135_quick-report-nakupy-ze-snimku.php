<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// The "nakupy" quick report lists purchases of every year, so it reads what each was sold as: the
// purchase keeps the name and the size or day apart. Rewritten only while it reads exactly as shipped.
$puvodniDotaz = "select n.id_uzivatele, n.id_predmetu, p.nazev, n.rok, n.cena_nakupni, n.datum\r\nfrom shop_nakupy n\r\njoin shop_predmety p\r\non p.id_predmetu = n.id_predmetu";
$novyDotaz = "select shop_nakupy.id_uzivatele, shop_nakupy.id_predmetu, shop_nakupy.product_name AS nazev, shop_nakupy.variant_name AS varianta,\r\n       shop_nakupy.rok, shop_nakupy.cena_nakupni, shop_nakupy.datum\r\nfrom shop_nakupy";

// `q()` takes no bound parameters, and the stored SQL is quoted through PDO.
$this->q(
    'UPDATE reporty_quick SET dotaz = ' . $this->connection->quote($novyDotaz)
    . ' WHERE dotaz = ' . $this->connection->quote($puvodniDotaz),
);
