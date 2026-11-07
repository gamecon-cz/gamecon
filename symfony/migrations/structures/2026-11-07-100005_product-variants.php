<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Legacy has one catalog row for everything that can be bought: each size of a shirt, each night of a room.
// The new model has a product (the shirt, the room type) and a variant per size or night, which is what lets
// the storefront render a size picker. Every row first becomes a variant of itself, then sizes and nights are
// regrouped under one product; the rows they leave are removed by legacy-catalog-removed.

// A variant of a product that is not split has no name of its own: what is shown is the product, plus the
// variant only when it is a size or a night. Price and reserved_for_organizers stay NULL, i.e. inherited.
$this->q(<<<'SQL'
INSERT INTO product_variant (product_id, name, code, price, reserved_for_organizers, accommodation_day, position, capacity, state)
SELECT shop_predmety.id_predmetu,
       NULL,
       shop_predmety.kod_predmetu,
       NULL,
       NULL,
       shop_predmety.ubytovani_den,
       0,
       shop_predmety.kusu_vyrobeno,
       shop_predmety.stav
FROM shop_predmety
ORDER BY shop_predmety.id_predmetu
SQL);

// ── Sizes ────────────────────────────────────────────────────────────────────────────────────────────────────
//
// The size lives in kod_predmetu, not in nazev (the name carries it far less often). Grouping keys on the code
// and includes the year, otherwise every season collapses together. Garments end in the size (`..._XXL_2026`,
// sometimes with a trailing id), socks spell out a range (`ponozky_2021_vel_38_39`); a regex written for one
// silently skips the other. Products with no size keep their single variant.

$sizeSuffix = '_(XXXL|XXL|XL|XS|L|M|S)(_[0-9]{4})?(_[0-9]+)?$';
$sockSuffix = '_vel_[0-9]+_[0-9]+(_[0-9]+)?$';

$groupKey = <<<SQL
CASE
    WHEN shop_predmety.kod_predmetu REGEXP '{$sockSuffix}'
        THEN CONCAT(REGEXP_REPLACE(shop_predmety.kod_predmetu, '{$sockSuffix}', ''), '|', COALESCE(YEAR(shop_predmety.archived_at), 'current'))
    ELSE CONCAT(REGEXP_REPLACE(shop_predmety.kod_predmetu, '{$sizeSuffix}', ''), '|', COALESCE(YEAR(shop_predmety.archived_at), 'current'))
END
SQL;

$sizeLabel = <<<SQL
CASE
    WHEN shop_predmety.kod_predmetu REGEXP '{$sockSuffix}'
        THEN REPLACE(REGEXP_SUBSTR(shop_predmety.kod_predmetu, '(?<=_vel_)[0-9]+_[0-9]+'), '_', '-')
    ELSE UPPER(REGEXP_SUBSTR(shop_predmety.kod_predmetu, '(XXXL|XXL|XL|XS|L|M|S)(?=(_[0-9]{4})?(_[0-9]+)?\$)'))
END
SQL;

// Socks fall through to 0: they have only two values and their codes already sort correctly.
$sizeOrder = <<<'SQL'
CASE UPPER(REGEXP_SUBSTR(shop_predmety.kod_predmetu, '(XXXL|XXL|XL|XS|L|M|S)(?=(_[0-9]{4})?(_[0-9]+)?$)'))
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

// The owner of a group is its lowest product id, so the choice is stable.
$this->q('DROP TEMPORARY TABLE IF EXISTS tmp_size_groups');
$this->q(<<<'SQL'
CREATE TEMPORARY TABLE tmp_size_groups
(
    product_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    owner_id   BIGINT UNSIGNED NOT NULL,
    size_label VARCHAR(255)    NOT NULL,
    size_order SMALLINT        NOT NULL,
    INDEX (owner_id)
) ENGINE = InnoDB
SQL);

$this->q(<<<SQL
INSERT INTO tmp_size_groups (product_id, owner_id, size_label, size_order)
SELECT shop_predmety.id_predmetu,
       owners.owner_id,
       {$sizeLabel},
       {$sizeOrder}
FROM shop_predmety
    INNER JOIN (
        SELECT {$groupKey} AS group_key,
               MIN(shop_predmety.id_predmetu) AS owner_id
        FROM shop_predmety
        WHERE shop_predmety.kod_predmetu REGEXP '{$sizeSuffix}'
           OR shop_predmety.kod_predmetu REGEXP '{$sockSuffix}'
        GROUP BY group_key
    ) AS owners ON owners.group_key = {$groupKey}
WHERE shop_predmety.kod_predmetu REGEXP '{$sizeSuffix}'
   OR shop_predmety.kod_predmetu REGEXP '{$sockSuffix}'
SQL);

$this->q(<<<'SQL'
UPDATE product_variant
    INNER JOIN tmp_size_groups ON tmp_size_groups.product_id = product_variant.product_id
SET product_variant.product_id = tmp_size_groups.owner_id,
    product_variant.name       = tmp_size_groups.size_label,
    product_variant.position   = tmp_size_groups.size_order
SQL);

// The owner's own name still carries the size ("Tričko červené L"); the product reads "Tričko červené" and
// the variant supplies the size.
$this->q(<<<'SQL'
UPDATE shop_predmety
    INNER JOIN tmp_size_groups ON tmp_size_groups.product_id = shop_predmety.id_predmetu
        AND tmp_size_groups.owner_id = shop_predmety.id_predmetu
SET shop_predmety.nazev = TRIM(REGEXP_REPLACE(
        REGEXP_REPLACE(shop_predmety.nazev, '\\s*\\([^)]*\\)\\s*$', ''),
        '\\s+(XXXL|XXL|XL|XS|L|M|S)\\s*$', ''
    ))
WHERE shop_predmety.nazev REGEXP '\\s(XXXL|XXL|XL|XS|L|M|S)$'
   OR shop_predmety.nazev REGEXP '\\([^)]*\\)$'
SQL);

$this->q('DROP TEMPORARY TABLE tmp_size_groups');

// ── Accommodation ────────────────────────────────────────────────────────────────────────────────────────────
//
// One product per room type with a variant per night, keyed on kod_predmetu (`Hs-2L-ct` is type `Hs-2L`, day
// `ct`), never on the name; older seasons use an unstructured convention and keep their single variant.
// The type is a product of its own, not the row of one night (capacity and reservations belong to the nights);
// it is created from the room's lowest-id night, which carries the name, price and state.

// The day is the last code segment after "-" or "_"; both separators occur (`1L_st` vs `Hs-2L-ct`).
$dayOfCode = <<<'SQL'
SUBSTRING_INDEX(shop_predmety.kod_predmetu, IF(shop_predmety.kod_predmetu LIKE '%\_%', '_', '-'), -1)
SQL;

// The type is everything before that segment.
$typeOfCode = <<<SQL
LEFT(shop_predmety.kod_predmetu, CHAR_LENGTH(shop_predmety.kod_predmetu) - CHAR_LENGTH({$dayOfCode}) - 1)
SQL;

// Current accommodation only. A code with no separator has no day segment to strip, so LEFT() would yield ''
// and every such type would collapse into one group; skip those rather than merge them.
$isCurrentAccommodation = <<<SQL
shop_predmety.archived_at IS NULL
AND shop_predmety.kod_predmetu REGEXP '[-_]'
AND {$dayOfCode} IN ('st', 'ct', 'pa', 'so', 'ne')
AND EXISTS (
    SELECT 1
    FROM product_product_tag
        INNER JOIN product_tag ON product_tag.id = product_product_tag.tag_id
    WHERE product_product_tag.product_id = shop_predmety.id_predmetu
      AND product_tag.code = 'ubytovani'
)
SQL;

// The collation is pinned: a temporary column would take the database default, which is not the one of
// kod_predmetu and product_variant.code, and comparing the two is an error.
$this->q('DROP TEMPORARY TABLE IF EXISTS tmp_accommodation_nights');
$this->q(<<<'SQL'
CREATE TEMPORARY TABLE tmp_accommodation_nights
(
    night_code VARCHAR(255) COLLATE utf8mb4_czech_ci NOT NULL PRIMARY KEY,
    owner_id   BIGINT UNSIGNED                       NOT NULL,
    type_id    BIGINT UNSIGNED                       NULL,
    day        SMALLINT                              NOT NULL,
    INDEX (owner_id)
) ENGINE = InnoDB
SQL);

$this->q(<<<SQL
INSERT INTO tmp_accommodation_nights (night_code, owner_id, day)
SELECT shop_predmety.kod_predmetu,
       owners.owner_id,
       CASE {$dayOfCode}
           WHEN 'st' THEN 0
           WHEN 'ct' THEN 1
           WHEN 'pa' THEN 2
           WHEN 'so' THEN 3
           WHEN 'ne' THEN 4
       END
FROM shop_predmety
    INNER JOIN (
        SELECT {$typeOfCode} AS type_code,
               MIN(shop_predmety.id_predmetu) AS owner_id
        FROM shop_predmety
        WHERE {$isCurrentAccommodation}
        GROUP BY type_code
    ) AS owners ON owners.type_code = {$typeOfCode}
WHERE {$isCurrentAccommodation}
SQL);

$this->q(<<<'SQL'
INSERT INTO shop_predmety (nazev, kod_predmetu, cena_aktualni, stav, nabizet_do, kusu_vyrobeno, ubytovani_den, popis, vedlejsi, archived_at, breakfast_included, model_rok, typ, podtyp, je_letosni_hlavni)
SELECT TRIM(REGEXP_REPLACE(night.nazev, ' ?(pondělí|úterý|středa|čtvrtek|pátek|sobota|neděle)$', '')),
       CONCAT(night.kod_predmetu, '-typ'),
       night.cena_aktualni,
       night.stav,
       night.nabizet_do,
       NULL,
       NULL,
       night.popis,
       night.vedlejsi,
       night.archived_at,
       night.breakfast_included,
       night.model_rok,
       night.typ,
       night.podtyp,
       night.je_letosni_hlavni
FROM shop_predmety AS night
WHERE night.id_predmetu IN (SELECT owner_id FROM tmp_accommodation_nights)
ORDER BY night.id_predmetu
SQL);

$this->q(<<<'SQL'
UPDATE tmp_accommodation_nights
    INNER JOIN shop_predmety AS owner ON owner.id_predmetu = tmp_accommodation_nights.owner_id
    INNER JOIN shop_predmety AS room_type ON room_type.kod_predmetu = CONCAT(owner.kod_predmetu, '-typ')
SET tmp_accommodation_nights.type_id = room_type.id_predmetu
SQL);

// The tags decide what counts as accommodation, so the type needs the night's.
$this->q(<<<'SQL'
INSERT INTO product_product_tag (product_id, tag_id)
SELECT DISTINCT tmp_accommodation_nights.type_id,
                product_product_tag.tag_id
FROM tmp_accommodation_nights
    INNER JOIN product_product_tag ON product_product_tag.product_id = tmp_accommodation_nights.owner_id
SQL);

$this->q(<<<'SQL'
UPDATE product_variant
    INNER JOIN tmp_accommodation_nights ON tmp_accommodation_nights.night_code = product_variant.code
SET product_variant.product_id        = tmp_accommodation_nights.type_id,
    product_variant.name              = ELT(tmp_accommodation_nights.day + 1, 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle'),
    product_variant.accommodation_day = tmp_accommodation_nights.day,
    product_variant.position          = tmp_accommodation_nights.day
SQL);

$this->q('DROP TEMPORARY TABLE tmp_accommodation_nights');

// ── State ────────────────────────────────────────────────────────────────────────────────────────────────────
//
// A night or a size can be suspended on its own while the product above it stays on offer. A variant whose own
// row is archived apart from its product is a night or size taken off the offer; any other is offered as its row was.
$this->q(<<<'SQL'
UPDATE product_variant
    INNER JOIN shop_predmety AS own_row ON own_row.kod_predmetu = product_variant.code
    INNER JOIN shop_predmety AS product ON product.id_predmetu = product_variant.product_id
SET product_variant.state = IF(own_row.archived_at IS NOT NULL AND product.archived_at IS NULL, 0, own_row.stav)
SQL);
