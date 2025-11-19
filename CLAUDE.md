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
/nastaveni/     - Configuration files
/vendor/        - Composer dependencies
```

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

# Database access
./bin-docker/mysql ostra              # Connect to 'ostra' database
./bin-docker/mysql ostra -e 'SQL'     # Execute SQL query

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

## Best Practices
- Follow existing code conventions
- Use strict typing (`declare(strict_types=1)`)
- Run tests before committing
- Check existing patterns in similar components
- Use Docker for consistent development environment

## SQL Coding Style
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