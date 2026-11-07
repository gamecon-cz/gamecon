<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A purchase used to point at the catalog row it bought. It now points at a variant, and its product is the
// variant's product; the row it came from is kept only until the legacy-catalog-removed migration.
// Everything that describes the sale (names, code, tags, price) is a snapshot taken here, from the catalog as
// it is right now: products can be renamed or removed later and the history must keep reading the same.

$year = ROCNIK;

// ── Orders ───────────────────────────────────────────────────────────────────────────────────────────────────
//
// History predates orders, so one order is made per customer, year and day of purchase. Both answers of the
// accommodation form belong to a year's registration, but legacy keeps them on the account with no year, so
// only the current year's orders get them; older orders get the neutral answer.
$this->q(<<<SQL
INSERT INTO shop_order (customer_id, year, status, total_price, created_at, completed_at, roommate, accommodation_declined)
SELECT shop_nakupy.id_uzivatele,
       shop_nakupy.rok,
       'completed',
       SUM(shop_nakupy.cena_nakupni),
       MIN(shop_nakupy.datum),
       MIN(shop_nakupy.datum),
       MAX(IF(shop_nakupy.rok = {$year}, NULLIF(TRIM(uzivatele_hodnoty.ubytovan_s), ''), NULL)),
       COALESCE(MAX(IF(shop_nakupy.rok = {$year}, uzivatele_hodnoty.nechce_ubytovani, 0)), 0)
FROM shop_nakupy
    INNER JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = shop_nakupy.id_uzivatele
GROUP BY shop_nakupy.id_uzivatele, shop_nakupy.rok, DATE(shop_nakupy.datum)
ORDER BY shop_nakupy.id_uzivatele, shop_nakupy.rok, DATE(shop_nakupy.datum)
SQL);

$this->q(<<<'SQL'
UPDATE shop_nakupy
    INNER JOIN shop_order ON shop_order.customer_id = shop_nakupy.id_uzivatele
        AND shop_order.year = shop_nakupy.rok
        AND DATE(shop_order.created_at) = DATE(shop_nakupy.datum)
SET shop_nakupy.order_id = shop_order.id
SQL);

// ── Purchases ────────────────────────────────────────────────────────────────────────────────────────────────
//
// The variant is the one made from the purchased row (same code), whether that row is now a size, a night or a
// product of its own. The tags are the category the product had when it was bought; those added since
// (hoodie, sleeping bag, ...) did not exist then.
$this->q(<<<'SQL'
UPDATE shop_nakupy
    INNER JOIN shop_predmety AS bought_row ON bought_row.id_predmetu = shop_nakupy.id_predmetu
    INNER JOIN product_variant ON product_variant.code = bought_row.kod_predmetu
    INNER JOIN shop_predmety AS product ON product.id_predmetu = product_variant.product_id
SET shop_nakupy.variant_id          = product_variant.id,
    shop_nakupy.variant_name        = product_variant.name,
    shop_nakupy.product_name        = product.nazev,
    shop_nakupy.product_code        = bought_row.kod_predmetu,
    shop_nakupy.product_tags        = JSON_ARRAY(CASE bought_row.typ
        WHEN 1 THEN 'predmet'
        WHEN 2 THEN 'ubytovani'
        WHEN 3 THEN 'tricko'
        WHEN 4 THEN 'jidlo'
        WHEN 5 THEN 'vstupne'
        WHEN 6 THEN 'parcon'
        WHEN 7 THEN 'proplaceni_bonusu'
    END),
    shop_nakupy.product_description = bought_row.popis,
    shop_nakupy.original_price      = shop_nakupy.cena_nakupni
SQL);

// ── Cancelled purchases ──────────────────────────────────────────────────────────────────────────────────────
//
// They never had a snapshot, though a report lists them across years and today's product is the only source of
// the name. The size comes from the variant, because grouping cut it off the owner's name; it is added back
// unless the row's own name already has it. The test on the variant name is explicit because a NULL name would
// otherwise travel through three comparisons before the IF treats it as false.
$this->q(<<<'SQL'
UPDATE shop_nakupy_zrusene
    INNER JOIN shop_predmety AS bought_row ON bought_row.id_predmetu = shop_nakupy_zrusene.id_predmetu
    INNER JOIN product_variant ON product_variant.code = bought_row.kod_predmetu
SET shop_nakupy_zrusene.variant_id   = product_variant.id,
    shop_nakupy_zrusene.product_code = bought_row.kod_predmetu,
    shop_nakupy_zrusene.product_name = IF(
        product_variant.name IS NOT NULL
            AND TRIM(product_variant.name) REGEXP '^(XS|S|M|L|XL|XXL|XXXL|[0-9]+-[0-9]+)$'
            AND bought_row.nazev NOT REGEXP CONCAT('[[:<:]]', TRIM(product_variant.name), '[[:>:]]'),
        CONCAT(bought_row.nazev, ' ', TRIM(product_variant.name)),
        bought_row.nazev
    )
SQL);

// A cell of the desk sale grid sold one catalog row; it now names the product and the size or night it sells.
// A deleted variant unpins the cell rather than breaking it: the sale then asks for the size.
$this->q(<<<'SQL'
UPDATE obchod_bunky
    INNER JOIN shop_predmety AS sold_row ON sold_row.id_predmetu = obchod_bunky.cil_id
    INNER JOIN product_variant ON product_variant.code = sold_row.kod_predmetu
SET obchod_bunky.variant_id = product_variant.id,
    obchod_bunky.cil_id     = product_variant.product_id
WHERE obchod_bunky.typ = 0
SQL);

// ── Every purchase has a variant ─────────────────────────────────────────────────────────────────────────────
//
// Deploy migrations do not run in strict mode: NOT NULL over a missing variant would turn it into 0, and the
// foreign key does not check existing rows. So a purchase without a variant has to stop the migration here.
foreach (['shop_nakupy', 'shop_nakupy_zrusene'] as $table) {
    $withoutVariant = (int) $this->q("SELECT COUNT(*) FROM {$table} WHERE variant_id IS NULL")->fetchColumn();
    if ($withoutVariant > 0) {
        throw new RuntimeException("{$withoutVariant} rows of {$table} have no variant. Their catalog row has no variant with the same code; add one and run the migration again.");
    }
}

$this->q(<<<'SQL'
ALTER TABLE shop_nakupy
    MODIFY variant_id BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT FK_nakupy_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE RESTRICT
SQL);

$this->q(<<<'SQL'
ALTER TABLE shop_nakupy_zrusene
    MODIFY variant_id BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT FK_nakupy_zrusene_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE RESTRICT
SQL);
