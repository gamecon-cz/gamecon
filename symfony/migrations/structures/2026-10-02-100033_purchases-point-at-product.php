<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A purchase points at its product (a room type, a shirt model) and names the night or size it
// bought through its variant. Every reader now takes the item from the variant, so a size's or
// night's own catalog row no longer has to be what the purchase points at.

$this->q(<<<'SQL'
UPDATE shop_nakupy
    INNER JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
SET shop_nakupy.id_predmetu = product_variant.product_id
WHERE shop_nakupy.id_predmetu <> product_variant.product_id
SQL);

$this->q(<<<'SQL'
UPDATE shop_nakupy_zrusene
    INNER JOIN product_variant ON product_variant.id = shop_nakupy_zrusene.variant_id
SET shop_nakupy_zrusene.id_predmetu = product_variant.product_id
WHERE shop_nakupy_zrusene.id_predmetu <> product_variant.product_id
SQL);

// The debug quick report of nights booked and cancelled read the night from the purchase's row;
// rewritten only while it reads as shipped. `q()` takes no bound parameters, so values are quoted.
foreach (['shop_nakupy', 'shop_nakupy_zrusene'] as $tabulka) {
    $this->q(
        'UPDATE reporty_quick SET dotaz = REPLACE(dotaz, '
        . $this->connection->quote("JOIN shop_predmety_s_typem ON shop_predmety_s_typem.id_predmetu = {$tabulka}.id_predmetu")
        . ', '
        . $this->connection->quote("JOIN shop_varianty_s_typem AS shop_predmety_s_typem ON shop_predmety_s_typem.id_varianty = {$tabulka}.variant_id")
        . ') WHERE nazev = ' . $this->connection->quote('Adrijaned: debugovací report'),
    );
}
