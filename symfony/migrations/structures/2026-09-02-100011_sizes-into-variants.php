<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Legacy has no concept of a size: each size of a shirt, hoodie or pair of socks is its
// own product row. The new model expresses that as one product with a variant per size,
// which is what lets the storefront render a size picker instead of a list of products.
//
// The size lives in kod_predmetu, not in nazev — only 132 of 486 shirt names end in a
// size, while 477 codes carry one. Grouping therefore keys on the code, and includes the
// year: without it every season collapses together (one "product" with 82 sizes).
//
// Two conventions exist. Garments end in the size (`..._XXL_2026`, sometimes with a
// trailing id that disambiguates duplicate codes); socks spell out a range
// (`ponozky_2021_vel_38_39`). A regex written for one silently skips the other.
//
// Products with no size at all keep their single default variant: the sizeless
// `tricko_tilko_*` rows are the same shirt sold internally during the festival, and
// `tricko_stare*` are leftovers. Neither is a size.
//
// Accommodation is deliberately left alone. It is variant-like too, but along three
// dimensions at once (day, room capacity, lodging type), which needs a general product
// options mechanism that does not exist yet.

$sizeSuffix = '_(XXXL|XXL|XL|XS|L|M|S)(_[0-9]{4})?(_[0-9]+)?$';
$sockSuffix = '_vel_[0-9]+_[0-9]+(_[0-9]+)?$';

// A group is one product-year: the code with its size stripped, plus the archived year
// (NULL = current). Both are needed — the same shirt recurs every season.
$groupKey = <<<SQL
CASE
    WHEN shop_predmety.kod_predmetu REGEXP '{$sockSuffix}'
        THEN CONCAT(REGEXP_REPLACE(shop_predmety.kod_predmetu, '{$sockSuffix}', ''), '|', COALESCE(YEAR(shop_predmety.archived_at), 'current'))
    ELSE CONCAT(REGEXP_REPLACE(shop_predmety.kod_predmetu, '{$sizeSuffix}', ''), '|', COALESCE(YEAR(shop_predmety.archived_at), 'current'))
END
SQL;

// The size as it should read to a customer: "XXL", or "38-39" for socks.
$sizeLabel = <<<SQL
CASE
    WHEN shop_predmety.kod_predmetu REGEXP '{$sockSuffix}'
        THEN REPLACE(REGEXP_SUBSTR(shop_predmety.kod_predmetu, '(?<=_vel_)[0-9]+_[0-9]+'), '_', '-')
    ELSE UPPER(REGEXP_SUBSTR(shop_predmety.kod_predmetu, '(XXXL|XXL|XL|XS|L|M|S)(?=(_[0-9]{4})?(_[0-9]+)?\$)'))
END
SQL;

// Sort order inside the picker; socks fall through to 0, they have only two values and
// their codes already sort correctly.
$sizeOrder = <<<SQL
CASE UPPER(REGEXP_SUBSTR(shop_predmety.kod_predmetu, '(XXXL|XXL|XL|XS|L|M|S)(?=(_[0-9]{4})?(_[0-9]+)?\$)'))
    WHEN 'XS' THEN 1
    WHEN 'S' THEN 2
    WHEN 'M' THEN 3
    WHEN 'L' THEN 4
    WHEN 'XL' THEN 5
    WHEN 'XXL' THEN 6
    WHEN 'XXXL' THEN 7
    ELSE 0
END
SQL;

// Owner of each group: the lowest product id, so the choice is stable across reruns.
$this->q(<<<SQL
CREATE TEMPORARY TABLE tmp_size_groups (
    product_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    owner_id BIGINT UNSIGNED NOT NULL,
    size_label VARCHAR(255) NOT NULL,
    size_order SMALLINT NOT NULL,
    INDEX (owner_id)
) ENGINE=InnoDB
SQL);

$this->q(<<<SQL
INSERT INTO tmp_size_groups (product_id, owner_id, size_label, size_order)
SELECT shop_predmety.id_predmetu,
       owners.owner_id,
       {$sizeLabel},
       {$sizeOrder}
FROM shop_predmety
JOIN (
    SELECT {$groupKey} AS group_key, MIN(shop_predmety.id_predmetu) AS owner_id
    FROM shop_predmety
    WHERE shop_predmety.kod_predmetu REGEXP '{$sizeSuffix}'
       OR shop_predmety.kod_predmetu REGEXP '{$sockSuffix}'
    GROUP BY group_key
) AS owners ON owners.group_key = {$groupKey}
WHERE shop_predmety.kod_predmetu REGEXP '{$sizeSuffix}'
   OR shop_predmety.kod_predmetu REGEXP '{$sockSuffix}'
SQL);

// Move every variant onto its group's owner and rename it to the bare size. The variant
// ids do not change, so the 4 773 shop_nakupy rows pointing at them still resolve — only
// the parent product changes.
$this->q(<<<SQL
UPDATE product_variant
JOIN tmp_size_groups ON tmp_size_groups.product_id = product_variant.product_id
SET product_variant.product_id = tmp_size_groups.owner_id,
    product_variant.name       = tmp_size_groups.size_label,
    product_variant.position   = tmp_size_groups.size_order
SQL);

// The owner's own name still carries the size ("Tričko červené L"); strip it, so the
// product reads "Tričko červené" and the variant supplies the size. Names are no longer
// unique, which is what makes this possible — the same shirt exists every year.
$this->q(<<<SQL
UPDATE shop_predmety
JOIN tmp_size_groups ON tmp_size_groups.product_id = shop_predmety.id_predmetu
    AND tmp_size_groups.owner_id = shop_predmety.id_predmetu
SET shop_predmety.nazev = TRIM(REGEXP_REPLACE(
        REGEXP_REPLACE(shop_predmety.nazev, '\\\\s*\\\\([^)]*\\\\)\\\\s*\$', ''),
        '\\\\s+(XXXL|XXL|XL|XS|L|M|S)\\\\s*\$', ''
    ))
WHERE shop_predmety.nazev REGEXP '\\\\s(XXXL|XXL|XL|XS|L|M|S)\$'
   OR shop_predmety.nazev REGEXP '\\\\([^)]*\\\\)\$'
SQL);

// The snapshot on historical purchases should read the same way the product does now.
$this->q(<<<SQL
UPDATE shop_nakupy
JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
JOIN shop_predmety ON shop_predmety.id_predmetu = product_variant.product_id
SET shop_nakupy.product_name = shop_predmety.nazev,
    shop_nakupy.variant_name = product_variant.name
WHERE shop_nakupy.variant_id IS NOT NULL
SQL);

$this->q('DROP TEMPORARY TABLE IF EXISTS tmp_size_groups');
