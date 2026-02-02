# New E-shop Implementation Documentation

## Overview

This document describes the new e-shop system implementation that removes `model_rok` multi-year versioning and introduces flexible tagging, bundles, and discounts.

**Date:** 2026-01-31
**Author:** Claude Code
**Status:** Implemented (needs testing & deployment)

---

## Table of Contents

1. [Key Changes](#key-changes)
2. [Entity Structure](#entity-structure)
3. [Database Schema](#database-schema)
4. [Migration Guide](#migration-guide)
5. [API Documentation](#api-documentation)
6. [Usage Examples](#usage-examples)
7. [Testing](#testing)
8. [Deployment Checklist](#deployment-checklist)

---

## Key Changes

### Removed

- ❌ `model_rok` - products no longer recreated each year
- ❌ `je_letosni_hlavni` - no multi-year versioning flag
- ❌ `typ` field (1-7) - replaced with flexible tags

### Added

- ✅ `archived_at` - soft-delete for products
- ✅ `amount_organizers` / `amount_participants` - separate capacities for accommodation
- ✅ `product_tag` table - flexible tagging system
- ✅ `product_bundle` - forced bundling (MUST requirement)
- ✅ `product_discount` - role-based discounts (COULD requirement)
- ✅ OrderItem snapshot - price freezing (SHOULD requirement)

### Philosophy

**Before:** Products recreated yearly → import XLSX every year → old products set to MIMO
**After:** Products exist permanently → update prices/names as needed → archive when done

---

## Entity Structure

### Product (`symfony/src/Entity/Product.php`)

**Main entity for e-shop items**

```php
class Product {
    private ?int $id;
    private string $name;
    private string $code;              // Unique
    private string $currentPrice;
    private int $state;                // 0=MIMO, 1=VEŘEJNÝ, 2=PODPULTOVÝ, 3=POZASTAVENÝ
    private ?\DateTimeInterface $availableUntil;
    private ?int $producedQuantity;
    private ?int $accommodationDay;
    private string $description;
    private ?\DateTimeInterface $archivedAt;     // NEW
    private ?int $amountOrganizers;            // NEW
    private ?int $amountParticipants;          // NEW

    // Relations
    private Collection $tags;          // ProductTag[]
    private Collection $bundles;       // ProductBundle[]
    private Collection $discounts;     // ProductDiscount[]
    private Collection $orderItems;    // OrderItem[]
}
```

**Key Methods:**
- `archive()` / `restore()` - soft-delete management
- `addTag($tag)` / `removeTag($tag)` / `hasTag($tag)` - tag management
- `isAccommodation()` - checks for 'ubytovani' tag
- `isAvailable()` - checks availability (not archived, state OK, not expired)
- `getTotalCapacity()` - total capacity (organizers + participants or producedQuantity)

### ProductTag (`symfony/src/Entity/ProductTag.php`)

**Flexible tagging system**

```php
class ProductTag {
    private ?int $id;
    private ?Product $product;
    private string $tag;               // lowercase, alphanumeric + hyphens
    private \DateTimeInterface $createdAt;
}
```

**Common Tags:**
- Type tags: `predmet`, `ubytovani`, `tricko`, `jidlo`, `vstupne`, `parcon`, `proplaceni-bonusu`
- Item tags: `kostka`, `placka`, `blok`, `ponozka`, `taska`, `snidane`, `obed`, `vecere`
- Special: `org-merch`, `modre`, `cervene`

### ProductBundle (`symfony/src/Entity/ProductBundle.php`)

**Forced bundling (MUST requirement)**

```php
class ProductBundle {
    private ?int $id;
    private string $name;
    private bool $forced;                   // Cannot buy items individually
    private array $applicableToRoles;       // JSON: ['ucastnik']
    private Collection $products;           // Product[]
}
```

**Use Case:** Weekend accommodation package
```php
$bundle = new ProductBundle();
$bundle->setName('Víkendový balíček ubytování');
$bundle->setForced(true);
$bundle->setApplicableToRoles(['ucastnik']); // Only for participants
$bundle->addProduct($thursday);
$bundle->addProduct($friday);
$bundle->addProduct($saturday);
```

### ProductDiscount (`symfony/src/Entity/ProductDiscount.php`)

**Role-based discounts (COULD requirement)**

```php
class ProductDiscount {
    private ?int $id;
    private ?Product $product;
    private string $role;              // organizator, vypravec, ucastnik, host
    private string $discountPercent;   // 0-100 (100 = free)
    private ?int $maxQuantity;         // null = unlimited
}
```

**Example:** Organizers get dice for free (max 1 per person)
```php
$discount = new ProductDiscount();
$discount->setProduct($dice);
$discount->setRole('organizator');
$discount->setDiscountPercent('100.00');
$discount->setMaxQuantity(1);
```

### OrderItem (`symfony/src/Entity/OrderItem.php`)

**Purchase record with snapshot (SHOULD requirement)**

```php
class OrderItem {
    private ?int $id;
    private ?User $customer;
    private ?User $orderer;
    private ?Order $order;                    // Optional grouping
    private ?Product $product;                // Nullable - can be deleted
    private int $year;

    // Snapshot fields (NEW)
    private ?string $productName;
    private ?string $productCode;
    private ?string $productDescription;
    private string $purchasePrice;            // Final price after discounts
    private ?string $originalPrice;           // Price before discounts
    private ?string $discountAmount;
    private ?string $discountReason;

    private \DateTimeInterface $purchasedAt;
}
```

**Snapshot ensures:**
- Product name/code preserved even if product deleted
- Price frozen at purchase time (not affected by later price changes)
- Discount information tracked for transparency

### Order (`symfony/src/Entity/Order.php`)

**Optional grouping of OrderItems**

```php
class Order {
    const STATUS_PENDING = 'pending';       // Cart
    const STATUS_COMPLETED = 'completed';   // Paid
    const STATUS_CANCELLED = 'cancelled';

    private ?int $id;
    private ?User $customer;
    private int $year;
    private string $status;
    private string $totalPrice;
    private Collection $items;              // OrderItem[]
}
```

---

## Database Schema

### Migration Files

Located in `migrace/2026-01-31-new-eshop-*.sql`:

1. `01-remove-model-rok.sql` - Drop old columns, add new ones
2. `02-create-product-tags.sql` - Create product_tag table
3. `03-create-product-bundles.sql` - Create bundle tables
4. `04-create-product-discounts.sql` - Create discounts table
5. `05-add-orderitem-snapshot.sql` - Add snapshot to shop_nakupy
6. `06-migrate-typ-to-tags.sql` - Migrate data from typ → tags
7. `07-create-orders-table.sql` - Optional Order table

**IMPORTANT:** See `migrace/2026-01-31-new-eshop-README.md` for detailed migration instructions!

### Key Schema Changes

**shop_predmety (Product):**
```sql
-- Removed:
DROP COLUMN model_rok, je_letosni_hlavni, typ

-- Added:
ADD COLUMN archived_at DATETIME NULL
ADD COLUMN amount_organizers INT NULL
ADD COLUMN amount_participants INT NULL

-- Updated constraints:
DROP KEY UNIQ_nazev_model_rok, UNIQ_kod_predmetu_model_rok
ADD UNIQUE KEY UNIQ_kod_predmetu (kod_predmetu)
```

**shop_nakupy (OrderItem):**
```sql
-- Added snapshot:
ADD COLUMN product_name VARCHAR(255) NULL
ADD COLUMN product_code VARCHAR(255) NULL
ADD COLUMN product_description TEXT NULL
ADD COLUMN original_price DECIMAL(6,2) NULL
ADD COLUMN discount_amount DECIMAL(6,2) NULL
ADD COLUMN discount_reason VARCHAR(255) NULL

-- Made product_id nullable:
MODIFY COLUMN id_predmetu BIGINT UNSIGNED NULL
```

---

## Services

### ProductService

**Main business logic service**

```php
$productService = new ProductService(...);

// Create product
$product = $productService->createProduct(
    name: 'Kostka D20',
    code: 'KOSTKA-D20',
    price: '80.00',
    state: 1,
    tags: ['predmet', 'kostka']
);

// Get product with user-specific pricing
$priceInfo = $productService->getProductWithPrice($product, $user, 2025);
// Returns: ['product', 'originalPrice', 'finalPrice', 'discountAmount', 'discountReason']

// Check if user can purchase
$canPurchase = $productService->canPurchase($product, $user, 2025, 'ucastnik');

// Archive product
$productService->archiveProduct($product);
```

### DiscountCalculator

**Calculates role-based discounts**

```php
$discountCalculator = new DiscountCalculator(...);

$result = $discountCalculator->calculateDiscount($product, $user, 2025);
// Returns: ['discount', 'discountAmount', 'finalPrice', 'reason']

// Check remaining quota (for max_quantity limits)
$remaining = $discountCalculator->getRemainingQuota($product, $user, 2025);
```

### CapacityManager

**Manages product capacities**

```php
$capacityManager = new CapacityManager(...);

// Check availability
$available = $capacityManager->getAvailableCapacity($product, 2025, 'ucastnik');

// Get capacity info
$info = $capacityManager->getCapacityInfo($product, 2025);
// Returns: ['total', 'sold', 'available', 'percentSold']

// Validate before purchase
$capacityManager->validateCapacity($product, $user, 2025, $quantity);
// Throws exception if capacity exceeded
```

---

## Usage Examples

### Creating Products

```php
// Basic product
$product = new Product();
$product->setName('Kostka D20');
$product->setCode('KOSTKA-D20');
$product->setCurrentPrice('80.00');
$product->setState(1); // VEŘEJNÝ
$product->setProducedQuantity(100);
$product->addTag('predmet');
$product->addTag('kostka');

$em->persist($product);
$em->flush();
```

### Setting Up Discounts

```php
// Organizers get dice for free (max 1)
$discount = new ProductDiscount();
$discount->setProduct($dice);
$discount->setRole('organizator');
$discount->setDiscountPercent('100.00');
$discount->setMaxQuantity(1);

$em->persist($discount);
$em->flush();
```

### Creating Bundles

```php
// Weekend accommodation bundle (forced for participants)
$bundle = new ProductBundle();
$bundle->setName('Víkendový balíček');
$bundle->setForced(true);
$bundle->setApplicableToRoles(['ucastnik']);
$bundle->addProduct($thursdayAccommodation);
$bundle->addProduct($fridayAccommodation);
$bundle->addProduct($saturdayAccommodation);

$em->persist($bundle);
$em->flush();
```

### Purchasing (with snapshot)

```php
$orderItem = new OrderItem();
$orderItem->setCustomer($user);
$orderItem->setProduct($product);
$orderItem->setYear(2025);

// Create snapshot
$orderItem->snapshotProduct($product);

// Apply discount
$discountInfo = $discountCalculator->calculateDiscount($product, $user, 2025);
$orderItem->setOriginalPrice($product->getCurrentPrice());
$orderItem->setPurchasePrice($discountInfo['finalPrice']);
$orderItem->setDiscountAmount($discountInfo['discountAmount']);
$orderItem->setDiscountReason($discountInfo['reason']);

$em->persist($orderItem);
$em->flush();
```

---

## Testing

### Running Tests

```bash
# All tests
vendor/bin/phpunit

# Specific test
vendor/bin/phpunit symfony/tests/Entity/ProductTest.php

# With coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Test Files

- `symfony/tests/Entity/ProductTest.php` - Product entity tests
- `symfony/tests/Service/DiscountCalculatorTest.php` - Discount calculation
- `symfony/tests/Service/CapacityManagerTest.php` - Capacity management

---

## Deployment Checklist

### Pre-Deployment

- [ ] Backup production database
- [ ] Review migration files
- [ ] Test migrations on staging
- [ ] Verify tag migration (typ → tags)
- [ ] Check for duplicate kod_predmetu values
- [ ] Test discount calculations
- [ ] Test bundle validations

### Deployment

- [ ] Run migrations in order (see migration README)
- [ ] Verify schema changes
- [ ] Populate initial discounts
- [ ] Create initial bundles
- [ ] Test XLSX import (if keeping)
- [ ] Verify snapshot population

### Post-Deployment

- [ ] Check products display correctly
- [ ] Verify pricing with discounts
- [ ] Test cart functionality
- [ ] Check capacity limits
- [ ] Test bundle enforcement
- [ ] Monitor error logs

---

## Future Enhancements

### Not Yet Implemented

1. **API Platform integration** - REST API for products (Task #20, #21, #22)
2. **XLSX Import controller** - Symfony-based import (Task #21, #22)
3. **API tests** - Integration tests (Task #26, #27)
4. **Full role provider** - Extract getUserRoles() to service
5. **Accommodation capacity system** - Sophisticated multi-day tracking

### Recommended Next Steps

1. Implement UserRoleProvider service for discount calculations
2. Add validation for bundle purchases (prevent buying individual items)
3. Create admin UI for managing discounts/bundles
4. Add API endpoints for mobile app
5. Implement capacity reservation system for accommodation

---

## Support & Questions

For technical questions or issues:
1. Check migration README: `migrace/2026-01-31-new-eshop-README.md`
2. Review entity PHPDoc comments
3. Check test examples for usage patterns
4. Contact development team

---

**Last Updated:** 2026-01-31
**Version:** 1.0
**Requires:** PHP 8.2+, MariaDB 10.6+, Doctrine ORM
