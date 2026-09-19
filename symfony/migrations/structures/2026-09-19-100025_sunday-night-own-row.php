<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Every accommodation type owns one product with a variant per night, but the owner row is
// still a night itself: the grouping migration picked MIN(id_predmetu) per type and the ids
// happen to run Sunday first, so the type row carries ubytovani_den = 4, its own stock and
// its own purchases.
//
// One row therefore means two things, and the two readers disagree about which:
// CapacityManager inherits reserved_for_organizers from the parent product, so a reservation
// meant for Sunday silently applies to all five nights, while AccommodationWriter reads it
// from the night's own row via kod_predmetu and sees Sunday only.
//
// The split adds a new row for the TYPE and leaves the existing row as Sunday's night. That
// way the night keeps its id, its purchases and its `<typ>_<den>` code — the import still
// finds five nights per type by stripping the day suffix, which it would not if Sunday's
// code changed.

// A type whose owner is also its own Sunday night. All three conditions are load-bearing:
// accommodation_day alone is not an accommodation marker — every legacy product got a default
// variant carrying its ubytovani_den, and meals use that column for their own day — so without
// the tag and the archive filter this also fires on meals, sleeping bags and every past year.
// The variant count is what "is a type" actually means: a night owns exactly one variant.
$nedelniVlastnici = <<<SQL
SELECT id_predmetu FROM (
    SELECT shop_predmety.id_predmetu
    FROM shop_predmety
    JOIN product_variant ON product_variant.product_id = shop_predmety.id_predmetu
        AND product_variant.code = shop_predmety.kod_predmetu
        AND product_variant.accommodation_day = 4
    WHERE shop_predmety.archived_at IS NULL
      AND EXISTS (
          SELECT 1
          FROM product_product_tag
          JOIN product_tag ON product_tag.id = product_product_tag.tag_id
          WHERE product_product_tag.product_id = shop_predmety.id_predmetu
            AND product_tag.code = 'ubytovani'
      )
      AND (
          SELECT COUNT(*) FROM product_variant AS varianty_typu
          WHERE varianty_typu.product_id = shop_predmety.id_predmetu
      ) > 1
) AS nedelni_vlastnici
SQL;

$this->q(<<<SQL
CREATE TEMPORARY TABLE tmp_nedelni_noci (
    -- Collation pinned to shop_predmety.kod_predmetu (utf8mb4_czech_ci) while the database
    -- default is utf8mb4_general_ci; the join below is an illegal mix of collations without it.
    kod_noci VARCHAR(255) COLLATE utf8mb4_czech_ci NOT NULL PRIMARY KEY,
    id_noci  BIGINT UNSIGNED NOT NULL,
    id_typu  BIGINT UNSIGNED NULL,
    INDEX (id_noci)
) ENGINE=InnoDB
SQL);

$this->q(<<<SQL
INSERT INTO tmp_nedelni_noci (kod_noci, id_noci)
SELECT shop_predmety.kod_predmetu, shop_predmety.id_predmetu
FROM shop_predmety
WHERE shop_predmety.id_predmetu IN ({$nedelniVlastnici})
SQL);

// The type inherits what describes the room rather than the night: name, price, description.
// It gets no day and no stock, because capacity belongs to the five night rows.
$this->q(<<<SQL
INSERT INTO shop_predmety (
    nazev, kod_predmetu, cena_aktualni, stav, nabizet_do, kusu_vyrobeno, ubytovani_den,
    popis, vedlejsi, archived_at, breakfast_included, reserved_for_organizers
)
SELECT shop_predmety.nazev,
       CONCAT(shop_predmety.kod_predmetu, '-typ'),
       shop_predmety.cena_aktualni,
       shop_predmety.stav,
       shop_predmety.nabizet_do,
       NULL,
       NULL,
       shop_predmety.popis,
       shop_predmety.vedlejsi,
       shop_predmety.archived_at,
       shop_predmety.breakfast_included,
       shop_predmety.reserved_for_organizers
FROM shop_predmety
JOIN tmp_nedelni_noci ON tmp_nedelni_noci.id_noci = shop_predmety.id_predmetu
SQL);

$this->q(<<<SQL
UPDATE tmp_nedelni_noci
JOIN shop_predmety ON shop_predmety.kod_predmetu = CONCAT(tmp_nedelni_noci.kod_noci, '-typ')
SET tmp_nedelni_noci.id_typu = shop_predmety.id_predmetu
SQL);

// Tags decide what counts as accommodation, so the type needs the night's.
$this->q(<<<SQL
INSERT INTO product_product_tag (product_id, tag_id)
SELECT tmp_nedelni_noci.id_typu, product_product_tag.tag_id
FROM product_product_tag
JOIN tmp_nedelni_noci ON tmp_nedelni_noci.id_noci = product_product_tag.product_id
SQL);

// All five variants move onto the type. Sunday's was the odd one out: it pointed at a row
// that was simultaneously its own parent.
$this->q(<<<SQL
UPDATE product_variant
JOIN tmp_nedelni_noci ON tmp_nedelni_noci.id_noci = product_variant.product_id
SET product_variant.product_id = tmp_nedelni_noci.id_typu
SQL);

// Each night's reservation moves onto its own variant. CapacityManager reads the variant
// first and only falls back to the parent, which is now the type — without this the type's
// value would apply to all five nights, which is the bug this migration exists to fix.
$this->q(<<<SQL
UPDATE product_variant
JOIN shop_predmety AS noc ON noc.kod_predmetu = product_variant.code
JOIN tmp_nedelni_noci ON tmp_nedelni_noci.id_typu = product_variant.product_id
SET product_variant.reserved_for_organizers = noc.reserved_for_organizers
WHERE noc.reserved_for_organizers IS NOT NULL
SQL);

// The type is a grouping, never a thing to reserve; the nights carry that now.
$this->q(<<<SQL
UPDATE shop_predmety
JOIN tmp_nedelni_noci ON tmp_nedelni_noci.id_typu = shop_predmety.id_predmetu
SET shop_predmety.reserved_for_organizers = NULL
SQL);

// Sunday reads as an ordinary night like the other four. The day word is stripped first:
// the grouping migration removed it from this row, but a rerun of that one would put a name
// through here twice and "neděle neděle" is not a name.
$this->q(<<<SQL
UPDATE shop_predmety
JOIN tmp_nedelni_noci ON tmp_nedelni_noci.id_noci = shop_predmety.id_predmetu
SET shop_predmety.nazev = CONCAT(
        TRIM(REGEXP_REPLACE(
            shop_predmety.nazev,
            ' ?(pondělí|úterý|středa|čtvrtek|pátek|sobota|neděle)\$',
            ''
        )),
        ' neděle'
    )
SQL);

// The purchase snapshot names the type the way the product does now. Scoped to the nights
// this migration touched: a snapshot is the record of what was sold and must not be rewritten
// for products that were never in scope.
$this->q(<<<SQL
UPDATE shop_nakupy
JOIN tmp_nedelni_noci ON tmp_nedelni_noci.id_noci = shop_nakupy.id_predmetu
JOIN shop_predmety ON shop_predmety.id_predmetu = tmp_nedelni_noci.id_noci
SET shop_nakupy.product_name = shop_predmety.nazev
SQL);

$this->q('DROP TEMPORARY TABLE IF EXISTS tmp_nedelni_noci');
