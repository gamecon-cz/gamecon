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

# Database access (use dbal:run-sql to ensure correct DB)
./bin-docker/php ./bin/console dbal:run-sql 'SELECT 1'  # Execute SQL query in current DB

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

### Testing Doctrine repositories: beware the two-connection deadlock

`AbstractTestDb` wraps each test in a transaction on the **legacy `dbQuery`/`mysqli` connection** (via `keepTestClassDbChangesInTransaction` / `keepSingleTestMethodDbChangesInTransaction`, which default to `true`). A Doctrine repository/EntityManager uses a **separate Doctrine DBAL connection**. The two connections do not share a transaction.

If a test inserts fixture rows with legacy `dbQuery(...)` (uncommitted, inside the legacy transaction) and then the repository under test runs a Doctrine query that reads or writes those same rows, the Doctrine connection blocks on the legacy connection's row locks → `SQLSTATE[HY000]: 1205 Lock wait timeout exceeded`. This is **not** a bug in the repository — it's two connections fighting over uncommitted rows.

**Fix:** when testing a Doctrine repository, route **all** fixtures and assertions through the **Doctrine connection** so everything shares one connection, and opt out of the legacy transaction wrapping:

```php
class UserRoleRepositoryTest extends AbstractTestDb
{
    // Legacy per-test transaction wrapping would isolate fixtures from the
    // Doctrine connection. Reset the DB after the class instead.
    protected static function keepTestClassDbChangesInTransaction(): bool { return false; }
    protected static function keepSingleTestMethodDbChangesInTransaction(): bool { return false; }
    protected static function resetDbAfterClass(): bool { return true; }

    private function connection(): \Doctrine\DBAL\Connection
    {
        return $this->getContainer()->get('doctrine.orm.entity_manager')->getConnection();
    }
    // ... use $this->connection()->executeStatement(...) / ->fetchOne(...) for fixtures + asserts
}
```

Get the custom repository via `->getRepository(SomeEntity::class)` (returns the project's `ServiceEntityRepository`; repos aren't public services by default, so don't `->get()` them by class). Don't mix legacy `dbQuery` and Doctrine writes in the same test against the same tables.

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

## Git Branch Naming from Trello Cards

When given a Trello card URL, the branch name is exactly the slug
that already lives in the URL after the card ID — **no extra
`trello-` prefix**.

Trello URLs look like:

```
https://trello.com/c/<short-id>/<card-id>-<slug>
                                  └────┬─────┘
                                       │
                                       └─ this whole thing IS the branch name
```

Examples:

| Trello URL                                                    | Branch name                |
| ------------------------------------------------------------- | -------------------------- |
| `https://trello.com/c/1VPhrSgW/1444-previews-list`            | `1444-previews-list`       |
| `https://trello.com/c/abc12345/1500-fix-login-redirect-loop`  | `1500-fix-login-redirect-loop` |

Create the branch from updated `origin/main`:

```bash
git fetch origin main
git switch -c 1444-previews-list origin/main
```

**Do not** prepend `trello-`, the project initials, or anything else.
The numeric card ID at the start is enough disambiguation and the
slug already describes the task.

This overrides the global YouTrack-style naming rule from
`~/.claude/CLAUDE.md` — that one targets a different tracker (YouTrack
ticket IDs like `PCA-682` aren't unique without the project prefix;
Trello card numbers are).

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

## Generated Concept Docs (`docs/generated/`)

Vstupní bod pro Claude nad většími koncepty (featury, architektura, cross-cutting chování). Cíl: místo opakovaného prohledávání kódu jít nejdřív sem.

**Před prací nad pojmenovaným konceptem** (aktivity, týmy, přihlášky, platby, role, instance, online prezence, import/export, …): přečti `docs/generated/README.md` (index jednořádkových popisů). Pokud existuje relevantní dokument, **začni tam** a neopakuj průzkum kódu.

**Vytvoř / rozšiř dokument**, když během úkolu:
- zjistíš netriviální pravidlo nebo záměr **nederivovatelný z kódu** (typicky z promptu uživatele)
- narazíš na gotcha, kterou by budoucí Claude řešil znovu
- musíš projít **víc než pár souborů**, abys pochopil koncept

Formát, povinné minimum a pravidla údržby: **viz `docs/generated/README.md`**. Po vytvoření / přejmenování dokumentu aktualizuj tam index. Nevytvářej dokumenty pro drobnosti, jednorázové fixy ani věci zřejmé z kódu nebo již pokryté v `CLAUDE.md` / `docs/`.

**Ochrana proti driftu:** dokumenty jsou hypotéza, ne pravda — před rozhodnutím podle dokumentu zlehka ověř klíčová tvrzení proti kódu. Při nálezu driftu prověř celý dokument, nejen dotčenou větu.

## Preview / Archive Environments Must Mirror Ostra

Preview prostředí (`<slug>.preview.gamecon.cz`) a archivní ročníky (`NNNN.gamecon.cz`) **musí vypadat a chovat se PŘESNĚ jako ostra**. Jejich účel je dvojí:
1. **Preview** = sandbox pro feature větev, kde tester / autor ticketu / produkťák ověří, že se feature chová stejně jako bude na ostré. Pokud se preview chová jinak, ztrácí cenu.
2. **Archive** = zmrazený stav konkrétního ročníku, ale stále s živým adminem (rozcestník, dev nástroje, ...) — admin sám není zmrazený, mění se spolu s `main`.

**Praktický důsledek pro každou novou produkční feature, která čte runtime nastavení (env var, konstantu, secret):**

Když přidáváš novou feature, která na ostré čte z `getenv()` / `define()` / `secrets.*`, musíš zařídit, aby **stejná hodnota dorazila i do preview a archive image**. Jinak se feature v preview/archive bude chovat jinak než na ostré (typicky tichý fallback na prázdný string), a chyba se nezjistí dokud někdo nezahlásí "v preview to nefunguje".

Plný path env var → PHP konstanta na všech třech místech:

| Vrstva | Ostra | Preview | Archive |
|--------|-------|---------|---------|
| Workflow s `secrets.*` | `.github/workflows/deploy-ostra.yml` (a `deploy-beta.yml`) — `env:` na deploy stepu | `.github/workflows/deploy-preview.yml` — `build-args:` na `docker/build-push-action` | `.github/workflows/deploy-year-archive.yml` — `build-args:` na `docker/build-push-action` |
| Doručení do runtime | `nasad.php` propaguje env → `vytvorSouborSkrytehoNastaveniPodleEnv()` zapeče do `nastaveni-<env>.php` při prvním bootu | `Dockerfile.preview` — `ARG NAME=""` + `ENV NAME=$NAME` (zapečené do image) | `Dockerfile.archive` — `ARG NAME=""` + `ENV NAME=$NAME` (zapečené do image) |
| Čtení v PHP | `vytvorSouborSkrytehoNastaveniPodleEnv()` v `model/funkce/skryte-nastaveni-z-env-funkce.php` | totéž (preview i archive používají stejný `verejne-nastaveni-*.php` → stejnou generator funkci) | totéž |
| Default pro lokální vývoj | `nastaveni/nastaveni-local-default.php` — `define(... getenv(...) ?: '')` | n/a (preview neběží lokálně) | n/a |

**Checklist před commitem nové produkční feature čtoucí env var / secret:**

- [ ] Přidat `getenv()` + heredoc `define()` do `model/funkce/skryte-nastaveni-z-env-funkce.php`
- [ ] Přidat default do `nastaveni/nastaveni-local-default.php` (typicky `getenv(...) ?: ''`)
- [ ] Přidat `secrets.*` do `.github/workflows/deploy-ostra.yml` a `deploy-beta.yml` (env: block)
- [ ] Přidat `secrets.*` do `.github/workflows/deploy-preview.yml` (build-args:)
- [ ] Přidat `secrets.*` do `.github/workflows/deploy-year-archive.yml` (build-args:)
- [ ] Přidat `ARG` + `ENV` do `Dockerfile.preview`
- [ ] Přidat `ARG` + `ENV` do `Dockerfile.archive`

**Tajemství v image — bezpečnost:** GHCR repo `gamecon-cz/gamecon` je privátní, image layery nejsou veřejně čitelné. Citlivá produkční tajemství (DB hesla, FIO token, APP_SECRET) ale v preview/archive image **nemají co dělat** — preview/archive se připojují k vlastním (oddělených) DB a externím službám. Bake do image dělej **jen pro tajemství, která jsou společná napříč prostředími** (basic-auth k Caddy bráně před preview/archive — to je řízení přístupu k infrastruktuře, ne k datům).

### Dvě cesty doručení env var do preview / archive

Existují **dvě nezávislé cesty**, jak se env var dostane do běžícího preview / archive kontejneru:

1. **Build-time bake přes Dockerfile** (tento repo) — `ARG` + `ENV` v `Dockerfile.preview` / `Dockerfile.archive`, hodnota přijde z `build-args:` v GitHub Actions workflow. Vhodné pro hodnoty **společné všem preview / všem archivům** (typicky basic-auth k bráně).
2. **Runtime injection přes `docker run -e`** (ansible repo, `roles/preview_deployer/templates/deploy-preview-branch.sh.j2` a `roles/year_archive_deployer/files/deploy-year-archive.sh`). Vhodné pro hodnoty **per-deployment** (DB jméno odvozené ze slugu) nebo **per-prostředí, ale ne v image** (FIO token, kryptografické klíče — citlivé, nechceš je v image layerech). Preview deploy skript je Jinja template — tajemství pro `-e` se rendrují z `secrets.yaml` (SOPS) při `make deploy`; finální soubor na hostu je `0700`. Archive deploy zatím tajemství z vaultu nepotřebuje (statický `files/...sh`).

**Pravidlo:** preview se musí chovat jako ostra. **Cokoli, co ostra v `deploy-ostra.yml` `env:` bloku předává a co se reálně čte v runtime kódu, musí dorazit i do preview kontejneru jednou z těch dvou cest.** Default = stejná hodnota jako ostra; výjimky dokumentuj v tabulce níže.

### Audit červen 2026: konkrétní env vars předávané ostre vs. doručené do preview

Pravdivý stav ke dni auditu (zdroj: `cat /usr/local/sbin/deploy-preview-branch.sh` na `gamecon.cz` — soubor je deploynutý z ansible repo, role `preview_deployer`).

| Env var | Cesta v preview | Hodnota v preview | Pozn. |
|---------|-----------------|-------------------|-------|
| `DB_SERV`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` | `docker run -e` | per-slug (DB `gc_preview_<slug>`, user stejně, heslo = HMAC slugu) | správně |
| `DBM_USER`, `DBM_PASS` | `docker run -e` | totožné s `DB_USER`/`DB_PASS` (preview nepotřebuje oddělený migration user) | správně |
| `MIGRACE_HESLO` | `docker run -e` | per-slug HMAC | správně |
| `APP_ENV`, `APP_DEBUG`, `APP_SECRET` | `docker run -e` | `prod` / prázdné / per-slug HMAC | správně |
| `MAILER_DSN` | `docker run -e` | `smtp://172.17.0.1:1025` (sdílený Mailpit, `webmail.preview.gamecon.cz`) | **úmyslně jiné než ostra** — nechceme posílat z preview reálné maily |
| `FIO_TOKEN` | `docker run -e` | **stejné jako ostra** | chceme testovat stahování plateb i z preview |
| `SECRET_CRYPTO_KEY` | `docker run -e` | **stejné jako ostra** | šifruje osobní data (číslo OP) — preview musí umět rozšifrovat, co bylo zašifrováno na ostré (restored ostra dump) |
| `DB_ANONYM_SERV/USER/PASS/NAME` | `docker run -e` | **stejné jako ostra** (sdílená anonymní DB) | beta i ostra ji sdílí (workflow používá `secrets.DB_ANONYM_*`, ne `OSTRA_DB_ANONYM_*`) |
| `CRON_KEY` | `docker run -e` | per-slug HMAC | per-preview izolace; reálné crony nikdo zvenku nespouští, ale endpoint nesmí být `403` |
| `SERVER_NAME` | `docker run -e` | `<slug>.preview.gamecon.cz` | konzistentní s tím, co by Apache nastavil z requestu |
| `GOOGLE_API_CREDENTIALS` | (nevyplněno) | prázdné | úmyslné — vyřešíme později, většina toků v preview netřeba |
| `PREVIEW_BASIC_AUTH_USER/PASSWORD` | Dockerfile bake | sdílené napříč všemi preview | bake je správná cesta — společná hodnota |
| `ARCHIVE_BASIC_AUTH_USER/PASSWORD` | Dockerfile bake | sdílené napříč všemi archive | bake je správná cesta |
| `FTP_*` | nepředáváno | n/a | deploy-only, runtime kód nečte — správně, že to v preview není |

**Když přidáváš novou env-driven feature, vrať se k téhle tabulce a doplň řádek.** Pokud rozhodneš, že preview má dostat něco jiného než ostra, **napiš proč** — jinak se z toho stane skrytý drift.


