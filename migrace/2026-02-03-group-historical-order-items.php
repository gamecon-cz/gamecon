<?php

/** @var \Godric\DbMigrations\Migration $this */

$dbName = $this->q("SELECT DATABASE()")->fetchColumn();

// Check prerequisites: order_id column and shop_order table must exist (created by new-eshop migration)
$columnResult = $this->q(
    "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'shop_nakupy' AND COLUMN_NAME = 'order_id'",
);
if ((int) $columnResult->fetch(\PDO::FETCH_ASSOC)['cnt'] === 0) {
    throw new \RuntimeException('shop_nakupy.order_id column does not exist — new-eshop migration must run first');
}

$tableResult = $this->q(
    "SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'shop_order'",
);
if ((int) $tableResult->fetch(\PDO::FETCH_ASSOC)['cnt'] === 0) {
    throw new \RuntimeException('shop_order table does not exist — new-eshop migration must run first');
}

// Check if there are already orders (idempotency — don't re-create if already grouped)
$existingOrders = (int) $this->q("SELECT COUNT(*) FROM shop_order")->fetchColumn();
if ($existingOrders > 0) {
    return; // Already migrated
}

// Create Orders from existing ungrouped purchases
$this->q(<<<'SQL'
INSERT INTO `shop_order` (`customer_id`, `year`, `status`, `total_price`, `created_at`, `completed_at`)
SELECT
  `id_uzivatele` AS customer_id,
  `rok` AS year,
  'completed' AS status,
  SUM(`cena_nakupni`) AS total_price,
  MIN(`datum`) AS created_at,
  MIN(`datum`) AS completed_at
FROM `shop_nakupy`
WHERE `order_id` IS NULL
GROUP BY `id_uzivatele`, `rok`, DATE(`datum`)
ORDER BY `id_uzivatele`, `rok`, DATE(`datum`)
SQL);

// Link OrderItems to their corresponding Orders
$this->q(<<<'SQL'
UPDATE `shop_nakupy`
INNER JOIN `shop_order` ON
  shop_nakupy.`id_uzivatele` = shop_order.`customer_id`
  AND shop_nakupy.`rok` = shop_order.`year`
  AND DATE(shop_nakupy.`datum`) = DATE(shop_order.`created_at`)
SET shop_nakupy.`order_id` = shop_order.`id`
WHERE shop_nakupy.`order_id` IS NULL
SQL);

// Populate product snapshots from current product data (if not already populated)
$this->q(<<<'SQL'
UPDATE `shop_nakupy`
INNER JOIN `shop_predmety` ON shop_nakupy.`id_predmetu` = shop_predmety.`id_predmetu`
SET
  shop_nakupy.`product_name` = COALESCE(shop_nakupy.`product_name`, shop_predmety.`nazev`),
  shop_nakupy.`product_code` = COALESCE(shop_nakupy.`product_code`, shop_predmety.`kod_predmetu`),
  shop_nakupy.`product_description` = COALESCE(shop_nakupy.`product_description`, shop_predmety.`popis`)
WHERE shop_nakupy.`product_name` IS NULL OR shop_nakupy.`product_code` IS NULL
SQL);
