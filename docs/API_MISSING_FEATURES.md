# Missing Features for Complete API-Only E-shop

**Date:** 2026-02-09
**Context:** API Platform installed, Product entity configured
**Goal:** Complete REST API without UI

---

## ✅ What We Have

- ✅ API Platform installed and configured
- ✅ Product entity with full API Resource configuration
- ✅ Routes registered (`/api/products`)
- ✅ Serialization groups configured
- ✅ Basic filters (search, order, range)
- ✅ Database entities (Product, Order, OrderItem, ProductTag, ProductBundle, ProductDiscount, CancelledOrderItem)
- ✅ Service classes (DiscountCalculator, CapacityManager, ProductService)
- ✅ Repository classes with query methods
- ✅ Event listener classes (not hooked up)

---

## ❌ What's Missing (API-Only Focus)

### 🔴 **PHASE 1: Authentication (BLOCKING)**

#### 1.1 JWT Authentication Bundle

**Status:** Not installed

**Required:**
```bash
composer require lexik/jwt-authentication-bundle
```

**Configuration needed:**
```yaml
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 3600
```

**Generate keys:**
```bash
php bin/console lexik:jwt:generate-keypair
```

#### 1.2 Security Configuration

**File:** `config/packages/security.yaml`

**Missing:**
- JWT firewall for `/api` routes
- Access control rules
- User provider for API authentication
- Role hierarchy

**Example needed:**
```yaml
security:
    firewalls:
        api:
            pattern: ^/api
            stateless: true
            jwt: ~

    access_control:
        - { path: ^/api/products, roles: PUBLIC_ACCESS, methods: [GET] }
        - { path: ^/api/products, roles: ROLE_ADMIN, methods: [POST, PUT, PATCH, DELETE] }
        - { path: ^/api/orders, roles: ROLE_USER }
```

#### 1.3 Login Endpoint

**File:** Create `symfony/src/Controller/AuthController.php`

**Missing:**
- `POST /api/login` - Returns JWT token
- Token validation logic
- Integration with legacy User entity

---

### 🟠 **PHASE 2: Entity API Resources**

#### 2.1 Order Entity API Resource

**File:** `symfony/src/Entity/Order.php`

**Missing:**
- `#[ApiResource]` annotation
- Operations configuration:
  - `GET /api/orders` - List customer's orders
  - `GET /api/orders/{id}` - Get order details
  - `POST /api/orders` - Create order (checkout)
  - `PATCH /api/orders/{id}` - Update order (admin only)
  - `DELETE /api/orders/{id}` - Cancel order
- Serialization groups
- Security rules (customer sees only their orders)
- Filters (by year, status, customer)

**Required fields in serialization:**
- Order number generation logic
- Status field
- Total amount calculation
- OrderItems collection

#### 2.2 OrderItem Entity API Resource

**File:** `symfony/src/Entity/OrderItem.php`

**Missing:**
- `#[ApiResource]` annotation
- Sub-resource configuration under Order
- Operations:
  - `GET /api/order_items` - List items (for cart)
  - `POST /api/order_items` - Add to cart
  - `PATCH /api/order_items/{id}` - Update quantity
  - `DELETE /api/order_items/{id}` - Remove from cart
- Snapshot fields in serialization
- Discount information exposure

#### 2.3 ProductTag Entity API Resource

**File:** `symfony/src/Entity/ProductTag.php`

**Missing:**
- `#[ApiResource]` annotation
- Operations:
  - `GET /api/product_tags` - List all tags
  - `POST /api/product_tags` - Create tag (admin)
  - `DELETE /api/product_tags/{id}` - Remove tag (admin)
- Tag search/filter
- Products count per tag

#### 2.4 ProductBundle Entity API Resource

**File:** `symfony/src/Entity/ProductBundle.php`

**Missing:**
- `#[ApiResource]` annotation
- Operations:
  - `GET /api/product_bundles` - List bundles
  - `GET /api/product_bundles/{id}` - Bundle details
  - `POST /api/product_bundles` - Create bundle (admin)
  - `PUT /api/product_bundles/{id}` - Update bundle (admin)
  - `DELETE /api/product_bundles/{id}` - Delete bundle (admin)
- Forced bundle flag exposure
- Applicable roles array
- Products collection

#### 2.5 ProductDiscount Entity API Resource

**File:** `symfony/src/Entity/ProductDiscount.php`

**Missing:**
- `#[ApiResource]` annotation
- Operations:
  - `GET /api/product_discounts` - List discounts
  - `POST /api/product_discounts` - Create discount (admin)
  - `PUT /api/product_discounts/{id}` - Update discount (admin)
  - `DELETE /api/product_discounts/{id}` - Delete discount (admin)
- Role-based filtering
- Product association
- Discount percentage validation

#### 2.6 CancelledOrderItem Entity API Resource

**File:** `symfony/src/Entity/CancelledOrderItem.php`

**Missing:**
- `#[ApiResource]` annotation (read-only)
- Operations:
  - `GET /api/cancelled_order_items` - List cancelled items (admin)
  - `GET /api/cancelled_order_items/{id}` - Details (admin)
- Cancellation reason exposure
- Cancelled by user information
- Filters by reason, date, customer

---

### 🟡 **PHASE 3: Custom API Operations**

#### 3.1 Cart Management

**Create:** `symfony/src/Controller/CartController.php` or use API Platform State Processors

**Missing Endpoints:**
- `POST /api/cart/add` - Add product to cart
  - Validates stock/capacity
  - Checks bundle requirements
  - Returns updated cart
- `PATCH /api/cart/items/{id}` - Update quantity
  - Validates new quantity against stock
- `DELETE /api/cart/items/{id}` - Remove item
- `GET /api/cart` - Get current cart
  - Includes calculated discounts
  - Shows total price
  - Lists all items
- `DELETE /api/cart/clear` - Empty cart

**Integration needed:**
- CapacityManager validation
- Bundle enforcement (ProductBundleRepository::isForcedBundleViolation)
- User session/authentication

#### 3.2 Checkout Operation

**Create:** `symfony/src/StateProcessor/CheckoutProcessor.php`

**Missing:**
- `POST /api/cart/checkout` - Convert cart to order
  - Final stock validation
  - Apply discounts
  - Create Order entity
  - Generate order number
  - Move OrderItems from pending to completed
  - Trigger email notification
  - Return order details

**Integration needed:**
- DiscountCalculator::calculateDiscountsForUser
- CapacityManager::canPurchase
- OrderItemCreatedListener (capacity reduction)
- Email service

#### 3.3 Discount Preview

**Create:** Custom API Platform operation

**Missing:**
- `GET /api/products/{id}/discount-preview` - Preview discount for current user
  - Returns: original price, discounted price, discount reason
  - Requires authentication
- `POST /api/cart/preview-discounts` - Preview discounts for cart
  - Returns: item-by-item breakdown

**Integration needed:**
- DiscountCalculator service
- User role provider

#### 3.4 Bulk Operations

**Create:** `symfony/src/Controller/Admin/BulkOrderController.php`

**Missing:**
- `POST /api/admin/orders/bulk-cancel` - Cancel multiple orders
  - Accepts: array of order IDs or criteria (e.g., non-paying users)
  - Returns: count of cancelled orders
  - Moves to cancelled_order_items table
- `POST /api/admin/products/bulk-archive` - Archive multiple products
- `POST /api/admin/products/bulk-update-state` - Update state for multiple products

**Security:** Admin only

---

### 🔵 **PHASE 4: Business Logic Integration**

#### 4.1 Stock/Capacity Validation

**Create:** `symfony/src/StateProcessor/OrderItemProcessor.php`

**Missing:**
- API Platform State Processor for OrderItem
- On `POST /api/order_items` (add to cart):
  - Call `CapacityManager::canPurchase()`
  - Return 400 error if out of stock
  - Return clear error message
- On `POST /api/cart/checkout`:
  - Validate entire cart
  - Atomic stock reduction

**Files to modify:**
- OrderItem entity - add state processor attribute
- CapacityManager - ensure proper integration

#### 4.2 Discount Calculation

**Create:** `symfony/src/StateProcessor/DiscountApplicationProcessor.php`

**Missing:**
- Automatic discount application on cart operations
- User role provider service (replace hardcoded getUserRoles)
- Integration points:
  - When adding to cart
  - When user role changes
  - On checkout

**Files to modify:**
- DiscountCalculator - replace placeholder getUserRoles()
- Create UserRoleProvider service

#### 4.3 Bundle Validation

**Create:** `symfony/src/Validator/BundleEnforcementValidator.php`

**Missing:**
- Custom Symfony validator constraint
- Validates that forced bundle rules are followed
- Integration:
  - On `POST /api/order_items`
  - Returns 400 with message "Tento produkt musíte koupit jako součást balíčku"

**Files to modify:**
- OrderItem entity - add validation constraint
- ProductBundleRepository::isForcedBundleViolation - use this method

#### 4.4 Event Listener Hookup

**Modify:** `symfony/src/EventListener/OrderItemCreatedListener.php`

**Currently:** Stub with TODO comment

**Missing Implementation:**
- Complete `handleAccommodationCapacity()` method
  - Check if product has 'ubytovani' tag
  - Find all related accommodation nights
  - Reduce capacity for ALL nights
  - Track which nights are booked together
- Register Doctrine lifecycle callback
- Test with real accommodation data

**Modify:** `symfony/src/EventListener/UserRoleChangedListener.php`

**Currently:** Exists but not triggered

**Missing:**
- Add event dispatcher to UserRole entity
- Dispatch UserRoleChangedEvent when role assigned/removed
- Test discount recalculation

**Create:** Event classes
- `symfony/src/Event/UserRoleChangedEvent.php`
- `symfony/src/Event/OrderStatusChangedEvent.php`

---

### 🟢 **PHASE 5: Reporting API (MUST HAVE)**

#### 5.1 Purchase Reporting

**Create:** `symfony/src/Controller/ReportController.php`

**Required Endpoints (MUST for operations):**

**`GET /api/reports/purchases/by-product`**
- Returns: Product-level purchase counts
- Query params: year, product_id, tag
- Example: How many shirts total?

**`GET /api/reports/purchases/by-variant`**
- Returns: Variant-level breakdown (size × color)
- Critical for ordering merchandise
- Example: 15x S-blue, 22x M-red, 8x L-blue

**`GET /api/reports/purchases/food-matrix`**
- Returns: Food purchases by day × meal type
- Example:
  ```json
  {
    "thursday": {"snidane": 50, "obed": 60, "vecere": 55},
    "friday": {"snidane": 80, "obed": 90, "vecere": 85}
  }
  ```

**`GET /api/reports/purchases/accommodation`**
- Returns: Accommodation by night
- Shows: total booked, capacity remaining, org vs participant split

**Integration:**
- OrderItemRepository query methods
- Group by product, tag, variant
- Filter by year

#### 5.2 Financial Reporting

**Required Endpoints (MUST for finance):**

**`GET /api/reports/financial/summary`**
- Returns: Total revenue, items sold, average order value
- Filter by: year, date range

**`GET /api/reports/financial/by-customer-group`**
- Returns: Revenue split by role (organizator, vypravec, ucastnik)
- Shows: total paid, total discounted, total free

**`GET /api/reports/financial/by-discount-type`**
- Returns: Breakdown of full-price vs discounted vs free
- Example:
  ```json
  {
    "full_price": {"count": 150, "revenue": "45000.00"},
    "discounted": {"count": 80, "revenue": "12000.00"},
    "free": {"count": 30, "revenue": "0.00"}
  }
  ```

**`GET /api/reports/financial/by-product`**
- Returns: Revenue per product
- Sortable by revenue, quantity

**Integration:**
- OrderItem original_price, discount_amount fields
- Join with User roles
- Aggregate financial data

#### 5.3 Timeline Reporting (COULD HAVE)

**`GET /api/reports/timeline/sales`**
- Returns: Sales over time (daily/weekly breakdown)
- Used for budget projections
- Filter by: product, tag, date range

---

### ⚪ **PHASE 6: Advanced Features**

#### 6.1 Accommodation Room Sharing

**Create:** `symfony/src/Controller/AccommodationController.php`

**Missing:**
- `POST /api/accommodation/room-sharing` - Set roommate preference
  - Body: `{ "roommate_user_id": 123 }`
  - Validates: user exists, has accommodation
  - Updates: User.ubytovan_s field
- `GET /api/accommodation/my-room` - Get room assignment
- Validation of room capacity vs group size

#### 6.2 Multi-Day Accommodation Capacity

**Already partially done but needs completion:**

- Complete OrderItemCreatedListener logic
- Create capacity tracking table (optional)
- Test with real data

#### 6.3 Separate Org/Participant Capacities

**Already exists in Product entity but needs:**

- Validation in CapacityManager to check correct capacity pool
- Admin API to set these capacities
- Reporting to show capacity splits

#### 6.4 Food Matrix Selection

**Create:** Custom API operation

**Missing:**
- `GET /api/food/matrix` - Get food options matrix (days × types)
- `POST /api/food/bulk-order` - Order multiple meals at once
  - Body: `[{day: "thursday", type: "obed"}, {day: "friday", type: "vecere"}]`
  - Validates all items, then creates OrderItems

#### 6.5 Product Search & Advanced Filters

**Add to Product entity:**

- Full-text search on name/description
- Filter by multiple tags (AND/OR logic)
- Filter by availability (in stock, available until)
- Filter by capacity (available/sold out)
- Custom filter for "recommended products"

---

### 🧪 **PHASE 7: Testing & Documentation**

#### 7.1 API Integration Tests

**Create:** `symfony/tests/Api/`

**Missing test files:**
- `ProductApiTest.php` - Test all Product CRUD operations
- `OrderApiTest.php` - Test order creation, listing, cancellation
- `CartApiTest.php` - Test cart operations
- `DiscountApiTest.php` - Test discount application
- `BundleValidationApiTest.php` - Test forced bundle enforcement
- `CapacityApiTest.php` - Test stock validation
- `ReportingApiTest.php` - Test reporting endpoints
- `AuthApiTest.php` - Test JWT authentication

#### 7.2 OpenAPI Documentation

**Missing:**
- Custom descriptions for operations
- Request/response examples
- Error response documentation
- Authentication flow documentation

**Add to entities:**
```php
#[ApiResource(
    description: 'Products available in e-shop',
    operations: [
        new GetCollection(
            description: 'Retrieves the collection of Product resources',
        ),
    ]
)]
```

#### 7.3 API Usage Documentation

**Create:** `docs/API_USAGE.md`

**Missing:**
- Authentication flow (how to get JWT token)
- Common use cases with curl examples
- Error handling guide
- Rate limiting info
- Pagination guide

---

## Implementation Checklist

### Immediate (Can Start Now)

- [x] Install API Platform ✅ DONE
- [x] Configure Product entity ✅ DONE
- [ ] Install JWT authentication bundle
- [ ] Configure security.yaml
- [ ] Create login endpoint
- [ ] Test authentication flow

### High Priority (Core Functionality)

- [ ] Add `#[ApiResource]` to Order entity
- [ ] Add `#[ApiResource]` to OrderItem entity
- [ ] Create cart management endpoints
- [ ] Create checkout operation
- [ ] Integrate DiscountCalculator
- [ ] Integrate CapacityManager
- [ ] Complete OrderItemCreatedListener
- [ ] Hook up UserRoleChangedListener

### Medium Priority (Business Requirements)

- [ ] Create purchase reporting endpoints (MUST)
- [ ] Create financial reporting endpoints (MUST)
- [ ] Add `#[ApiResource]` to ProductTag entity
- [ ] Add `#[ApiResource]` to ProductBundle entity
- [ ] Add `#[ApiResource]` to ProductDiscount entity
- [ ] Implement bundle validation
- [ ] Create bulk operations endpoints

### Lower Priority (Nice to Have)

- [ ] Timeline reporting (COULD)
- [ ] Room sharing API
- [ ] Food matrix selection
- [ ] Advanced search filters
- [ ] API integration tests
- [ ] OpenAPI documentation enhancements

---

## Estimated Effort

**Phase 1 (Auth):** 4-6 hours
**Phase 2 (Entity Resources):** 8-12 hours
**Phase 3 (Custom Operations):** 12-16 hours
**Phase 4 (Business Logic):** 8-12 hours
**Phase 5 (Reporting):** 10-14 hours
**Phase 6 (Advanced):** 6-10 hours
**Phase 7 (Testing):** 12-16 hours

**Total:** 60-86 hours for complete API-only implementation

---

## Success Criteria

### Minimum Viable API

- ✅ Authentication works (JWT tokens)
- ✅ Can list/get/create/update products
- ✅ Can create orders (checkout flow)
- ✅ Cart operations work
- ✅ Discounts apply automatically
- ✅ Stock validation prevents overselling
- ✅ Purchase reporting works
- ✅ Financial reporting works

### Complete API

- ✅ All entities exposed via API
- ✅ All business rules enforced
- ✅ All event listeners working
- ✅ Bulk operations available
- ✅ Advanced features (bundles, accommodation)
- ✅ Comprehensive tests
- ✅ Full documentation

---

**Last Updated:** 2026-02-09
**Current Status:** Phase 1 Ready (API Platform installed)
**Next Step:** Install JWT Authentication
