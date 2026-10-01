<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A night or a size can be suspended on its own while the room type above it, which nobody
// buys, stays suspended. Until now only the variant's own legacy row carried that state.
$this->q(<<<'SQL'
ALTER TABLE product_variant
    ADD state SMALLINT DEFAULT NULL
SQL);

$this->q(<<<'SQL'
UPDATE product_variant
    INNER JOIN shop_predmety AS vlastni_radek ON vlastni_radek.kod_predmetu = product_variant.code
SET product_variant.state = vlastni_radek.stav
SQL);

// A variant created in the new admin has no legacy row and is offered as its product is.
$this->q(<<<'SQL'
UPDATE product_variant
    INNER JOIN shop_predmety AS produkt ON produkt.id_predmetu = product_variant.product_id
SET product_variant.state = produkt.stav
WHERE product_variant.state IS NULL
SQL);

$this->q(<<<'SQL'
ALTER TABLE product_variant
    MODIFY state SMALLINT NOT NULL
SQL);
