<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A product's only variant has no name of its own; what is shown is the product, plus the
// variant only when it is a size or a night. Copying the product's name into it meant keeping
// the two in step on every rename and guarding every display against "Kostka Kostka".
$this->q(<<<'SQL'
ALTER TABLE product_variant
    MODIFY name VARCHAR(255) DEFAULT NULL
SQL);

$this->q(<<<'SQL'
UPDATE product_variant
    INNER JOIN shop_predmety AS produkt ON produkt.id_predmetu = product_variant.product_id
SET product_variant.name = NULL
WHERE product_variant.name = produkt.nazev
SQL);

// Purchases snapshot the same pair, so their history reads by the same rule.
$this->q(<<<'SQL'
UPDATE shop_nakupy
SET variant_name = NULL
WHERE variant_name = product_name
SQL);

$this->q(<<<'SQL'
CREATE OR REPLACE VIEW shop_varianty_s_typem AS
SELECT
    product_variant.id                                     AS id_varianty,
    produkt.id_predmetu                                    AS id_predmetu,
    CONCAT_WS(' ', produkt.nazev, product_variant.name)    AS nazev,
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
