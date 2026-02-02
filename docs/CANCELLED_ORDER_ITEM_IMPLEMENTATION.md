# CancelledOrderItem Entity Implementation

## Overview
Successfully implemented the `CancelledOrderItem` Doctrine entity to map the `shop_nakupy_zrusene` table, which stores archived cancelled order items for audit and history purposes.

## Files Created

### 1. Main Entity
**File**: `symfony/src/Entity/CancelledOrderItem.php`
- Maps to `shop_nakupy_zrusene` table
- Includes all required fields: id, customer, product, year, purchasePrice, purchasedAt, cancelledAt, cancellationReason
- Relationships:
  - ManyToOne with User (customer) - CASCADE on delete
  - ManyToOne with Product (product) - no cascade
- Helper method: `getDisplayProductName()` - returns product name or "Smazaný produkt" if deleted
- Constructor auto-initializes `cancelledAt` timestamp

### 2. Repository
**File**: `symfony/src/Repository/CancelledOrderItemRepository.php`
- `findByCustomerAndYear()` - Get all cancelled items for customer/year
- `findByCancellationReason()` - Get cancelled items by reason (for legacy compatibility)
- `countByProductAndYear()` - Count cancelled items for a product
- `getTotalCancelledRevenueByYear()` - Calculate total revenue lost to cancellations

### 3. Structure Classes (Legacy Compatibility)
**File**: `symfony/src/Structure/Entity/CancelledOrderItemEntityStructure.php`
- Maps legacy camelCase property names to entity properties
- Example: `idNakupu` → `id`, `cenaNakupni` → `purchasePrice`

**File**: `symfony/src/Structure/Sql/CancelledOrderItemSqlStructure.php`
- Maps entity properties to SQL column names
- Example: `id_nakupu`, `cena_nakupni`, `datum_zruseni`

### 4. Entity Relationships Updated
**File**: `symfony/src/Entity/Product.php`
- Added `cancelledOrderItems` collection with OneToMany relationship

**File**: `symfony/src/Entity/User.php`
- Added `cancelledOrderItems` collection with OneToMany relationship (cascade remove)

### 5. Tests
**File**: `symfony/tests/Entity/CancelledOrderItemTest.php`
- 7 test methods covering:
  - Constructor initialization
  - Setters and getters
  - Customer relationship
  - Product relationship
  - Display name with and without product
  - Nullable cancellation reason

## Verification Results

### Doctrine Mapping
```bash
bin/console doctrine:mapping:info | grep CancelledOrderItem
# Result: [OK]   App\Entity\CancelledOrderItem
```

### Test Results
```bash
vendor/bin/phpunit --no-configuration symfony/tests/Entity/CancelledOrderItemTest.php
# Result: OK (7 tests, 14 assertions)
```

All tests pass successfully.

## Database Schema

The entity correctly maps to the existing `shop_nakupy_zrusene` table structure:

```sql
CREATE TABLE shop_nakupy_zrusene (
    id_nakupu     BIGINT UNSIGNED NOT NULL,
    id_uzivatele  BIGINT UNSIGNED NOT NULL,
    id_predmetu   BIGINT UNSIGNED NOT NULL,
    rocnik        SMALLINT NOT NULL,
    cena_nakupni  DECIMAL(6,2) NOT NULL,
    datum_nakupu  TIMESTAMP NOT NULL,
    datum_zruseni TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    zdroj_zruseni VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id_nakupu),
    KEY (id_uzivatele),
    KEY (id_predmetu),
    KEY (datum_zruseni),
    KEY (zdroj_zruseni),
    FOREIGN KEY (id_predmetu) REFERENCES shop_predmety (id_predmetu),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);
```

## Key Design Decisions

### 1. Simpler than OrderItem
Unlike `OrderItem`, `CancelledOrderItem` does NOT include:
- Product snapshots (product_name, product_code, product_description)
- Discount tracking (original_price, discount_amount, discount_reason)
- Orderer field (id_objednatele)
- Order relation (order_id)

This reflects the table's purpose as a simple audit log of cancellations.

### 2. Relationship Cascading
- **User → CancelledOrderItem**: CASCADE on delete (when user is deleted, their cancelled items are deleted)
- **Product → CancelledOrderItem**: NO ACTION (preserve cancelled item records even if product is deleted)

This matches the legacy foreign key constraints in the database.

### 3. ID Generation
The entity does NOT use auto-increment for the ID. The ID comes from the original order item ID and is set manually before persisting.

### 4. Read-Only from Application Perspective
This entity is primarily for reading archived data. Write operations are handled by legacy code in `model/Shop/Shop.php`.

## Future Migration Path

When migrating cancellation logic to Doctrine:
1. Use the repository methods for querying cancelled items
2. Create new service class for cancellation logic
3. Migrate INSERT operations from `Shop.php` to use Doctrine entity
4. Update calls to `dejNazvyZrusenychNakupu()` to use `findByCancellationReason()`

## Notes

- The entity is recognized by Doctrine and schema validation shows no issues
- Test database bootstrap has pre-existing issues with `shop_predmety.typ` column (removed in new e-shop design)
- This is unrelated to the CancelledOrderItem implementation
- All entity unit tests pass when run without full database bootstrap
- Migration file `2026-02-01-convert-utf8mb3-to-utf8mb4.sql` updated to skip charset conversion for this table (uses database default)
