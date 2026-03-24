-- Migration: Group historical OrderItems into Orders
-- Date: 2026-02-03
-- Purpose: Create Orders from existing OrderItems and link them together
-- Note: shop_order table and shop_nakupy columns already exist from previous eshop migrations

-- This migration assumes:
-- - shop_order table exists (created by Doctrine schema)
-- - shop_nakupy.order_id column exists
-- - shop_nakupy product snapshot columns exist
-- - Most order_id values are NULL (not yet grouped into orders)

-- Step 1: Check current state
-- SELECT COUNT(*) as items_without_order FROM shop_nakupy WHERE order_id IS NULL;
-- SELECT COUNT(*) as existing_orders FROM shop_order;

-- Step 2: Group historical OrderItems into Orders
-- Strategy: Create one Order per (customer, year, purchase_day) combination
-- This groups items purchased on the same day by the same customer for the same year

-- Create Orders from existing ungrouped purchases
INSERT INTO `shop_order` (`customer_id`, `year`, `status`, `total_price`, `created_at`, `completed_at`)
SELECT
  `id_uzivatele` AS customer_id,
  `rok` AS year,
  'completed' AS status,
  SUM(`cena_nakupni`) AS total_price,
  MIN(`datum`) AS created_at,  -- Use earliest purchase time as created_at
  MIN(`datum`) AS completed_at  -- Mark as completed immediately (historical data)
FROM `shop_nakupy`
WHERE `order_id` IS NULL  -- Only group items not yet in an order
GROUP BY `id_uzivatele`, `rok`, DATE(`datum`)
ORDER BY `id_uzivatele`, `rok`, DATE(`datum`);

-- Step 3: Link OrderItems to their corresponding Orders
-- Match based on (customer, year, purchase_day)
UPDATE `shop_nakupy`
INNER JOIN `shop_order` ON
  shop_nakupy.`id_uzivatele` = shop_order.`customer_id`
  AND shop_nakupy.`rok` = shop_order.`year`
  AND DATE(shop_nakupy.`datum`) = DATE(shop_order.`created_at`)
SET shop_nakupy.`order_id` = shop_order.`id`
WHERE shop_nakupy.`order_id` IS NULL;  -- Only update items not yet linked

-- Step 4: Populate product snapshots from current product data (if not already populated)
-- This preserves product information even if products are later deleted
UPDATE `shop_nakupy`
INNER JOIN `shop_predmety` ON shop_nakupy.`id_predmetu` = shop_predmety.`id_predmetu`
SET
  shop_nakupy.`product_name` = COALESCE(shop_nakupy.`product_name`, shop_predmety.`nazev`),
  shop_nakupy.`product_code` = COALESCE(shop_nakupy.`product_code`, shop_predmety.`kod_predmetu`),
  shop_nakupy.`product_description` = COALESCE(shop_nakupy.`product_description`, shop_predmety.`popis`)
WHERE shop_nakupy.`product_name` IS NULL OR shop_nakupy.`product_code` IS NULL;

-- Step 5: Verification queries (commented out - uncomment to verify)
-- Check orders created:
-- SELECT COUNT(*) AS total_orders,
--        SUM(total_price) AS total_revenue,
--        MIN(created_at) AS first_order,
--        MAX(created_at) AS last_order
-- FROM shop_order;

-- Check linkage:
-- SELECT
--   COUNT(*) AS total_items,
--   SUM(CASE WHEN order_id IS NOT NULL THEN 1 ELSE 0 END) AS items_with_order,
--   SUM(CASE WHEN order_id IS NULL THEN 1 ELSE 0 END) AS items_without_order,
--   ROUND(SUM(CASE WHEN order_id IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS percentage_linked
-- FROM shop_nakupy;

-- Top customers by order count:
-- SELECT
--   uzivatele_hodnoty.login_uzivatele,
--   shop_order.customer_id,
--   shop_order.year,
--   COUNT(*) AS order_count,
--   SUM(shop_order.total_price) AS total_spent
-- FROM shop_order
-- JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = shop_order.customer_id
-- GROUP BY shop_order.customer_id, shop_order.year, uzivatele_hodnoty.login_uzivatele
-- ORDER BY total_spent DESC
-- LIMIT 10;
