# New E-shop Migration Guide

## Overview

This migration implements the new e-shop design that removes `model_rok` and introduces:
- Flexible tag system (replacing fixed `typ` field)
- Product bundles (forced bundling for accommodation)
- Role-based discounts (database-driven instead of hardcoded)
- OrderItem snapshots (price freezing - "zamrazení ceny")

## Prerequisites

**CRITICAL: Backup your database before running any migrations!**

```bash
# Backup database
mysqldump -u root -p gamecon > backup_before_eshop_migration_$(date +%Y%m%d_%H%M%S).sql
```

## Migration Order

**IMPORTANT: Migrations must be run in this exact order!**

### Phase 1: Preparation (Read-only checks)

```bash
# Check for duplicate kod_predmetu values
./bin-docker/mysql gamecon -e "
SELECT kod_predmetu, model_rok, COUNT(*) as cnt
FROM shop_predmety
GROUP BY kod_predmetu, model_rok
HAVING cnt > 1;
"

# Check for kod_predmetu that would conflict after removing model_rok
./bin-docker/mysql gamecon -e "
SELECT kod_predmetu, COUNT(DISTINCT model_rok) as year_count, GROUP_CONCAT(model_rok ORDER BY model_rok) as years
FROM shop_predmety
GROUP BY kod_predmetu
HAVING year_count > 1;
"
```

**Action Required:** If duplicates found, decide how to handle:
- **Option A:** Rename products to make unique (e.g., "Kostka-2024", "Kostka-2025")
- **Option B:** Keep only latest version, archive old ones
- **Option C:** Merge data manually

### Phase 2: Create New Tables (Safe - no data loss)

```bash
# 02: Create product_tag table
./bin-docker/mysql gamecon < migrace/2026-01-31-new-eshop-02-create-product-tags.sql

# 03: Create product_bundle tables
./bin-docker/mysql gamecon < migrace/2026-01-31-new-eshop-03-create-product-bundles.sql

# 04: Create product_discount table
./bin-docker/mysql gamecon < migrace/2026-01-31-new-eshop-04-create-product-discounts.sql

# 07: Create shop_order table (optional)
./bin-docker/mysql gamecon < migrace/2026-01-31-new-eshop-07-create-orders-table.sql
```

### Phase 3: Data Migration (Before dropping columns)

```bash
# 06: Migrate typ → tags (MUST run before dropping typ!)
./bin-docker/mysql gamecon < migrace/2026-01-31-new-eshop-06-migrate-typ-to-tags.sql

# Verify tag migration
./bin-docker/mysql gamecon -e "
SELECT p.nazev, p.typ, GROUP_CONCAT(pt.tag ORDER BY pt.tag SEPARATOR ', ') AS tags
FROM shop_predmety p
LEFT JOIN product_tag pt ON p.id_predmetu = pt.product_id
GROUP BY p.id_predmetu
LIMIT 20;
"

# 05: Add OrderItem snapshot fields
./bin-docker/mysql gamecon < migrace/2026-01-31-new-eshop-05-add-orderitem-snapshot.sql

# Verify snapshot population
./bin-docker/mysql gamecon -e "
SELECT id_nakupu, product_name, product_code, cena_nakupni, original_price
FROM shop_nakupy
LIMIT 10;
"
```

### Phase 4: Handle Duplicates

**If you have duplicate kod_predmetu values across different model_rok:**

```sql
-- Example: Rename products to include year
UPDATE shop_predmety
SET kod_predmetu = CONCAT(kod_predmetu, '-', model_rok)
WHERE kod_predmetu IN (
    SELECT kod_predmetu FROM (
        SELECT kod_predmetu
        FROM shop_predmety
        GROUP BY kod_predmetu
        HAVING COUNT(DISTINCT model_rok) > 1
    ) AS duplicates
);
```

**OR: Archive old versions**

```sql
-- Archive all products except latest version of each kod_predmetu
UPDATE shop_predmety p1
LEFT JOIN (
    SELECT kod_predmetu, MAX(model_rok) as latest_rok
    FROM shop_predmety
    GROUP BY kod_predmetu
) p2 ON p1.kod_predmetu = p2.kod_predmetu AND p1.model_rok = p2.latest_rok
SET p1.archived_at = NOW()
WHERE p2.kod_predmetu IS NULL;
```

### Phase 5: Drop Old Columns (DESTRUCTIVE!)

**CRITICAL: This step is irreversible! Make sure:**
1. Database is backed up
2. Tags are migrated (verify with query above)
3. Snapshots are populated
4. Duplicates are resolved

```bash
# 01: Remove model_rok, je_letosni_hlavni, typ
./bin-docker/mysql gamecon < migrace/2026-01-31-new-eshop-01-remove-model-rok.sql
```

## Post-Migration Verification

```bash
# Check table structures
./bin-docker/mysql gamecon -e "DESCRIBE shop_predmety;"
./bin-docker/mysql gamecon -e "DESCRIBE product_tag;"
./bin-docker/mysql gamecon -e "DESCRIBE shop_nakupy;"

# Check tag distribution
./bin-docker/mysql gamecon -e "
SELECT tag, COUNT(*) as product_count
FROM product_tag
GROUP BY tag
ORDER BY product_count DESC;
"

# Check products without tags
./bin-docker/mysql gamecon -e "
SELECT p.id_predmetu, p.nazev, p.kod_predmetu
FROM shop_predmety p
LEFT JOIN product_tag pt ON p.id_predmetu = pt.product_id
WHERE pt.id IS NULL
LIMIT 20;
"

# Check snapshot integrity
./bin-docker/mysql gamecon -e "
SELECT
    COUNT(*) as total_purchases,
    SUM(CASE WHEN product_name IS NOT NULL THEN 1 ELSE 0 END) as with_snapshot,
    SUM(CASE WHEN product_name IS NULL THEN 1 ELSE 0 END) as without_snapshot
FROM shop_nakupy;
"
```

## Rollback Plan

If something goes wrong:

```bash
# Restore from backup
mysql -u root -p gamecon < backup_before_eshop_migration_YYYYMMDD_HHMMSS.sql
```

## Next Steps

After migration:

1. **Test Symfony entities:**
   ```bash
   # Check if entities load correctly
   docker compose exec php bin/console doctrine:schema:validate
   ```

2. **Populate discounts** (if using ProductDiscount):
   ```sql
   -- Example: Organizers get dice for free
   INSERT INTO product_discount (product_id, role, discount_percent, max_quantity)
   SELECT id_predmetu, 'organizator', 100.00, 1
   FROM shop_predmety p
   JOIN product_tag pt ON p.id_predmetu = pt.product_id
   WHERE pt.tag = 'kostka';
   ```

3. **Create bundles** (if needed):
   ```sql
   -- Example: Weekend accommodation bundle
   INSERT INTO product_bundle (name, forced, applicable_to_roles)
   VALUES ('Víkendový balíček ubytování', 1, '["ucastnik"]');

   INSERT INTO product_bundle_items (bundle_id, product_id)
   SELECT 1, id_predmetu
   FROM shop_predmety p
   JOIN product_tag pt ON p.id_predmetu = pt.product_id
   WHERE pt.tag = 'ubytovani';
   ```

4. **Update application code** to use new entities:
   - Replace `ShopItem` with `Product`
   - Replace `ShopPurchase` with `OrderItem`
   - Use tag queries instead of `typ` checks

## Troubleshooting

### Issue: Duplicate key error on kod_predmetu

**Cause:** Multiple products with same kod_predmetu (different model_rok)

**Solution:** Run duplicate handling (Phase 4) before Phase 5

### Issue: Foreign key constraint fails

**Cause:** shop_nakupy references non-existent product

**Solution:** Clean up orphaned purchases first:
```sql
DELETE FROM shop_nakupy
WHERE id_predmetu NOT IN (SELECT id_predmetu FROM shop_predmety);
```

### Issue: Tags not migrated

**Cause:** Migration 06 not run before dropping typ

**Solution:** Restore from backup, run migrations in correct order

## Support

For issues or questions, contact the development team.
