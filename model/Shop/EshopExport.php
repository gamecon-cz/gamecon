<?php

declare(strict_types=1);

namespace Gamecon\Shop;

use App\Enum\ProductTagCode;

/**
 * The catalog as the import reads it back: one row per variant, the product's own columns
 * repeated on each of its rows.
 */
class EshopExport
{
    public function __construct(
        private readonly int $rocnik,
    ) {
    }

    public function report(): \Report
    {
        // The flag the import reads back: 1 only for the item each rule names as this year's.
        $letosniKody = array_values(array_filter(array_column(
            (new LetosniPredmetyZdarma($this->rocnik))->stav(),
            'kod',
        )));

        return \Report::zSql(<<<SQL
SELECT
  produkt.nazev AS product_name,
  produkt.kod_predmetu AS product_code,
  product_variant.name AS variant_name,
  product_variant.code AS variant_code,
  produkt.archived_at AS archivovano,
  (SELECT product_tag.code
   FROM product_product_tag
   INNER JOIN product_tag ON product_tag.id = product_product_tag.tag_id
   WHERE product_product_tag.product_id = produkt.id_predmetu
     AND product_tag.code IN ($1)
   LIMIT 1) AS tag,
  produkt.cena_aktualni,
  produkt.stav,
  produkt.nabizet_do,
  produkt.popis,
  produkt.vedlejsi,
  produkt.breakfast_included AS snidane_v_cene,
  IF(produkt.archived_at IS NULL AND produkt.kod_predmetu IN ($0), 1, 0) AS je_letosni_hlavni,
  product_variant.price AS cena_varianty,
  product_variant.state AS stav_varianty,
  product_variant.capacity AS kusu_vyrobeno,
  product_variant.accommodation_day AS ubytovani_den
FROM product_variant
INNER JOIN shop_predmety AS produkt ON produkt.id_predmetu = product_variant.product_id
ORDER BY produkt.archived_at IS NOT NULL, produkt.archived_at DESC, produkt.id_predmetu DESC,
  product_variant.position, product_variant.id
SQL,
            [
                0 => $letosniKody,
                1 => array_map(static fn (ProductTagCode $tag): string => $tag->value, ProductTagCode::categories()),
            ],
        );
    }
}
