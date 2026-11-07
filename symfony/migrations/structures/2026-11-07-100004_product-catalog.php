<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A product is described by tags instead of the type number, by archived_at instead of the model year and by
// breakfast_included instead of the 'hotel' subtype. The old columns stay until the whole move is done.

// The first seven are the former types 1-7 and are mutually exclusive categories; the rest add one attribute
// each to a product that already has a category. Colour shirts carry who they are for, not their colour,
// because the colour changes every year and the audience does not.
$this->q(<<<'SQL'
INSERT INTO product_tag (code, name, description, created_at, updated_at)
VALUES ('predmet', 'Předmět', 'Merch (kostky, odznaky, zápisníky)', NOW(), NOW()),
       ('ubytovani', 'Ubytování', NULL, NOW(), NOW()),
       ('tricko', 'Tričko', NULL, NOW(), NOW()),
       ('jidlo', 'Jídlo', NULL, NOW(), NOW()),
       ('vstupne', 'Vstupné', NULL, NOW(), NOW()),
       ('parcon', 'ParCon mini-akce', NULL, NOW(), NOW()),
       ('proplaceni_bonusu', 'Výplata bonusu (interní)', NULL, NOW(), NOW()),
       ('mikina', 'Mikina', NULL, NOW(), NOW()),
       ('spacak', 'Spacák', NULL, NOW(), NOW()),
       ('tricko_vypravecske', 'Vypravěčské tričko', NULL, NOW(), NOW()),
       ('tricko_organizatorske', 'Organizátorské tričko', NULL, NOW(), NOW()),
       ('snidane', 'Snídaně', NULL, NOW(), NOW())
SQL);

$this->q(<<<'SQL'
INSERT INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu,
       product_tag.id
FROM shop_predmety
    INNER JOIN product_tag ON product_tag.code = CASE shop_predmety.typ
        WHEN 1 THEN 'predmet'
        WHEN 2 THEN 'ubytovani'
        WHEN 3 THEN 'tricko'
        WHEN 4 THEN 'jidlo'
        WHEN 5 THEN 'vstupne'
        WHEN 6 THEN 'parcon'
        WHEN 7 THEN 'proplaceni_bonusu'
    END
SQL);

$withoutCategory = (int) $this->q(<<<'SQL'
SELECT COUNT(*)
FROM shop_predmety
WHERE NOT EXISTS (
    SELECT 1
    FROM product_product_tag
    WHERE product_product_tag.product_id = shop_predmety.id_predmetu
)
SQL)->fetchColumn();
if ($withoutCategory > 0) {
    throw new RuntimeException("{$withoutCategory} products have a type outside 1-7 and would end up without a category tag.");
}

// A hoodie has no attribute to move to, so it is a tag. Hotel rooms include breakfast.
$this->q(<<<'SQL'
INSERT INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu,
       product_tag.id
FROM shop_predmety
    INNER JOIN product_tag ON product_tag.code = 'mikina'
WHERE shop_predmety.podtyp = 'mikina'
SQL);

$this->q(<<<'SQL'
UPDATE shop_predmety
SET breakfast_included = 1
WHERE podtyp = 'hotel'
SQL);

// Sleeping bags are told apart by the literal name "Spacák" in the legacy code; a tag survives a rename.
$this->q(<<<'SQL'
INSERT INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu,
       product_tag.id
FROM shop_predmety
    INNER JOIN product_tag ON product_tag.code = 'spacak'
WHERE shop_predmety.nazev LIKE 'Spacák%'
SQL);

// Keyed on kod_predmetu, not on the name: the code says who the shirt is for (vypravecske, organizatorske)
// while the name says only what colour it is, and under utf8mb4_czech_ci a name search folds é→e but not č→c.
// stav 2 (restricted) is kept in the condition so that a public shirt never becomes permission-locked.
foreach (['tricko_vypravecske' => 'vypravecske', 'tricko_organizatorske' => 'organizatorske'] as $tagCode => $audienceInCode) {
    $this->q(<<<SQL
INSERT INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu,
       product_tag.id
FROM shop_predmety
    INNER JOIN product_tag ON product_tag.code = '{$tagCode}'
WHERE shop_predmety.stav = 2
  AND shop_predmety.kod_predmetu LIKE '%{$audienceInCode}%'
SQL);
}

// A breakfast was recognised by a food product's name starting with "Snídaně"; the code is plain ASCII and
// says the same in every year's catalog.
$this->q(<<<'SQL'
INSERT INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu,
       breakfast_tag.id
FROM shop_predmety
    INNER JOIN product_product_tag AS food_link ON food_link.product_id = shop_predmety.id_predmetu
    INNER JOIN product_tag AS food_tag ON food_tag.id = food_link.tag_id AND food_tag.code = 'jidlo'
    INNER JOIN product_tag AS breakfast_tag ON breakfast_tag.code = 'snidane'
WHERE shop_predmety.kod_predmetu LIKE '%snidane%'
SQL);

// A breakfast the old name rule found must not drop out unnoticed: it would simply stop being cancelled.
$breakfastsWithoutTag = $this->q(<<<'SQL'
SELECT shop_predmety.kod_predmetu
FROM shop_predmety
    INNER JOIN product_product_tag AS food_link ON food_link.product_id = shop_predmety.id_predmetu
    INNER JOIN product_tag AS food_tag ON food_tag.id = food_link.tag_id AND food_tag.code = 'jidlo'
WHERE TRIM(shop_predmety.nazev) LIKE 'Snídaně%'
  AND NOT EXISTS (
      SELECT 1
      FROM product_product_tag AS breakfast_link
          INNER JOIN product_tag AS breakfast_tag ON breakfast_tag.id = breakfast_link.tag_id
      WHERE breakfast_link.product_id = shop_predmety.id_predmetu
        AND breakfast_tag.code = 'snidane'
  )
ORDER BY shop_predmety.kod_predmetu
SQL)->fetchAll(PDO::FETCH_COLUMN);
if ($breakfastsWithoutTag !== []) {
    throw new RuntimeException(
        'Food products named like a breakfast whose code does not say "snidane" would stop being breakfasts: '
        . implode(', ', $breakfastsWithoutTag)
        . '. Give each the "snidane" tag in product_product_tag, then run the migration again.',
    );
}

// Past years are archived at the end of their year instead of carrying a model year.
$this->q(<<<'SQL'
UPDATE shop_predmety
SET archived_at = CONCAT(model_rok, '-12-31 23:59:59')
WHERE model_rok < (SELECT hodnota FROM systemove_nastaveni WHERE klic = 'ROCNIK' LIMIT 1)
SQL);

// The code identifies a product across years, so it has to be unique on its own. The name is only a label:
// the same shirt legitimately recurs every year. Older duplicates keep the newest row's code and get their id
// appended. The indexes that include model_rok go first, they would otherwise shrink to the column left.
foreach (['nazev', 'UNIQ_nazev_model_rok', 'kod_predmetu', 'UNIQ_kod_predmetu_model_rok'] as $oldIndex) {
    $indexExists = (bool) $this->q(<<<SQL
SELECT EXISTS (
    SELECT 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'shop_predmety'
      AND INDEX_NAME = '{$oldIndex}'
)
SQL)->fetchColumn();
    if ($indexExists) {
        $this->q("DROP INDEX `{$oldIndex}` ON shop_predmety");
    }
}

$this->q(<<<'SQL'
UPDATE shop_predmety AS duplicated
    INNER JOIN (
        SELECT kod_predmetu,
               MAX(id_predmetu) AS keep_id
        FROM shop_predmety
        GROUP BY kod_predmetu
        HAVING COUNT(*) > 1
    ) AS keeper ON duplicated.kod_predmetu = keeper.kod_predmetu AND duplicated.id_predmetu <> keeper.keep_id
SET duplicated.kod_predmetu = CONCAT(duplicated.kod_predmetu, '_', duplicated.id_predmetu)
SQL);

$this->q('CREATE UNIQUE INDEX UNIQ_kod_predmetu ON shop_predmety (kod_predmetu)');
