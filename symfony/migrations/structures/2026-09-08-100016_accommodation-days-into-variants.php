<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Groups one product per night into one product per room type with a variant per night.
// Keys on kod_predmetu (`Hs-2L-ct` = type `Hs-2L`, day `ct`), never on the name: older
// seasons use an unstructured convention and are left with their single default variant.
// Absorbed rows keep their day suffix, which Shop::bezDne still parses for the legacy form.

// The day is the last code segment after "-" or "_"; both separators occur
// (`1L_st` vs `Hs-2L-ct`).
$kodDne = <<<SQL
SUBSTRING_INDEX(shop_predmety.kod_predmetu, IF(shop_predmety.kod_predmetu LIKE '%\\_%', '_', '-'), -1)
SQL;

// The type is everything before that segment — the group key.
$kodTypu = <<<SQL
LEFT(shop_predmety.kod_predmetu, CHAR_LENGTH(shop_predmety.kod_predmetu) - CHAR_LENGTH({$kodDne}) - 1)
SQL;

// Current accommodation: tagged `ubytovani`, not archived. Both `typ` and `model_rok`
// are gone from this table — the tag replaced the first, archived_at the second.
$jeAktualniUbytovani = <<<SQL
shop_predmety.archived_at IS NULL
-- A code with no separator has no day segment to strip, so LEFT() would yield '' and
-- every such type would collapse into one group. Skip them rather than merge them.
AND shop_predmety.kod_predmetu REGEXP '[-_]'
AND EXISTS (
    SELECT 1
    FROM product_product_tag
    JOIN product_tag ON product_tag.id = product_product_tag.tag_id
    WHERE product_product_tag.product_id = shop_predmety.id_predmetu
      AND product_tag.code = 'ubytovani'
)
SQL;

$this->q(<<<SQL
CREATE TEMPORARY TABLE tmp_accommodation_groups (
    -- Collation pinned to match shop_predmety.kod_predmetu / product_variant.code, which
    -- are utf8mb4_czech_ci while the database default is utf8mb4_general_ci — the join
    -- below is an "illegal mix of collations" error without it.
    kod_varianty VARCHAR(255) COLLATE utf8mb4_czech_ci NOT NULL PRIMARY KEY,
    owner_id     BIGINT UNSIGNED NOT NULL,
    den          SMALLINT        NOT NULL,
    INDEX (owner_id)
) ENGINE=InnoDB
SQL);

// Keyed on the variant code, which never changes, and the day is read out of that code
// rather than out of the product row. Both matter for a rerun: after the first pass the
// variants sit on the owner, so joining on product_id would hand every variant the
// OWNER's day (Sunday, the lowest id) and flatten all five nights into one.
$this->q(<<<SQL
INSERT INTO tmp_accommodation_groups (kod_varianty, owner_id, den)
SELECT shop_predmety.kod_predmetu,
       owners.owner_id,
       CASE {$kodDne}
           WHEN 'st' THEN 0
           WHEN 'ct' THEN 1
           WHEN 'pa' THEN 2
           WHEN 'so' THEN 3
           WHEN 'ne' THEN 4
       END
FROM shop_predmety
JOIN (
    SELECT {$kodTypu} AS kod_typu, MIN(shop_predmety.id_predmetu) AS owner_id
    FROM shop_predmety
    WHERE {$jeAktualniUbytovani}
      -- The same day filter as the outer query. Without it a row whose last segment is
      -- not a day could win MIN(id) and become the owner, and would then never be
      -- renamed or given a day — it is filtered out of this table — while every real
      -- night of the type got reparented onto it.
      AND {$kodDne} IN ('st', 'ct', 'pa', 'so', 'ne')
    GROUP BY kod_typu
) AS owners ON owners.kod_typu = {$kodTypu}
WHERE {$jeAktualniUbytovani}
  AND {$kodDne} IN ('st', 'ct', 'pa', 'so', 'ne')
SQL);

// Move each night's variant onto its type's owner. Variant ids do not change, so the
// shop_nakupy rows pointing at them still resolve — only the parent product changes.
// The variant already carries this night's own remaining_quantity, set by 100010.
$this->q(<<<SQL
UPDATE product_variant
JOIN tmp_accommodation_groups ON tmp_accommodation_groups.kod_varianty = product_variant.code
SET product_variant.product_id        = tmp_accommodation_groups.owner_id,
    product_variant.name              = ELT(tmp_accommodation_groups.den + 1,
                                            'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle'),
    product_variant.accommodation_day = tmp_accommodation_groups.den,
    product_variant.position          = tmp_accommodation_groups.den
SQL);

// The owner keeps whichever day it happened to be (the lowest id is Sunday's row), so
// without this the product would read "Postel na 3L koleji neděle" while carrying all
// five nights. Only the 8 owners are renamed; the 32 absorbed rows keep their suffix,
// which is what the legacy form still reads. Shop::bezDne is idempotent, so a stripped
// name lands in the same type bucket there as a suffixed one.
$this->q(<<<SQL
UPDATE shop_predmety
JOIN tmp_accommodation_groups ON tmp_accommodation_groups.owner_id = shop_predmety.id_predmetu
    AND tmp_accommodation_groups.kod_varianty = shop_predmety.kod_predmetu
SET shop_predmety.nazev = TRIM(REGEXP_REPLACE(
        shop_predmety.nazev,
        ' ?(pondělí|úterý|středa|čtvrtek|pátek|sobota|neděle)\$',
        ''
    ))
SQL);

// The snapshot on existing purchases should read the way the product does now.
$this->q(<<<SQL
UPDATE shop_nakupy
JOIN product_variant ON product_variant.id = shop_nakupy.variant_id
JOIN shop_predmety ON shop_predmety.id_predmetu = product_variant.product_id
SET shop_nakupy.product_name = shop_predmety.nazev,
    shop_nakupy.variant_name = product_variant.name
WHERE shop_nakupy.variant_id IS NOT NULL
SQL);

$this->q('DROP TEMPORARY TABLE IF EXISTS tmp_accommodation_groups');
