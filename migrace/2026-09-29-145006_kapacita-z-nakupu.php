<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Remaining stock is now counted from shop_nakupy instead of stored on the variant.
// `rok` leads the index: the variant's foreign key keeps its own index, and one index serves
// both one variant's count and the whole year's GROUP BY.

$this->q(<<<SQL
CREATE INDEX IDX_nakupy_rok_variant ON shop_nakupy (rok, variant_id)
SQL,
);

$this->q(<<<SQL
ALTER TABLE product_variant
    DROP COLUMN remaining_quantity
SQL,
);
