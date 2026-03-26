# GameCon Project Guide for Claude

## Project Overview
GameCon is a Czech PHP web application for managing the largest Czechoslovak non-computer games festival. It's a comprehensive event management system with both public web interface and admin panel.

## Technology Stack
- **Language**: PHP 8.2+ with strict typing
- **Database**: MariaDB 10.6 
- **Architecture**: Custom MVC with XTemplate templating
- **Testing**: PHPUnit 10.0
- **Infrastructure**: Docker-based development
- **Deployment**: GitHub Actions automation

## Project Structure
```
/web/           - Public frontend (event info, registration)
/admin/         - Admin panel (event management)
/model/         - Business logic (27+ directories)
/tests/         - PHPUnit tests (55+ files)
/migrace/       - Database migrations
                  Migration files should be prefixed with `date +"%Y-%m-%d-%H%M%S"` (e.g., `2026-03-07-161642_some-description.php`)
/nastaveni/     - Configuration files
/vendor/        - Composer dependencies
/symfony/var/   - Symfony cache, logs, temporary files (NOT /var/)
```

**Important:** This project does NOT have a `/var/` directory. Use `/symfony/var/` for temporary scripts and files.

## Key Model Components
- `Uzivatel/` - User management, roles, payments
- `Aktivita/` - Event activities, scheduling
- `SystemoveNastaveni/` - System configuration
- Database abstraction via `db-object.php`

## Development Commands
```bash
# Start development environment
docker compose up

# Run tests
vendor/bin/phpunit

# Database access (use dbal:run-sql to ensure correct DB)
./bin-docker/php ./bin/console dbal:run-sql 'SELECT 1'  # Execute SQL query in current DB

# Symfony console (from project root)
bin/console <command>                    # Run Symfony console commands
bin/console doctrine:mapping:info        # Check entity mappings
bin/console doctrine:schema:validate     # Validate database schema
bin/console cache:clear                  # Clear cache

# Access points
# http://localhost/web - Public site
# http://localhost/admin - Admin panel
# http://localhost:8081 - phpMyAdmin
```

## Important Dependencies
- `symfony/mailer` - Email notifications
- `endroid/qr-code` - Payment QR codes
- `google/apiclient` - Google Sheets integration
- `tracy/tracy` - Debugging
- `phpunit/phpunit` - Testing

## Configuration Files
- `composer.json` - Dependencies and autoloading
- `docker-compose.yml` - Development environment
- `phpunit.xml.dist` - Test configuration
- `nastaveni/` - Environment-specific settings

## Current Development Context
- **Active Branch**: User merging functionality
- **Recent Changes**: Database settings, user management, test infrastructure
- **Key Files Modified**: User models, database wrappers

## Czech Localization
- Interface in Czech language
- Czech banking integration (QR payments)
- Timezone: Europe/Prague
- Currency handling for Czech crowns

## Testing Notes
- Tests use temporary database setup
- Database migrations run automatically
- Test data in `/tests/Db/data/`
- Bootstrap: `tests/_zavadec.php`
- **Test logs location**: `logy/tests/{PID}` (process-specific)
  - Each test run creates a separate log directory based on process ID
  - FIO payment cache files stored in `logy/tests/{PID}/fio/`
  - SQLite logs in `logy/tests/{PID}/platby.sqlite`
  - LOGY constant in tests: `/var/www/html/gamecon/logy/tests/{PID}` (inside Docker)
  - These are NOT in `/tmp` - they're in the project's `logy/tests/` directory

## Temporary Scripts for Research/Debugging
- When running multi-step research or debugging inside Docker (grepping vendor files, reading multiple files, testing PHP snippets, etc.), use the `Write` tool to create a temporary script in `symfony/var/`, then execute it with `./bin-docker/php symfony/var/script.php` (or `bash symfony/var/script.sh`). Delete the script after use.
- Do NOT use inline PHP (`./bin-docker/php -r "..."`) or heredocs — those trigger permission prompts.

## Best Practices
- Follow existing code conventions
- Use strict typing (`declare(strict_types=1)`)
- Run tests before committing
- Check existing patterns in similar components
- Use Docker for consistent development environment
- **Directory creation**: Use Symfony's `(new Filesystem)->mkdir($dir, 0775)` instead of `@mkdir()` or `is_dir()` + `mkdir()` checks
- **Hashing**: Always use the complete result of a hashing function — never truncate it (e.g. `substr(md5(...), 0, 12)`) as this increases collision risk
- **Cache directories**: Use `SPEC` constant for private cache files and `CACHE` constant for public cache files (web-accessible)

## Doctrine Entity Guidelines

### Timestamp Columns
- **New timestamp columns** (created_at, updated_at, etc.) should ALWAYS use `DateTimeImmutable`
- **Legacy timestamp columns** may use `DateTime` for backward compatibility, but new code should prefer `DateTimeImmutable`
- **Rationale**: `DateTimeImmutable` prevents accidental mutations and is safer for value objects

**Example:**
```php
// ✅ GOOD: New timestamp columns
#[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: false)]
private \DateTimeImmutable $createdAt;

#[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
private ?\DateTimeImmutable $updatedAt = null;

public function __construct()
{
    $this->createdAt = new \DateTimeImmutable();
}

// ❌ BAD: Using mutable DateTime for new columns
#[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
private \DateTime $createdAt;
```

### Timestamp Injection (Testability)
- **Entities MUST NOT create timestamps internally** in business logic methods (except constructors for auto-set fields like `created_at`)
- **Always accept timestamps as parameters** from outside (e.g., from Clock service, application service, or controller)
- **Rationale**: Internal timestamp creation (`new \DateTimeImmutable()`) makes testing difficult because you can't control the time
- **Exception**: Constructors MAY auto-set `created_at` timestamps since they represent object creation time

**Examples:**
```php
// ✅ GOOD: Accept timestamp from outside
public function archive(\DateTimeImmutable $archivedAt): self
{
    $this->archivedAt = $archivedAt;
    return $this;
}

// Usage with Clock service
$product->archive($clock->now());

// Testing is easy - inject any timestamp
$product->archive(new \DateTimeImmutable('2024-01-15 10:00:00'));

// ❌ BAD: Creating timestamp inside entity method
public function archive(): self
{
    $this->archivedAt = new \DateTimeImmutable(); // Hard to test!
    return $this;
}

// ✅ GOOD: Constructor auto-set for creation timestamp
public function __construct()
{
    $this->createdAt = new \DateTimeImmutable(); // OK - represents object creation
}
```

**Clock Service Pattern:**
Use Symfony's Clock component for timestamp generation in services:
```php
use Symfony\Component\Clock\ClockInterface;

class ProductService
{
    public function __construct(
        private ClockInterface $clock,
    ) {}

    public function archiveProduct(Product $product): void
    {
        $product->archive($this->clock->now());
        // ... persist
    }
}
```

## SQL Coding Style

### Table Naming Convention
- **New tables use SINGULAR names**: `product_tag`, `product_discount`, `shop_order` (NOT plurals)
- **Legacy tables may use plural/Czech names**: `shop_predmety`, `uzivatele_hodnoty`, `akce_seznam` (keep as-is for backward compatibility)
- **Rationale**: Singular names are clearer, match entity class names better, and avoid confusion about what "one row" represents
- **Examples**:
  ```sql
  -- ✅ GOOD: New tables with singular English names
  CREATE TABLE product_tag (...)
  CREATE TABLE product_discount (...)
  CREATE TABLE shop_order (...)

  -- ❌ BAD: New tables with plural English names
  CREATE TABLE product_tags (...)      -- NO
  CREATE TABLE product_discount (...) -- NO
  CREATE TABLE shop_order (...)       -- NO

  -- ✅ ACCEPTABLE: Legacy tables (don't rename)
  shop_predmety, uzivatele_hodnoty, akce_seznam
  ```

### Query Style
- **No table aliases**: Use full table names in queries whenever possible
- **No single-letter aliases**: Avoid cryptic aliases like `t`, `n`, `a`
- **Descriptive names**: If aliases are necessary, use descriptive human-readable names
- **Example**:
  ```sql
  -- ❌ BAD: Single-letter aliases
  UPDATE `novinky` n LEFT JOIN `texty` t ON t.`id` = n.`text` SET n.`text_md` = t.`text`;

  -- ✅ GOOD: Full table names or descriptive aliases
  UPDATE `novinky` LEFT JOIN `texty` ON texty.`id` = novinky.`text` SET novinky.`text_md` = texty.`text`;
  ```

## SQL Query Parameter Preprocessing

The GameCon project has a custom SQL preprocessing system (see `model/funkce/fw-database.php`):

**Parameter Placeholders:**
- `$0, $1, $2...` - Indexed parameters in queries
- `?` - Sequential placeholders (can be mixed with `$N`)
- Parameters passed as second argument to `dbQuery()`

**Automatic Array Handling:**
- When an array is passed as a parameter, `dbQv()` automatically calls `dbQa()`
- `dbQa()` handles empty arrays by returning `'NULL'` (line 929-930)
- Example: `dbQuery("SELECT * FROM table WHERE id NOT IN ($1)", [1 => []])` becomes `... NOT IN (NULL)`
- This means you **don't need** to manually check for empty arrays before using `IN` or `NOT IN` clauses

**Example:**
```php
// This works correctly even when $ids is an empty array
dbQuery("DELETE FROM table WHERE id NOT IN ($1)", [1 => $ids]);
// Empty array → NOT IN (NULL) → matches nothing (correct behavior)
```

**When to Use Explicit Checks:**
- For code clarity and explicit intent
- To avoid different SQL execution paths
- To optimize query execution (avoiding query with NULL when you can skip it entirely)
