# E-shop Implementation Summary

**Date:** 2026-01-31, revised 2026-09-02
**Implementation Status:** data model and APIs built; the customer-facing storefront is still the legacy one

> Scope note: "complete" below means the backend building blocks exist, not that the e-shop is
> rewritten. `web/moduly/prihlaska/prihlaska.php` still renders accommodation, merchandise,
> t-shirts, hoodies and entry fees through the legacy `Gamecon\Shop\Shop` class; only the meal
> matrix runs on the new stack (with the legacy HTML kept as a `<noscript>` fallback).
> See "What is still missing" at the end for the gap against `NEW_ESHOP.md`.

---

## What Was Implemented

### ✅ Completed (Core Requirements)

#### 1. Doctrine Entities (6 entities)
- ✅ **Product** - Main product entity without `model_rok`
- ✅ **ProductTag** - Flexible tagging system
- ✅ **ProductBundle** - Forced bundling (MUST requirement)
- ✅ **ProductDiscount** - Role-based discounts (COULD requirement)
- ✅ **OrderItem** - Purchase records with snapshot
- ✅ **Order** - Optional grouping of OrderItems

#### 2. Repositories (6 repositories)
- ✅ **ProductRepository** - Product queries (active, by tag, public, etc.)
- ✅ **ProductTagRepository** - Tag management and queries
- ✅ **ProductBundleRepository** - Bundle queries, forced bundle checks
- ✅ **ProductDiscountRepository** - Discount queries by product/role
- ✅ **OrderItemRepository** - Purchase history, statistics
- ✅ **OrderRepository** - Order management

#### 3. Services (3 core services)
- ✅ **ProductService** - Main business logic orchestration
- ✅ **DiscountCalculator** - Role-based discount calculations
- ✅ **CapacityManager** - Capacity tracking and validation

#### 4. Event Listeners (2 listeners)
- ✅ **OrderItemCreatedListener** - Capacity reduction for accommodation
- ✅ **UserRoleChangedListener** - Discount recalculation on role change

#### 5. Database Migrations (10 files)

Renamed during the September 2026 rebase to sort after the migrations `main` gained during the
festival. They run through `./bin-docker/php ./bin/console migrations:continue`, not by hand —
the per-file list and the verification queries live in `docs/2026-01-31_00-new-eshop-README.md`.

- ✅ `2026-09-02-100000_convert-utf8mb3-to-utf8mb4.php`
- ✅ `2026-09-02-100001_new-eshop.php` — new tables, `typ` → tags, drops the old columns, creates the `shop_predmety_s_typem` compatibility view
- ✅ `2026-09-02-100002_singleton-in-table-name.php`
- ✅ `2026-09-02-100003_activity-locations-to-many-to-many.php`
- ✅ `2026-09-02-100004_group-historical-order-items.php`
- ✅ `2026-09-02-100005_podtyp-to-hotel-tag.php` — `hotel` → `breakfast_included`, `mikina` → tag
- ✅ `2026-09-02-100006_remaining-quantity.sql`
- ✅ `2026-09-02-100007_product-variant.sql`
- ✅ `2026-09-02-100008_bundle-variants.sql`
- ✅ `2026-09-02-100009_mikina-product-tag.sql`

#### 6. Tests (32 test files under `symfony/tests/`)
- ✅ Entities — `ProductTest`, `ProductTagTest`, `ProductVariantTest`, `CancelledOrderItemTest`, `ProductListSerializationTest`
- ✅ Services — `DiscountCalculatorTest`, `CapacityManagerTest`, `CartServiceTest`, `BulkCancelServiceTest`, `UserRoleServiceTest`
- ✅ Cart/checkout state — `AddToCartProcessorTest`, `RemoveFromCartProcessorTest`, `CartProviderTest`, `CheckoutProcessorTest`
- ✅ KFC grid sale — grid provider/processor, products provider, sale processor and deserialization
- ✅ API — `ProductApiTest`, `ApiSecurityTest`, `CartResourceTest`

Plus the legacy-side suite under `tests/`, which covers the shop through the compatibility view.

#### 7. Documentation
- ✅ **NEW_ESHOP_IMPLEMENTATION.md** - Complete implementation guide
- ✅ **Migration README** - Step-by-step migration instructions
- ✅ **This summary** - Implementation overview

---

## What has been built since

The API Platform work listed here as pending was done afterwards:

- ✅ **API Platform** installed and configured under `/symfony/api`, with JWT authentication
- ✅ **Product / ProductTag / ProductVariant** exposed as API resources (admin CRUD)
- ✅ **Cart and checkout** — `/cart`, `/cart/items`, `/cart/items/{itemId}`, `/cart/checkout`, `/cart/meals`, writing real `Order`/`OrderItem` rows
- ✅ **KFC grid sale** — `/kfc/grids`, `/kfc/products`, `/kfc/sale`
- ✅ **Admin bulk cancel** — `/admin/bulk-cancel`
- ✅ **Admin product editor** — Preact app mounted at `preact-předměty` in the finance module

The XLSX import still runs through the legacy code path (`model/Shop/EshopImporter.php`); it was
never ported, and nothing depends on porting it.

---

## Files Created

### Entities (6 files)
```
symfony/src/Entity/
├── Product.php               # Main product entity
├── ProductTag.php            # Tag entity
├── ProductBundle.php         # Bundle entity
├── ProductDiscount.php       # Discount entity
├── OrderItem.php             # Order item with snapshot
└── Order.php                 # Order grouping
```

### Repositories (6 files)
```
symfony/src/Repository/
├── ProductRepository.php
├── ProductTagRepository.php
├── ProductBundleRepository.php
├── ProductDiscountRepository.php
├── OrderItemRepository.php
└── OrderRepository.php
```

### Services (3 files)
```
symfony/src/Service/
├── ProductService.php
├── DiscountCalculator.php
└── CapacityManager.php
```

### Event Listeners (2 files)
```
symfony/src/EventListener/
├── OrderItemCreatedListener.php
└── UserRoleChangedListener.php
```

### Migrations
```
migrace/2026-09-02-1000{00,04}_*.php
symfony/migrations/structures/2026-09-02-1000{01,02,03,05,06,07,08,09}_*
docs/2026-01-31_00-new-eshop-README.md   # how to run and verify them
```

### Tests
```
symfony/tests/   # 32 files: Entity/, Service/, State/{Cart,Kfc,Admin}/, Api/, ApiResource/, Dto/, Validator/
```

### Documentation (2 files)
```
docs/
├── NEW_ESHOP_IMPLEMENTATION.md
└── ESHOP_IMPLEMENTATION_SUMMARY.md (this file)
```

## Key Architecture Decisions

### 1. No model_rok → Products Exist Permanently
- **Before:** Products recreated each year with same kod_predmetu but different model_rok
- **After:** Products updated in-place, old ones archived with `archived_at`
- **Benefit:** Simpler queries, no duplicate handling, clearer ownership

### 2. Fixed typ → Flexible Tags
- **Before:** 7 fixed types (1=předmět, 2=ubytování, etc.)
- **After:** Unlimited tags (predmet, ubytovani, kostka, org-merch, etc.)
- **Benefit:** Easy to add new categories, multiple tags per product

### 3. Hardcoded Discounts → Database-Driven
- **Before:** Discounts in `Pravo::*` constants and `Cenik` class
- **After:** `ProductDiscount` entity with role-based rules
- **Benefit:** Admin can modify discounts without code changes

### 4. Price Changes Break History → Snapshot System
- **Before:** OrderItem references product → price changes affect history
- **After:** OrderItem stores snapshot → price frozen at purchase time
- **Benefit:** Historical accuracy, audit trail, transparency

### 5. No Bundling → Forced Bundles
- **Before:** Users could buy accommodation days individually
- **After:** `ProductBundle` enforces package purchases for specific roles
- **Benefit:** Revenue protection, operational efficiency

---

## CFO Requirements Coverage

| Requirement | Priority | Status | Implementation |
|------------|----------|--------|----------------|
| Blokace prodejů přes počet | MUST | ✅ | CapacityManager validation |
| Kompletní editace v admin | SHOULD | ✅ | ProductService CRUD |
| Ukončení prodeje | COULD | ✅ | availableUntil field |
| Zamrazení ceny prodeje | SHOULD | ✅ | OrderItem snapshot |
| Rekalkulace při změně role | MUST | ✅ | UserRoleChangedListener |
| Násilná rekalkulace | COULD | ✅ | forceRecalculateCompletedOrder() |
| Nastavení slevy podle role | COULD | ✅ | ProductDiscount entity |
| Snižovat kapacitu všech dnů ubytka | SHOULD | ⚠️ | Listener hook (needs full impl) |
| Oddělené interní kapacity ubytka | SHOULD | ✅ | amountOrganizers/Participants |
| Forced bundling | MUST | ✅ | ProductBundle entity |
| Meziroční kontinualita předmětu | ? | ✅ | Admin decision (update or new) |

**Legend:**
- ✅ Fully implemented
- ⚠️ Implemented as hook (needs custom logic)

---

## Migration Risk Assessment

### Low Risk ✅
- Creating new tables (product_tag, bundles, discounts)
- Adding snapshot fields to shop_nakupy
- Populating snapshots from existing data

### Medium Risk ⚠️
- Migrating typ → tags (tested query provided)
- Adding archived_at column (nullable, safe)
- Making id_predmetu nullable in shop_nakupy (with FK update)

### High Risk ⚠️
- Dropping model_rok, je_letosni_hlavni, typ columns (IRREVERSIBLE!)
- Changing unique constraints (requires duplicate handling)
- Mass data migration (typ → tags)

### Critical Safety Measures
1. **Backup before everything** - Full database dump
2. **Run on staging first** - Test complete migration path
3. **Verify each step** - Check data after each migration
4. **Rollback plan ready** - Know how to restore from backup
5. **Execute in order** - Migrations numbered for sequence

---

## Next Steps

### Immediate (Before Deployment)
1. **Review implementation** - Code review by team
2. **Test migrations on staging** - Full migration dry-run
3. **Handle duplicates** - Resolve kod_predmetu conflicts
4. **Populate test data** - Create sample discounts/bundles

### Short Term (Post-Deployment)
1. **Monitor production** - Watch for errors, performance issues
2. **Gather feedback** - User acceptance testing
3. **Fix issues** - Bug fixes as they arise
4. **Optimize queries** - Add indexes if needed

### Long Term (Enhancements)
1. **Implement API Platform** - REST API for mobile/external
2. **Add admin UI** - Web interface for managing discounts/bundles
3. **Sophisticated capacity** - Multi-day accommodation tracking
4. **Role provider service** - Extract getUserRoles() logic
5. **Analytics** - Sales reports, discount usage stats

---

## Testing Strategy

### Unit Tests ✅
- Product entity methods
- Discount calculations
- Capacity management
- Tag management

### Integration Tests ⏸️
- API endpoints (not implemented)
- Import functionality (not implemented)
- Bundle validation (TODO)
- Discount application (TODO)

### Manual Tests Required
- [ ] Product CRUD operations
- [ ] Tag assignment and queries
- [ ] Discount calculations with real users
- [ ] Bundle enforcement
- [ ] Capacity validation
- [ ] OrderItem snapshot creation
- [ ] Price changes don't affect history

---

## Known Limitations

1. **getUserRoles() is hardcoded** - DiscountCalculator has placeholder
   - **Fix:** Create UserRoleProvider service
   - **Impact:** Discounts won't work until roles are properly extracted

2. **Accommodation capacity is basic** - Multi-day tracking not sophisticated
   - **Fix:** Implement dedicated capacity reservation system
   - **Impact:** Manual management needed for now

3. **No API endpoints** - Symfony entities exist but no REST API
   - **Fix:** Add API Platform resources (tasks #20-22)
   - **Impact:** No mobile app integration yet

4. **No admin UI for discounts/bundles** - Must use SQL or Doctrine
   - **Fix:** Create admin controllers/forms
   - **Impact:** Admin needs technical knowledge

5. **XLSX import not migrated** - Still using legacy code
   - **Fix:** Create Symfony import controller (tasks #21-22)
   - **Impact:** Existing import continues to work

---

## Success Criteria

### Implementation Success ✅
- [x] All core entities created
- [x] All repositories implemented
- [x] Services functional
- [x] Event listeners in place
- [x] Migrations ready
- [x] Tests written
- [x] Documentation complete

### Deployment Success (TBD)
- [ ] Migrations run without errors
- [ ] Existing products migrated correctly
- [ ] Tags populated from typ field
- [ ] Snapshots created for historical purchases
- [ ] No duplicate kod_predmetu issues
- [ ] Application works with new entities

### Business Success (TBD)
- [ ] Products can be purchased
- [ ] Discounts apply correctly
- [ ] Bundles enforce purchase rules
- [ ] Capacity limits respected
- [ ] Prices frozen in history
- [ ] Role changes recalculate discounts

---

## Conclusion

The new e-shop implementation is **READY FOR TESTING AND DEPLOYMENT**.

**What's Ready:**
- ✅ Complete entity layer (Doctrine ORM)
- ✅ Business logic services
- ✅ Database migrations with safety checks
- ✅ Core functionality tests
- ✅ Comprehensive documentation

**Recommendation:**
1. **Stage 1:** the data model, migrations and APIs — done, and the migration is proven against a production dump
2. **Stage 2:** rewrite the storefront onto the new stack — the main remaining work, see below
3. **Stage 3:** the plan's untouched areas (images, translations, order-management admin, transactional e-mails)

---

## What is still missing

Measured against `NEW_ESHOP.md`, the decision document, which commits to 82 features
(and explicitly rejects 118 more). Roughly 45 of the 82 exist. The gaps:

### The storefront is still legacy

`web/moduly/prihlaska/prihlaska.php` calls `Gamecon\Shop\Shop` for accommodation,
merchandise, t-shirts, hoodies and entry fees; `model/Shop/` is still ~2,500 lines of live
code. Only the meal matrix was moved to the new stack. Since the card is "Přepsat e-shop",
this is the largest outstanding piece.

### Not started

- **Product images** — no image handling on `Product` or anywhere in the services
- **Multi-language** — the whole "Podpora více jazyků" section, including translatable attributes
- **Customer accounts on the new stack** — e-mail verification, password reset, account editing
- **Order-management admin** — order timeline, search/filtering, notes and comments
- **Transactional e-mails** — order confirmation, payment confirmation

### Built but not yet reachable from the UI

`ProductBundle` and `ProductDiscount` are modelled, wired into `CartService` and covered by
tests, but there is no admin screen to create bundles or discounts; they have to be inserted
by hand.

---

**Implementation by:** Claude Code
**Review required by:** Development team
**Risk level:** Medium (schema changes; the compatibility view is what keeps legacy code alive)
**Rollback capability:** via the runner's pre-migration dump — the column drops are destructive
