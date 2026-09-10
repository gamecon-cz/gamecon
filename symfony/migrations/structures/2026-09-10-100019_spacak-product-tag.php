<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// When rooms are short the festival can restrict accommodation to sleeping bags. Legacy
// picks the surviving type by the literal name "Spacák"; a tag says the same thing without
// tying the rule to a product's wording.

$this->q(<<<SQL
INSERT IGNORE INTO product_tag (code, name, created_at)
VALUES ('spacak', 'Spacák', NOW())
SQL);

// Existing sleeping-bag products are archived years, but tagging them keeps the data honest
// and means a revived product is tagged by copying an old one.
$this->q(<<<SQL
INSERT IGNORE INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu, product_tag.id
FROM shop_predmety
JOIN product_tag ON product_tag.code = 'spacak'
WHERE shop_predmety.nazev LIKE 'Spacák%'
SQL);
