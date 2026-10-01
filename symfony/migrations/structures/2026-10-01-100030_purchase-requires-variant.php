<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Readers move from a purchase's id_predmetu to its variant, so a purchase without one would
// silently drop out of what is charged. Every writer sets it; the database now holds them to it.
$this->q(<<<'SQL'
UPDATE shop_nakupy
    INNER JOIN shop_predmety AS vlastni_radek ON vlastni_radek.id_predmetu = shop_nakupy.id_predmetu
    INNER JOIN product_variant ON product_variant.code = vlastni_radek.kod_predmetu
SET shop_nakupy.variant_id = product_variant.id
WHERE shop_nakupy.variant_id IS NULL
SQL);

$this->q(<<<'SQL'
ALTER TABLE shop_nakupy
    DROP FOREIGN KEY FK_nakupy_variant
SQL);

$this->q(<<<'SQL'
ALTER TABLE shop_nakupy
    MODIFY variant_id BIGINT UNSIGNED NOT NULL
SQL);

$this->q(<<<'SQL'
ALTER TABLE shop_nakupy
    ADD CONSTRAINT FK_nakupy_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE RESTRICT
SQL);
