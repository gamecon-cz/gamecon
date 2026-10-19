<?php
require __DIR__ . '/sdilene-hlavicky.php';

use App\Enum\ProductTagCode;

$report = Report::zSql(<<<SQL
SELECT u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele,
       product_variant.product_id AS product_id,
       CONCAT_WS(' ', produkt.nazev, product_variant.name) AS nazev,
       -- an archived product belongs to the year it was archived, one still on offer to this year
       COALESCE(YEAR(produkt.archived_at), $0) AS model_rok
FROM uzivatele_hodnoty u
LEFT JOIN shop_nakupy n ON (n.id_uzivatele = u.id_uzivatele)
LEFT JOIN product_variant ON (product_variant.id = n.variant_id)
LEFT JOIN shop_predmety AS produkt ON (produkt.id_predmetu = product_variant.product_id)
LEFT JOIN product_product_tag ON (product_product_tag.product_id = produkt.id_predmetu)
LEFT JOIN product_tag AS kategorie ON (kategorie.id = product_product_tag.tag_id AND kategorie.code IN ($1, $2))
WHERE (n.rok = $0)
  AND (kategorie.code = $1 OR (kategorie.code = $2 AND CONCAT_WS(' ', produkt.nazev, product_variant.name) LIKE '%taška%'))
ORDER BY u.id_uzivatele, n.id_nakupu
SQL
    , [0 => ROCNIK, 1 => ProductTagCode::TRICKO->value, 2 => ProductTagCode::PREDMET->value],
);

$report->tFormat(get('format'));
