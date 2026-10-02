<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A grid cell sold one item back when every size and night was a catalog row of its own. The
// cell now names the product and the size or night it sells; without one it asks at the sale.
$this->q(<<<'SQL'
ALTER TABLE obchod_bunky
    ADD COLUMN IF NOT EXISTS variant_id BIGINT UNSIGNED DEFAULT NULL
SQL);

// Through the row's code, as every purchase was given its variant. A cell of a group owner gets
// the owner's own size too: its label names it ("XXXL", "Ponožky (vel. 42-45)").
$this->q(<<<'SQL'
UPDATE obchod_bunky
    INNER JOIN shop_predmety AS radek ON radek.id_predmetu = obchod_bunky.cil_id
    INNER JOIN product_variant ON product_variant.code = radek.kod_predmetu
SET obchod_bunky.variant_id = product_variant.id,
    obchod_bunky.cil_id     = product_variant.product_id
WHERE obchod_bunky.typ = 0
  AND obchod_bunky.variant_id IS NULL
SQL);

// IF EXISTS, so a run that failed after this point can be repeated.
$this->q(<<<'SQL'
ALTER TABLE obchod_bunky
    DROP FOREIGN KEY IF EXISTS FK_obchod_bunky_variant
SQL);

// A deleted variant leaves the cell asking for the size at the sale, not broken.
$this->q(<<<'SQL'
ALTER TABLE obchod_bunky
    ADD CONSTRAINT FK_obchod_bunky_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL
SQL);
