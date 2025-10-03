# Doctrine Entity Migration Checklist

This is a step-by-step checklist for migrating legacy database tables to Doctrine entities with full backward
compatibility testing.

## Prerequisites

- [ ] Identify the legacy class to migrate
- [ ] Identify the database table name
- [ ] Choose a name for the new Doctrine entity

## Step 1: Research Phase

### 1.1 Find and Read Legacy Class

- [ ] Locate legacy class file (usually in `model/` directory)
- [ ] Note the namespace and class name
- [ ] Identify all properties and their types
- [ ] Review constructor and key methods
- [ ] Check for computed properties or special logic

### 1.2 Find Table Structure

- [ ] Search for `CREATE TABLE` in `migrace/*.php` files
- [ ] Check `tests/Db/data/*dump.sql` for current schema
- [ ] Look for `ALTER TABLE` migrations that modified the table
- [ ] **Important:** Check for `DROP COLUMN` statements (columns to exclude)
- [ ] List all current columns with their types

### 1.3 Study Existing Patterns

- [ ] Read `tests/Symfony/EntityLegacyComparisonTest.php`
- [ ] Review 2-3 similar entities in `symfony/src/Entity/`
- [ ] Review corresponding factories in `tests/Factory/`
- [ ] Understand the test pattern used

## Step 2: Create Doctrine Entity

### 2.1 Inspect Database Table Structure

- [ ] Query the database to see exact column structure: `SHOW CREATE TABLE table_name;`
- [ ] Or use phpMyAdmin to inspect table structure
- [ ] Note down:
  - All column names and types
  - Which columns are nullable
  - Default values
  - Primary key(s)
  - Unique constraints
  - Indexes
  - Foreign keys

### 2.2 Create Entity File

- [ ] Create `symfony/src/Entity/[EntityName].php`
- [ ] Use existing similar entity as a template (copy and modify)
- [ ] Add strict types declaration: `declare(strict_types=1);`
- [ ] Set namespace: `namespace App\Entity;`
- [ ] Add use statements:
  ```php
  use App\Repository\[EntityName]Repository;
  use Doctrine\DBAL\Types\Types;
  use Doctrine\ORM\Mapping as ORM;
  ```
- [ ] Map each column from database to property with correct Doctrine type

### 2.3 Add Class Annotations

- [ ] Add entity attribute: `#[ORM\Entity(repositoryClass: [EntityName]Repository::class)]`
- [ ] Add table attribute: `#[ORM\Table(name: 'table_name')]`
- [ ] Add unique constraints if applicable
- [ ] Add indexes if applicable (check table structure)
- [ ] Add PHPDoc with legacy class reference

### 2.4 Map Primary Key

- [ ] Add ID property
- [ ] Add `#[ORM\Id]` attribute
- [ ] Add `#[ORM\GeneratedValue]` attribute (if auto-increment)
- [ ] Add `#[ORM\Column]` with correct name and type
- [ ] Create `getId(): ?int` method

### 2.4 Map All Columns

For each table column:

- [ ] Create private property (camelCase)
- [ ] Add `#[ORM\Column]` attribute with:
    - `name:` database column name (snake_case)
    - `type:` appropriate Doctrine type
    - `length:` for string types
    - `nullable:` true if NULL allowed
    - `options:` for defaults if needed
- [ ] Add getter method
- [ ] Add setter method (return `self` for fluent interface)
- [ ] Use `is*()` naming for boolean getters

### 2.5 Handle Special Column Types

- [ ] DECIMAL: use `Types::DECIMAL` with precision and scale
- [ ] DATE: use `Types::DATE_MUTABLE`
- [ ] DATETIME/TIMESTAMP: use `Types::DATETIME_MUTABLE`
- [ ] ENUM: use appropriate PHP type
- [ ] TEXT: use `Types::TEXT`
- [ ] BOOLEAN: use `Types::BOOLEAN`

## Step 3: Create Repository

### 3.1 Create Repository File

- [ ] Create `symfony/src/Repository/[EntityName]Repository.php`
- [ ] Add strict types declaration
- [ ] Set namespace: `namespace App\Repository;`
- [ ] Extend `ServiceEntityRepository`

### 3.2 Implement Required Methods

- [ ] Add `__construct(ManagerRegistry $registry)` calling parent
- [ ] Add `save([EntityName] $entity, bool $flush = false): void`
- [ ] Add `remove([EntityName] $entity, bool $flush = false): void`
- [ ] Add PHPDoc with `@extends` and `@method` tags

## Step 4: Create Factory

### 4.1 Create Factory File

- [ ] Create `tests/Factory/[EntityName]Factory.php`
- [ ] Set namespace: `namespace Gamecon\Tests\Factory;`
- [ ] Extend `PersistentProxyObjectFactory`
- [ ] Add PHPDoc: `@extends PersistentProxyObjectFactory<[EntityName]>`

### 4.2 Implement Factory Methods

- [ ] Implement `class(): string` returning entity class
- [ ] Implement `defaults(): array | callable` with default values
- [ ] Use `self::faker()` for realistic test data
- [ ] Use `uniqid()` for unique values where needed
- [ ] Implement `initialize(): static` (can be empty)

### 4.3 Set Appropriate Defaults

- [ ] Strings: `self::faker()->text()` or hardcoded with `uniqid()`
- [ ] Integers: `self::faker()->numberBetween(min, max)`
- [ ] Decimals: `(string) self::faker()->randomFloat(2, min, max)`
- [ ] Dates: `new \DateTime('...')` or null
- [ ] Booleans: true/false as appropriate

## Step 5: Update Test File

### 5.1 Add Imports

- [ ] Open `tests/Symfony/EntityLegacyComparisonTest.php`
- [ ] Add import for new Doctrine entity: `use App\Entity\[EntityName];`
- [ ] Add import for legacy class: `use Fully\Qualified\LegacyClass;`
- [ ] Add import for factory: `use Gamecon\Tests\Factory\[EntityName]Factory;`
- [ ] Keep imports alphabetically sorted

### 5.2 Create Test Method

- [ ] Add method: `public function test[EntityName]EntityMatchesLegacy[LegacyClassName](): void`
- [ ] Add PHPDoc if needed

### 5.3 Implement Test Structure

- [ ] Create test entity using factory with specific values
- [ ] Call `->_save()->_real()` to persist
- [ ] Get entity ID and assert not null
- [ ] Fetch same record using legacy class: `LegacyClass::zId($id)`
- [ ] Assert legacy object is not null

### 5.4 Add Assertions

- [ ] Compare IDs: `$this->assertEquals($entity->getId(), $legacy->id())`
- [ ] Get raw data: `$legacyData = $legacy->raw()`
- [ ] For each property, assert:
  ```php
  $this->assertEquals($entity->getProperty(), $legacyData['column_name']);
  ```

### 5.5 Handle Special Cases

- [ ] Date fields: use `->format('Y-m-d')` or `->format('Y-m-d H:i:s')`
- [ ] Nullable dates: wrap in `if ($entity->getDate())`
- [ ] Booleans: cast legacy value: `(bool) $legacyData['field']`
- [ ] Foreign keys: compare IDs if relationship mapped
- [ ] Enums: compare `->value` property

## Step 6: Run and Verify

### 6.1 Run the Test

- [ ] Execute: `vendor/bin/phpunit --filter test[EntityName]EntityMatchesLegacy`
- [ ] Verify all assertions pass
- [ ] Check assertion count matches expected

### 6.2 Fix Issues

Common issues to check:

- [ ] Column not found: Check if column was dropped in migration
- [ ] Type mismatch: Verify Doctrine type matches database type
- [ ] Date format: Ensure format string matches database format
- [ ] Null handling: Check nullable properties in entity

### 6.3 Run Static Analysis and Code Style Check

- [ ] Execute: `make static`
  - This command runs code style fixer (PHP-CS-Fixer) and PHPStan
  - It will automatically fix code style issues
  - You can also run `make phpstan` separately if you only need static analysis
- [ ] Fix any PHPStan errors related to new entity, repository, or factory
- [ ] Common PHPStan issues:
  - Missing type hints in PHPDoc `@method` annotations
  - Use `array<string, mixed>` for criteria parameters
  - Use `array<string, string>|null` for orderBy parameters

### 6.4 Final Verification

- [ ] Run full test suite: `vendor/bin/phpunit tests/Symfony/EntityLegacyComparisonTest.php`
- [ ] Ensure no other tests were broken
- [ ] Review code for adherence to project conventions

## Reference Information

### Doctrine Type Mappings

| Database Type  | Doctrine Type             | Notes                        |
|----------------|---------------------------|------------------------------|
| `int(11)`      | `Types::INTEGER`          |                              |
| `tinyint(1)`   | `Types::BOOLEAN`          | For true/false               |
| `tinyint(4)`   | `Types::SMALLINT`         | For small numbers            |
| `smallint(6)`  | `Types::SMALLINT`         |                              |
| `bigint(20)`   | `Types::BIGINT`           |                              |
| `varchar(N)`   | `Types::STRING`           | Add `length: N`              |
| `text`         | `Types::TEXT`             |                              |
| `mediumtext`   | `Types::TEXT`             |                              |
| `longtext`     | `Types::TEXT`             |                              |
| `decimal(M,D)` | `Types::DECIMAL`          | Add `precision: M, scale: D` |
| `date`         | `Types::DATE_MUTABLE`     |                              |
| `datetime`     | `Types::DATETIME_MUTABLE` |                              |
| `timestamp`    | `Types::DATETIME_MUTABLE` |                              |

### Date Format Strings

- **DATE**: `'Y-m-d'`
- **DATETIME**: `'Y-m-d H:i:s'`
- **TIMESTAMP**: `'Y-m-d H:i:s'`

### Common Legacy Class Locations

- `model/Uzivatel/` - User-related
- `model/Aktivita/` - Activities
- `model/Shop/` - Shop items and purchases
- `model/Role/` - Roles and permissions
- `model/SystemoveNastaveni/` - System settings

### Files Checklist

**New Files (3):**

- [ ] `symfony/src/Entity/[EntityName].php`
- [ ] `symfony/src/Repository/[EntityName]Repository.php`
- [ ] `tests/Factory/[EntityName]Factory.php`

**Modified Files (1):**

- [ ] `tests/Symfony/EntityLegacyComparisonTest.php`

## Example Migration Log

Keep track of completed migrations:

| Entity Name | Legacy Class           | Table Name      | Status | Date       | Notes                                                         |
|-------------|------------------------|-----------------|--------|------------|---------------------------------------------------------------|
| ShopItem    | `Gamecon\Shop\Predmet` | `shop_predmety` | ✅ Done | 2025-10-02 | Excluded dropped columns: auto, kategorie_predmetu, se_slevou |

---

## Tips

1. **Check migrations first** - Always look for ALTER TABLE and DROP COLUMN statements
2. **Copy similar entity** - Start by copying a similar entity and modify it
3. **Test incrementally** - Create entity, then repository, then factory, then test
4. **Use faker wisely** - For unique constraints, add `uniqid()` suffix
5. **Date handling** - Nullable dates need conditional formatting in tests
6. **Boolean vs int** - Legacy tables often use tinyint(1) for booleans
7. **Decimal as string** - Doctrine returns DECIMAL as string, not float
8. **Composite keys** - Some tables have composite primary keys, handle carefully

---

## Migration Status Tracker

This table tracks which legacy entities have been migrated to Doctrine entities.

| Status | Table Name                       | Legacy Entity (FQCN)                          | Doctrine Entity (FQCN)                     |
|--------|----------------------------------|-----------------------------------------------|--------------------------------------------|
| ✅      | `uzivatele_hodnoty`              | `Uzivatel`                                    | `\App\Entity\User`                         |
| ✅      | `stranky`                        | `Stranka`                                     | `\App\Entity\Page`                         |
| ✅      | `sjednocene_tagy`                | `Tag`                                         | `\App\Entity\Tag`                          |
| ✅      | `kategorie_sjednocenych_tagu`    | `\Gamecon\KategorieTagu`                      | `\App\Entity\CategoryTag`                  |
| ✅      | `akce_typy`                      | `\Gamecon\Aktivita\TypAktivity`               | `\App\Entity\ActivityType`                 |
| ✅      | `akce_stavy_log`                 | `\Gamecon\Aktivita\StavAktivity`              | `\App\Entity\ActivityState`                |
| ✅      | `akce_prihlaseni_stavy`          | `\Gamecon\Aktivita\AkcePrihlaseniStavy`       | `\App\Entity\ActivityRegistrationState`    |
| ✅      | `newsletter_prihlaseni`          | `\Gamecon\Newsletter\NewsletterPrihlaseni`    | `\App\Entity\NewsletterSubscription`       |
| ✅      | `role_seznam`                    | `\Gamecon\Role\Role`                          | `\App\Entity\Role`                         |
| ✅      | `r_prava_soupis`                 | `\Gamecon\Pravo`                              | `\App\Entity\Permission`                   |
| ✅      | `ubytovani`                      | `\Gamecon\Ubytovani\Ubytovani`                | `\App\Entity\Accommodation`                |
| ✅      | `shop_predmety`                  | `\Gamecon\Shop\Predmet`                       | `\App\Entity\ShopItem`                     |
| ✅      | `akce_lokace`                    | `Lokace`                                      | `\App\Entity\Location`                     |
| ✅      | `novinky`                        | `Novinka`                                     | `\App\Entity\News`                         |
| ✅      | `obchod_bunky`                   | `\Gamecon\Kfc\ObchodMrizkaBunka`              | `\App\Entity\ShopGridCell`                 |
| ✅      | `obchod_mrizky`                  | `\Gamecon\Kfc\ObchodMrizka`                   | `\App\Entity\ShopGrid`                     |
| ✅      | `platby`                         | `\Gamecon\Uzivatel\Platba`                    | `\App\Entity\Payment`                      |
| ✅      | `medailonky`                     | `\Gamecon\Uzivatel\Medailonek`                | `\App\Entity\Badge`                        |
| ✅      | `texty`                          | -                                             | `\App\Entity\Text`                         |
| ✅      | `akce_import`                    | -                                             | `\App\Entity\ActivityImport`               |
| ✅      | `akce_instance`                  | -                                             | `\App\Entity\ActivityInstance`             |
| ✅      | `akce_lokace_tmp`                | -                                             | `\App\Entity\ActivityLocationTemp`         |
| ✅      | `akce_organizatori`              | -                                             | `\App\Entity\ActivityOrganizer`            |
| ✅      | `akce_prihlaseni`                | -                                             | `\App\Entity\ActivityRegistration`         |
| ✅      | `akce_prihlaseni_log`            | -                                             | `\App\Entity\ActivityRegistrationLog`      |
| ✅      | `akce_prihlaseni_spec`           | -                                             | `\App\Entity\ActivityRegistrationSpec`     |
| ✅      | `akce_seznam`                    | -                                             | `\App\Entity\Activity`                     |
| ✅      | `akce_sjednocene_tagy`           | -                                             | `\App\Entity\ActivityTag`                  |
| ✅      | `akce_stav`                      | -                                             | `\App\Entity\ActivityStatus`               |
| ✅      | `google_api_user_tokens`         | -                                             | `\App\Entity\GoogleApiUserToken`           |
| ✅      | `google_drive_dirs`              | -                                             | `\App\Entity\GoogleDriveDir`               |
| ✅      | `hromadne_akce_log`              | -                                             | `\App\Entity\BulkActivityLog`              |
| ✅      | `log_udalosti`                   | -                                             | `\App\Entity\EventLog`                     |
| ✅      | `newsletter_prihlaseni_log`      | -                                             | `\App\Entity\NewsletterSubscriptionLog`    |
| 🚫      | `platne_role`                    | (VIEW - based on role_seznam)                 | -                                          |
| 🚫      | `platne_role_uzivatelu`          | (VIEW - based on uzivatele_role)              | -                                          |
| ✅      | `prava_role`                     | -                                             | `\App\Entity\RolePermission`               |
| ✅      | `reporty`                        | -                                             | `\App\Entity\Report`                       |
| ✅      | `reporty_log_pouziti`            | -                                             | `\App\Entity\ReportUsageLog`               |
| ✅      | `reporty_quick`                  | -                                             | `\App\Entity\QuickReport`                  |
| ✅      | `role_texty_podle_uzivatele`     | -                                             | `\App\Entity\UserRoleText`                 |
| ✅      | `shop_nakupy`                    | -                                             | `\App\Entity\ShopPurchase`                 |
| ✅      | `shop_nakupy_zrusene`            | -                                             | `\App\Entity\ShopPurchaseCancelled`        |
| ✅      | `slevy`                          | -                                             | `\App\Entity\Discount`                     |
| ✅      | `systemove_nastaveni`            | -                                             | `\App\Entity\SystemSetting`                |
| ✅      | `systemove_nastaveni_log`        | -                                             | `\App\Entity\SystemSettingLog`             |
| ✅      | `uzivatele_role`                 | -                                             | `\App\Entity\UserRole`                     |
| ✅      | `uzivatele_role_log`             | -                                             | `\App\Entity\UserRoleLog`                  |
| ✅      | `uzivatele_role_podle_rocniku`   | -                                             | `\App\Entity\UserRoleByYear`               |
| ✅      | `uzivatele_slucovani_log`        | -                                             | `\App\Entity\UserMergeLog`                 |
| ✅      | `uzivatele_url`                  | -                                             | `\App\Entity\UserUrl`                      |
| 🚫      | `migrations`                     | (internal - migrations tracking)              | -                                          |
| 🚫      | `mutex`                          | (internal - locking mechanism)                | -                                          |
| 🚫      | `_table_data_versions`           | (internal - versioning)                       | -                                          |
| 🚫      | `_tables_used_in_view_data_versions` | (internal - versioning)                   | -                                          |
| 🚫      | `_vars`                          | (internal - variables)                        | -                                          |

**Legend:**
- ✅ = Migrated and tested
- ❌ = Not yet migrated
- ⏳ = Work in progress
- 🚫 = Internal/system table (should not be migrated)

Add new rows as you identify more legacy entities that need migration.
