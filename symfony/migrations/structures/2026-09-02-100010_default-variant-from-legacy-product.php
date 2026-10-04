<?php

/** @var \Godric\DbMigrations\Migration $this */

// The cart addresses products through variants, but legacy products have no variant rows —
// every product IS its own single variant until someone splits it (t-shirt sizes, hotel nights).
// Without this backfill the cart can address nothing at all on a database restored from a dump:
// /cart/meals skips variantless products, and adding to the cart requires a variantId.
//
// A variant mirrors the product's own values, so the legacy row provides all of them. Price and
// reserved_for_organizers stay NULL, which the schema defines as "inherit from the parent product",
// so a later price change on the product keeps propagating instead of going stale here.

$this->q(<<<'SQL'
INSERT INTO product_variant (product_id, name, code, price, remaining_quantity, reserved_for_organizers, accommodation_day, position)
SELECT shop_predmety.id_predmetu,
       shop_predmety.nazev,
       shop_predmety.kod_predmetu,
       NULL,
       shop_predmety.kusu_vyrobeno,
       NULL,
       shop_predmety.ubytovani_den,
       0
FROM shop_predmety
WHERE NOT EXISTS (
    SELECT 1 FROM product_variant WHERE product_variant.product_id = shop_predmety.id_predmetu
)
  -- code is UNIQUE across all variants, so a product whose code is already used as some other
  -- product's variant code has to be skipped rather than abort the migration.
  AND NOT EXISTS (
    SELECT 1 FROM product_variant AS jine WHERE jine.code = shop_predmety.kod_predmetu
)
SQL,
);

// Historical purchases predate variants; point them at the default variant so order history and
// capacity counting see the same rows the cart writes from now on. Purchases carry their own price
// snapshot, so only the link is filled in. Where a product already has several variants (sizes,
// nights), a purchase cannot say which one it was, so it goes to the lowest-positioned one rather
// than whichever the join happens to reach first.
$this->q(<<<'SQL'
UPDATE shop_nakupy
JOIN product_variant ON product_variant.id = (
    SELECT vychozi.id
    FROM product_variant AS vychozi
    WHERE vychozi.product_id = shop_nakupy.id_predmetu
    ORDER BY vychozi.position, vychozi.id
    LIMIT 1
)
SET shop_nakupy.variant_id = product_variant.id,
    shop_nakupy.variant_name = product_variant.name
WHERE shop_nakupy.variant_id IS NULL
SQL,
);
