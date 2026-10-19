<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Desk and accommodation sales long bypassed the variant stock counter and re-imports reset it
// to the full count. Recount it from what was actually sold this year. A variant's capacity is
// the row sharing its code, not its owner — accommodation nights hang under the room type.

$rok = ROCNIK;

$this->q(<<<SQL
UPDATE product_variant
    JOIN shop_predmety ON shop_predmety.kod_predmetu = product_variant.code
SET product_variant.remaining_quantity = shop_predmety.kusu_vyrobeno - (
    SELECT COUNT(*)
    FROM shop_nakupy
    WHERE shop_nakupy.rok = $rok
      AND shop_nakupy.variant_id = product_variant.id
)
WHERE shop_predmety.archived_at IS NULL
SQL,
);
