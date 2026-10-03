<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// What a purchase bought, one row per variant, for legacy readers moving off the purchase's
// id_predmetu. Product-level columns come from shop_predmety_s_typem, which owns the tag rules;
// a night or size dropped from the catalogue is RETIRED in stav (App\Service\VariantStateMirror).
$this->q(<<<'SQL'
CREATE OR REPLACE VIEW shop_varianty_s_typem AS
SELECT
    product_variant.id                                     AS id_varianty,
    produkt.id_predmetu                                    AS id_predmetu,
    IF(
        product_variant.name = produkt.nazev,
        produkt.nazev,
        CONCAT(produkt.nazev, ' ', product_variant.name)
    )                                                      AS nazev,
    product_variant.code                                   AS kod_predmetu,
    COALESCE(product_variant.price, produkt.cena_aktualni) AS cena_aktualni,
    product_variant.state                                  AS stav,
    produkt.nabizet_do                                     AS nabizet_do,
    product_variant.capacity                               AS kusu_vyrobeno,
    product_variant.accommodation_day                      AS ubytovani_den,
    produkt.popis                                          AS popis,
    produkt.vedlejsi                                       AS vedlejsi,
    produkt.archived_at                                    AS archived_at,
    produkt.breakfast_included                             AS breakfast_included,
    produkt.typ                                            AS typ,
    produkt.podtyp                                         AS podtyp,
    produkt.model_rok                                      AS model_rok
FROM product_variant
    INNER JOIN shop_predmety_s_typem AS produkt ON produkt.id_predmetu = product_variant.product_id
SQL);
