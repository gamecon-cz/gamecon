# API Platform Setup Complete

**Date:** 2026-02-09
**Status:** ✅ Installed and Configured

---

## What Was Done

### 1. API Platform Installation

```bash
composer require api-platform/core
```

**Installed Packages:**
- `api-platform/core` (v4.2.6)
- `symfony/serializer` (v7.4.5)
- `symfony/web-link` (v7.4.4)
- `willdurand/negotiation` (3.1.0)
- `psr/link` (2.0.1)

### 2. Bundle Registration

Added to `symfony/config/bundles.php`:
```php
ApiPlatform\Symfony\Bundle\ApiPlatformBundle::class => [
    'all' => true,
],
```

### 3. Configuration Files

**`config/packages/api_platform.yaml`:**
```yaml
api_platform:
    title: 'GameCon E-shop API'
    description: 'REST API for GameCon e-shop - products, orders, cart, and reporting'
    version: '1.0.0'

    defaults:
        stateless: true
        cache_headers:
            vary: ['Content-Type', 'Authorization', 'Origin']

    mapping:
        paths:
            - '%kernel.project_dir%/src/Entity'

    formats:
        jsonld: ['application/ld+json']
        json: ['application/json']
        html: ['text/html']

    collection:
        pagination:
            enabled: true
            items_per_page: 30
            maximum_items_per_page: 100
            page_parameter_name: 'page'
```

**`symfony/config/routes.yaml`:**
```yaml
api_platform:
    resource: .
    type: api_platform
    prefix: /symfony/api
```

### 4. Product Entity API Resource

Added API Platform annotations to `symfony/src/Entity/Product.php`:

**Operations:**
- `GET /api/products` - List products (public access)
- `GET /api/products/{id}` - Get product details (public access)
- `POST /api/products` - Create product (admin only)
- `PUT /api/products/{id}` - Update product (admin only)
- `PATCH /api/products/{id}` - Partial update (admin only)
- `DELETE /api/products/{id}` - Delete product (admin only)

**Filters:**
- Search: code (exact), name (partial), state (exact)
- Order: id, name, currentPrice, state
- Range: currentPrice, producedQuantity

**Serialization Groups:**
- `product:list` - Minimal data for collection
- `product:read` - Full product details
- `product:write` - Fields that can be modified

---

## Available Routes

```
GET    /symfony/api/products.{_format}          - List all products
GET    /symfony/api/products/{id}.{_format}     - Get single product
POST   /symfony/api/products.{_format}          - Create new product
PUT    /symfony/api/products/{id}.{_format}     - Update product (full)
PATCH  /symfony/api/products/{id}.{_format}     - Update product (partial)
DELETE /symfony/api/products/{id}.{_format}     - Delete product
```

**Formats supported:** `json`, `jsonld`, `html`

**Note:** All API routes are prefixed with `/symfony/api` to avoid conflicts with legacy application routes.

---

## Testing the API

### Via Symfony Console

```bash
# List all API routes
bin/console debug:router | grep api

# Check specific route details
bin/console debug:router _api_/products{._format}_get_collection
```

### Via HTTP (when deployed)

```bash
# Get all products
curl http://localhost/symfony/api/products.json

# Get single product
curl http://localhost/symfony/api/products/123.json

# Create product (requires auth)
curl -X POST http://localhost/symfony/api/products.json \
  -H "Content-Type: application/json" \
  -d '{"name": "Test Product", "code": "TEST-001", "currentPrice": "100.00", "state": 1}'

# Filter products by state
curl "http://localhost/symfony/api/products.json?state=1"

# Search by name
curl "http://localhost/symfony/api/products.json?name=tricko"

# Paginate
curl "http://localhost/symfony/api/products.json?page=2&itemsPerPage=10"
```

---

## What's Still Missing

### 🔴 **CRITICAL - Must Implement Next**

**1. Authentication & Authorization**
- No JWT authentication system
- Security rules configured but not enforced (no JWT bundle)
- `is_granted('ROLE_ADMIN')` checks won't work without auth

**Action Required:**
```bash
composer require lexik/jwt-authentication-bundle
# Configure JWT keys and security.yaml
```

**2. Remaining Entity API Resources**

Need to add `#[ApiResource]` to:
- ❌ **Order** - Cart and order management
- ❌ **OrderItem** - Line items in orders
- ❌ **ProductTag** - Tag management
- ❌ **ProductBundle** - Bundle configuration
- ❌ **ProductDiscount** - Discount rules
- ❌ **CancelledOrderItem** - Cancelled order archive

**3. Custom API Operations**

Need to create custom controllers/state processors for:
- ❌ Cart operations (add/remove/update items)
- ❌ Checkout flow
- ❌ Bulk order cancellation
- ❌ Discount preview
- ❌ Stock validation
- ❌ Bundle enforcement

**4. Business Logic Integration**

Need to integrate existing services:
- ❌ `DiscountCalculator` - Apply discounts on cart/checkout
- ❌ `CapacityManager` - Validate stock on purchase
- ❌ `ProductService` - Business logic orchestration

**5. Event Listeners Hookup**

Need to activate:
- ❌ `OrderItemCreatedListener` - Capacity reduction (stub exists)
- ❌ `UserRoleChangedListener` - Discount recalculation (not hooked)

**6. Reporting Endpoints**

Need to create custom operations:
- ❌ `GET /api/reports/purchases` - Purchase reporting (MUST)
- ❌ `GET /api/reports/financial` - Financial reporting (MUST)

---

## Next Steps (Priority Order)

### Phase 1: Authentication (Blocking Everything)

1. Install `lexik/jwt-authentication-bundle`
2. Generate JWT keys
3. Configure `security.yaml` for `/api` firewall
4. Create login endpoint
5. Test authenticated requests

### Phase 2: Complete Entity Resources

6. Add `#[ApiResource]` to Order, OrderItem
7. Add serialization groups to all entities
8. Configure proper security rules per operation
9. Test CRUD operations

### Phase 3: Business Logic

10. Create API Platform state processors for validation
11. Integrate DiscountCalculator
12. Integrate CapacityManager
13. Hook up event listeners
14. Test business rules enforcement

### Phase 4: Custom Operations

15. Create cart management endpoints
16. Create checkout operation
17. Create reporting endpoints
18. Create bulk operations

### Phase 5: Testing & Documentation

19. Write API integration tests
20. Document all endpoints
21. Add OpenAPI descriptions
22. Create usage examples

---

## Configuration Reference

### Serialization Groups Pattern

```php
#[Groups(['product:list', 'product:read', 'product:write'])]
```

- `{entity}:list` - Fields shown in collections
- `{entity}:read` - Fields shown in GET operations
- `{entity}:write` - Fields that can be modified

### Security Pattern

```php
#[ApiResource(
    operations: [
        new Get(security: "is_granted('PUBLIC_ACCESS')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
    ]
)]
```

### Filter Pattern

```php
#[ApiFilter(SearchFilter::class, properties: ['name' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'price'])]
#[ApiFilter(RangeFilter::class, properties: ['price'])]
```

---

## Resources

- [API Platform Documentation](https://api-platform.com/docs/)
- [Symfony Serializer](https://symfony.com/doc/current/serializer.html)
- [JWT Authentication Bundle](https://github.com/lexik/LexikJWTAuthenticationBundle)

---

**Last Updated:** 2026-02-09
**Next Task:** Install JWT Authentication Bundle
