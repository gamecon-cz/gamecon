-- Add 'mikina' product tag and update shop_predmety_s_typem view so that
-- products tagged 'mikina' expose podtyp='mikina' (mirroring the existing
-- breakfast_included → podtyp='hotel' translation).
--
-- Why: the cherry-picked legacy code in Shop.php and Polozka.php expects
-- podtyp='mikina' to distinguish hoodies from other PREDMET items
-- (e.g. for the prodejMikinDo() deadline). Without this tag + view change,
-- there is no way for the view to report a product as a hoodie.

INSERT INTO product_tag (code, name, description, created_at)
VALUES ('mikina', 'Mikina', NULL, NOW());

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
FROM shop_predmety;
