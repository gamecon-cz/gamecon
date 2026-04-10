<?php

/** @var \Godric\DbMigrations\Migration $this */

// Ensure 'hotel' tag exists (product_tag table was created by new-eshop migration)
$this->q(<<<SQL
INSERT IGNORE INTO product_tag (code, name, description, created_at)
VALUES ('hotel', 'Hotel', 'Hotelové ubytování (snídaně v ceně)', NOW())
SQL,
);

// Migrate podtyp='hotel' → hotel tag and drop column (if it exists)
$podtypColumnExists = $this->q(<<<SQL
SELECT COUNT(*) FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'shop_predmety'
  AND COLUMN_NAME = 'podtyp'
SQL,
)->fetch(PDO::FETCH_COLUMN);

if ($podtypColumnExists) {
    $this->q(<<<SQL
INSERT INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu, product_tag.id
FROM shop_predmety
CROSS JOIN product_tag
WHERE shop_predmety.podtyp = 'hotel'
  AND product_tag.code = 'hotel'
  AND NOT EXISTS (
      SELECT 1 FROM product_product_tag
      WHERE product_product_tag.product_id = shop_predmety.id_predmetu
        AND product_product_tag.tag_id = product_tag.id
  )
SQL,
    );

    $this->q('ALTER TABLE shop_predmety DROP COLUMN podtyp');
}
