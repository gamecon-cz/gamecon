# API Platform - Next Steps

**Status:** ✅ API Platform Installed & Configured
**Date:** 2026-02-09

---

## What Was Completed

✅ **API Platform Core installed** (v4.2.6)
✅ **Bundle registered** in symfony/config/bundles.php
✅ **Configuration created** (config/packages/api_platform.yaml)
✅ **Routes configured** with prefix `/symfony/api`
✅ **Product entity** fully configured as API Resource
✅ **Serialization groups** added (product:list, product:read, product:write)
✅ **Filters configured** (search, order, range)
✅ **Security rules** defined (public GET, admin-only POST/PUT/PATCH/DELETE)

---

## Available Endpoints

**Product API (fully functional):**
- `GET /symfony/api/products.json` - List products (public)
- `GET /symfony/api/products/{id}.json` - Get product (public)
- `POST /symfony/api/products.json` - Create product (admin only)*
- `PUT /symfony/api/products/{id}.json` - Update product (admin only)*
- `PATCH /symfony/api/products/{id}.json` - Partial update (admin only)*
- `DELETE /symfony/api/products/{id}.json` - Delete product (admin only)*

\* Requires JWT authentication (not yet implemented)

---

## What's Missing for Complete API

### 🔴 **CRITICAL - Block All Admin Operations**

**1. JWT Authentication** (4-6 hours)
```bash
composer require lexik/jwt-authentication-bundle
php bin/console lexik:jwt:generate-keypair
```

Without this:
- All POST/PUT/PATCH/DELETE operations return 403
- No way to create/modify products via API
- No user-specific operations (cart, orders)

---

### 🟠 **HIGH PRIORITY - Core E-commerce** (30-40 hours)

**2. Remaining Entity API Resources**
- Order entity - `/symfony/api/orders`
- OrderItem entity - `/symfony/api/order_items`
- ProductTag entity - `/symfony/api/product_tags`
- ProductBundle entity - `/symfony/api/product_bundles`
- ProductDiscount entity - `/symfony/api/product_discounts`
- CancelledOrderItem entity - `/symfony/api/cancelled_order_items`

**3. Cart Operations**
- POST `/symfony/api/cart/add` - Add to cart
- PATCH `/symfony/api/cart/items/{id}` - Update quantity
- DELETE `/symfony/api/cart/items/{id}` - Remove from cart
- GET `/symfony/api/cart` - Get cart contents

**4. Checkout Flow**
- POST `/symfony/api/cart/checkout` - Create order from cart

**5. Business Logic Integration**
- Stock/capacity validation (CapacityManager)
- Discount calculation (DiscountCalculator)
- Bundle enforcement validation
- Event listeners hookup

---

### 🟡 **MUST HAVE - Reporting** (10-14 hours)

**6. Purchase Reporting** (required for operations)
- GET `/symfony/api/reports/purchases/by-product`
- GET `/symfony/api/reports/purchases/by-variant`
- GET `/symfony/api/reports/purchases/food-matrix`
- GET `/symfony/api/reports/purchases/accommodation`

**7. Financial Reporting** (required for finance)
- GET `/symfony/api/reports/financial/summary`
- GET `/symfony/api/reports/financial/by-customer-group`
- GET `/symfony/api/reports/financial/by-discount-type`

---

### 🟢 **NICE TO HAVE - Advanced Features** (16-24 hours)

**8. Bulk Operations**
- POST `/symfony/api/admin/orders/bulk-cancel`
- POST `/symfony/api/admin/products/bulk-archive`

**9. Accommodation Features**
- POST `/symfony/api/accommodation/room-sharing`
- Multi-day capacity enforcement
- Separate org/participant pools

**10. Food Matrix**
- GET `/symfony/api/food/matrix`
- POST `/symfony/api/food/bulk-order`

---

## Recommended Implementation Order

### Week 1: Authentication & Core Entities
1. Install JWT authentication bundle
2. Configure security.yaml
3. Create login endpoint
4. Test authentication flow
5. Add API Resource to Order entity
6. Add API Resource to OrderItem entity

### Week 2: Cart & Checkout
7. Create cart management endpoints
8. Integrate CapacityManager validation
9. Integrate DiscountCalculator
10. Create checkout operation
11. Complete OrderItemCreatedListener
12. Test cart-to-order flow

### Week 3: Business Logic & Reporting
13. Hook up UserRoleChangedListener
14. Implement bundle validation
15. Create purchase reporting endpoints
16. Create financial reporting endpoints
17. Test all business rules

### Week 4: Remaining Entities & Polish
18. Add API Resources to remaining entities
19. Create bulk operations
20. Add API integration tests
21. Update OpenAPI documentation
22. Create usage examples

---

## Testing Checklist

- [ ] Can authenticate and get JWT token
- [ ] Can list products (public access)
- [ ] Can create product (admin with JWT)
- [ ] Can add product to cart
- [ ] Can update cart quantity
- [ ] Can remove from cart
- [ ] Cart shows correct discounts
- [ ] Checkout creates order
- [ ] Stock validation prevents overselling
- [ ] Bundle validation enforces rules
- [ ] Accommodation capacity reduces all nights
- [ ] Purchase reports show correct data
- [ ] Financial reports show correct breakdown
- [ ] Role change recalculates discounts

---

## Documentation

**Created:**
- ✅ `docs/API_PLATFORM_SETUP.md` - Installation and Product API guide
- ✅ `docs/API_MISSING_FEATURES.md` - Complete feature breakdown (60 pages!)
- ✅ `docs/API_NEXT_STEPS.md` - This file

**Still needed:**
- API authentication flow documentation
- Endpoint usage examples for each resource
- Error handling guide
- Rate limiting documentation

---

## Quick Start Commands

```bash
# View all API routes
bin/console debug:router | grep symfony

# View Product API configuration
bin/console debug:config api_platform

# Clear cache after changes
bin/console cache:clear

# Generate JWT keys (when ready)
php bin/console lexik:jwt:generate-keypair

# Test Product endpoint (will work when data exists)
curl http://localhost/symfony/api/products.json
```

---

## Key Files Modified

- ✅ `composer.json` - Added API Platform dependencies
- ✅ `symfony/config/bundles.php` - Registered ApiPlatformBundle
- ✅ `config/packages/api_platform.yaml` - API configuration
- ✅ `symfony/config/routes.yaml` - Added API routes
- ✅ `symfony/src/Entity/Product.php` - Added ApiResource attributes

---

## Resources

- **API Platform Docs:** https://api-platform.com/docs/
- **JWT Bundle:** https://github.com/lexik/LexikJWTAuthenticationBundle
- **Symfony Security:** https://symfony.com/doc/current/security.html
- **Serializer:** https://symfony.com/doc/current/serializer.html

---

**Next Immediate Task:** Install JWT Authentication Bundle

```bash
composer require lexik/jwt-authentication-bundle
```
