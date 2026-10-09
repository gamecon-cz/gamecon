<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A breakfast was recognised by a food product's name starting with "Snídaně". A tag says it
// directly, so renaming a product cannot silently turn it into something else.

$this->q(<<<'SQL'
INSERT INTO product_tag (code, name, description, created_at, updated_at)
SELECT 'snidane', 'Snídaně', NULL, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM product_tag
    WHERE product_tag.code = 'snidane'
)
SQL);

// Keyed on kod_predmetu, not on the name: under utf8mb4_czech_ci a name search folds é→e but
// not č→c, while the code is plain ASCII and the same word in every year's catalog.
$this->q(<<<'SQL'
INSERT INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu, breakfast_tag.id
FROM shop_predmety
INNER JOIN product_product_tag AS food_link
    ON food_link.product_id = shop_predmety.id_predmetu
INNER JOIN product_tag AS food_tag
    ON food_tag.id = food_link.tag_id
    AND food_tag.code = 'jidlo'
INNER JOIN product_tag AS breakfast_tag
    ON breakfast_tag.code = 'snidane'
LEFT OUTER JOIN product_product_tag AS already_tagged
    ON already_tagged.product_id = shop_predmety.id_predmetu
    AND already_tagged.tag_id = breakfast_tag.id
WHERE shop_predmety.kod_predmetu LIKE '%snidane%'
    AND already_tagged.product_id IS NULL
SQL);

// Deploy runs migrations without strict mode, so nothing else would stop a breakfast the old
// name rule found from quietly dropping out: it would just no longer be cancelled.
$bezTagu = $this->q(<<<'SQL'
SELECT shop_predmety.kod_predmetu
FROM shop_predmety
INNER JOIN product_product_tag AS food_link
    ON food_link.product_id = shop_predmety.id_predmetu
INNER JOIN product_tag AS food_tag
    ON food_tag.id = food_link.tag_id
    AND food_tag.code = 'jidlo'
WHERE TRIM(shop_predmety.nazev) LIKE 'Snídaně%'
    AND NOT EXISTS (
        SELECT 1
        FROM product_product_tag AS breakfast_link
        INNER JOIN product_tag AS breakfast_tag
            ON breakfast_tag.id = breakfast_link.tag_id
        WHERE breakfast_link.product_id = shop_predmety.id_predmetu
            AND breakfast_tag.code = 'snidane'
    )
ORDER BY shop_predmety.kod_predmetu
SQL)->fetchAll(PDO::FETCH_COLUMN);

if ($bezTagu !== []) {
    $zprava = 'Food products named like a breakfast whose code does not say "snidane" would stop being breakfasts: '
        . implode(', ', $bezTagu)
        . '. Give each the "snidane" tag in product_product_tag, then run the migration again.';

    throw new RuntimeException($zprava);
}
