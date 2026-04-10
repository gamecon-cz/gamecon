-- Add reserved_for_organizers on product (default for variants), replace amount_organizers/amount_participants

ALTER TABLE shop_predmety
    ADD reserved_for_organizers INT DEFAULT NULL COMMENT 'Default reserved-for-organizers for variants. NULL = no reservation.';

-- Migrate amount_organizers to reserved_for_organizers
UPDATE shop_predmety
SET reserved_for_organizers = amount_organizers
WHERE amount_organizers IS NOT NULL;

-- Drop old columns
ALTER TABLE shop_predmety
    DROP COLUMN amount_organizers,
    DROP COLUMN amount_participants;

-- Recreate backward-compatible view (uses shop_predmety.* which changes when columns are dropped/added)
CREATE OR REPLACE VIEW shop_predmety_s_typem AS
SELECT
    shop_predmety.*,
    (SELECT CASE product_tag.code
        WHEN 'predmet' THEN 1
        WHEN 'ubytovani' THEN 2
        WHEN 'tricko' THEN 3
        WHEN 'jidlo' THEN 4
        WHEN 'vstupne' THEN 5
        WHEN 'parcon' THEN 6
        WHEN 'proplaceni-bonusu' THEN 7
    END
    FROM product_product_tag
    JOIN product_tag ON product_product_tag.tag_id = product_tag.id
    WHERE product_product_tag.product_id = shop_predmety.id_predmetu
      AND product_tag.code IN ('predmet','ubytovani','tricko','jidlo','vstupne','parcon','proplaceni-bonusu')
    LIMIT 1) AS typ,
    (SELECT product_tag.code
    FROM product_product_tag
    JOIN product_tag ON product_product_tag.tag_id = product_tag.id
    WHERE product_product_tag.product_id = shop_predmety.id_predmetu
      AND product_tag.code = 'hotel'
    LIMIT 1) AS podtyp,
    CASE WHEN shop_predmety.archived_at IS NULL
         THEN (SELECT CAST(hodnota AS UNSIGNED) FROM systemove_nastaveni WHERE klic = 'ROCNIK' LIMIT 1)
         ELSE YEAR(shop_predmety.archived_at)
    END AS model_rok,
    CASE WHEN shop_predmety.archived_at IS NULL THEN 1 ELSE 0 END AS je_letosni_hlavni
FROM shop_predmety;
