<?php

declare(strict_types=1);

use App\Enum\ProductStateEnum;

/** @var Godric\DbMigrations\Migration $this */

// Blue and red t-shirts are orderable only with the matching permission, which legacy reads
// off the product name. A tag says it directly, so a rename cannot drop the restriction.

$this->q(<<<SQL
INSERT IGNORE INTO product_tag (code, name, created_at)
VALUES ('tricko-modre', 'Modré tričko', NOW()),
       ('tricko-cervene', 'Červené tričko', NOW())
SQL);

// Keyed on kod_predmetu, not on the name. The code says who the shirt is for
// (organizatorske / vypravecske) while the name says only what colour it is, and under
// utf8mb4_czech_ci a name search folds é→e but not č→c, so 'Tricko cervene' would silently
// miss. stav = RESTRICTED is kept as well: legacy gates only restricted shirts, and a public
// shirt must not become permission-locked.
$stavOmezeny = ProductStateEnum::RESTRICTED->value;

foreach ([
    'tricko-modre'   => 'vypravecske',
    'tricko-cervene' => 'organizatorske',
] as $kodTagu => $kodProKoho) {
    $this->q(<<<SQL
INSERT IGNORE INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu, product_tag.id
FROM shop_predmety
JOIN product_tag ON product_tag.code = '{$kodTagu}'
WHERE shop_predmety.stav = {$stavOmezeny}
  AND shop_predmety.kod_predmetu LIKE '%{$kodProKoho}%'
SQL);
}
