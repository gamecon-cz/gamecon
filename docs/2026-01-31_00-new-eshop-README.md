# New E-shop Migration Guide

## Overview

The new e-shop design removes `model_rok` and `typ` from `shop_predmety` and introduces:

- flexible tags (replacing the fixed `typ` field 1–7)
- product variants and bundles (forced bundling for accommodation)
- role-based discounts (database-driven instead of hardcoded)
- order-item snapshots (price freezing — "zamrazení ceny")

## How to run it

The migrations are ordinary project migrations, applied by the normal runner. There is no
manual per-file procedure and no need to pipe SQL by hand:

```bash
./bin-docker/php ./bin/console migrations:continue
```

The runner applies everything not yet recorded in the `migrations` table, in filename order
across both `migrace/` and `symfony/migrations/*/`, and takes its own pre-migration backup.

Never use `migrations:reset` on a database you care about — it clobbers the migration
bookkeeping of the other runner.

## Which migrations belong to the e-shop

Filenames are dated so they sort **after** the migrations that landed on `main` during the
2026 festival; they were renamed for that reason during the September 2026 rebase, so any
older document referring to `2026-01-31-new-eshop-NN-*.sql` names is out of date.

| File | What it does |
|---|---|
| `migrace/2026-09-02-100000_convert-utf8mb3-to-utf8mb4.php` | Converts remaining utf8mb3 tables |
| `symfony/migrations/structures/2026-09-02-100001_new-eshop.php` | Creates the new tables, migrates `typ` → tags, archives past years, drops `model_rok`/`typ`/`je_letosni_hlavni`, creates the compatibility view |
| `symfony/migrations/structures/2026-09-02-100002_singleton-in-table-name.php` | Table naming cleanup |
| `symfony/migrations/structures/2026-09-02-100003_activity-locations-to-many-to-many.php` | Activity locations to many-to-many |
| `migrace/2026-09-02-100004_group-historical-order-items.php` | Groups historical purchases into orders |
| `symfony/migrations/structures/2026-09-02-100005_podtyp-to-hotel-tag.php` | `podtyp='hotel'` → `breakfast_included`, `podtyp='mikina'` → `mikina` tag, then drops `podtyp` |
| `symfony/migrations/structures/2026-09-02-100006_remaining-quantity.sql` | Remaining-quantity handling |
| `symfony/migrations/structures/2026-09-02-100007_product-variant.sql` | Product variants |
| `symfony/migrations/structures/2026-09-02-100008_bundle-variants.sql` | Bundle variants |
| `symfony/migrations/structures/2026-09-02-100009_mikina-product-tag.sql` | Ensures the `mikina` tag and its view translation |

## The compatibility view

`shop_predmety_s_typem` is what keeps the legacy code working after the columns are dropped.
It re-derives the old columns from the new model:

- `typ` — from the category tag (`predmet`, `ubytovani`, `tricko`, `jidlo`, `vstupne`, `parcon`, `proplaceni-bonusu`)
- `podtyp` — `'hotel'` from `breakfast_included`, `'mikina'` from the `mikina` tag
- `model_rok` — current `ROCNIK` when `archived_at IS NULL`, otherwise the archived year
- `je_letosni_hlavni` — whether the product is unarchived

**Any legacy query reading `typ`, `podtyp`, `model_rok` or `je_letosni_hlavni` must select from
the view, not from `shop_predmety`.** Writes still go to the base table.

## Verifying afterwards

```bash
./bin-docker/php ./bin/console dbal:run-sql "SELECT typ, COUNT(*) FROM shop_predmety_s_typem GROUP BY typ"
./bin-docker/php ./bin/console dbal:run-sql "SELECT COUNT(*) FROM shop_predmety WHERE id_predmetu NOT IN (SELECT product_id FROM product_product_tag)"
./bin-docker/php ./bin/console dbal:run-sql "SELECT podtyp, COUNT(*) FROM shop_predmety_s_typem WHERE podtyp IS NOT NULL GROUP BY podtyp"
```

Expect every product to carry a category tag (the second query returns 0), and both `hotel` and
`mikina` to appear in the third if the source data had them. Verified against a production dump
from 2026-09-01: 1135 products, all tagged, 25 hotel rooms and 7 hoodies preserved.

## Rollback

The runner writes a pre-migration dump before each migration; restore that. The column drops
are destructive, so a database that has run `100001` cannot be walked back statement by
statement.
