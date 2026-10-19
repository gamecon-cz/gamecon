<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A variant's capacity moves onto the variant itself. It was `kusu_vyrobeno` of the catalog row
// sharing its code, or of its product when it had none; the view keeps exposing `kusu_vyrobeno`,
// now derived from the variant, so legacy SQL reading the view does not change. Its columns are
// listed in their old order: stored quick reports `SELECT *` from it into CSV.

$this->q(<<<SQL
ALTER TABLE product_variant
    ADD capacity INT NULL
SQL,
);

$this->q(<<<SQL
UPDATE product_variant
    INNER JOIN shop_predmety AS vlastni_radek ON vlastni_radek.kod_predmetu = product_variant.code
SET product_variant.capacity = vlastni_radek.kusu_vyrobeno
SQL,
);

$this->q(<<<SQL
UPDATE product_variant
    INNER JOIN shop_predmety AS vlastnik ON vlastnik.id_predmetu = product_variant.product_id
SET product_variant.capacity = vlastnik.kusu_vyrobeno
WHERE NOT EXISTS (
    SELECT 1 FROM shop_predmety AS vlastni_radek WHERE vlastni_radek.kod_predmetu = product_variant.code
)
SQL,
);

$this->q(<<<SQL
ALTER TABLE shop_predmety
    DROP COLUMN kusu_vyrobeno
SQL,
);

$this->q(<<<SQL
CREATE OR REPLACE VIEW shop_predmety_s_typem AS
SELECT
    shop_predmety.id_predmetu,
    shop_predmety.nazev,
    shop_predmety.kod_predmetu,
    shop_predmety.cena_aktualni,
    shop_predmety.stav,
    shop_predmety.nabizet_do,
    (
        SELECT product_variant.capacity
        FROM product_variant
        WHERE product_variant.code = shop_predmety.kod_predmetu
    ) AS kusu_vyrobeno,
    shop_predmety.ubytovani_den,
    shop_predmety.popis,
    shop_predmety.vedlejsi,
    shop_predmety.archived_at,
    shop_predmety.breakfast_included,
    shop_predmety.reserved_for_organizers,
    (SELECT CASE product_tag.code
        WHEN 'predmet' THEN 1
        WHEN 'ubytovani' THEN 2
        WHEN 'tricko' THEN 3
        WHEN 'jidlo' THEN 4
        WHEN 'vstupne' THEN 5
        WHEN 'parcon' THEN 6
        WHEN 'proplaceni_bonusu' THEN 7
    END
    FROM product_product_tag
    JOIN product_tag ON product_product_tag.tag_id = product_tag.id
    WHERE product_product_tag.product_id = shop_predmety.id_predmetu
      AND product_tag.code IN ('predmet','ubytovani','tricko','jidlo','vstupne','parcon','proplaceni_bonusu')
    LIMIT 1) AS typ,
    CASE
        WHEN shop_predmety.breakfast_included THEN _utf8mb4'hotel' COLLATE utf8mb4_czech_ci
        WHEN EXISTS (
            SELECT 1 FROM product_product_tag
            JOIN product_tag ON product_product_tag.tag_id = product_tag.id
            WHERE product_product_tag.product_id = shop_predmety.id_predmetu
              AND product_tag.code = 'mikina'
        ) THEN _utf8mb4'mikina' COLLATE utf8mb4_czech_ci
        ELSE NULL
    END AS podtyp,
    CASE WHEN shop_predmety.archived_at IS NULL
         THEN (SELECT CAST(hodnota AS UNSIGNED) FROM systemove_nastaveni WHERE klic = 'ROCNIK' LIMIT 1)
         ELSE YEAR(shop_predmety.archived_at)
    END AS model_rok,
    CASE WHEN shop_predmety.archived_at IS NULL THEN 1 ELSE 0 END AS je_letosni_hlavni
FROM shop_predmety
SQL,
);
