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
across both `migrace/` and `symfony/migrations/*/`. It takes no backup of its own (the deploy does), so dump the database first.

Never use `migrations:reset` on a database you care about — it clobbers the migration
bookkeeping of the other runner.

## Which migrations belong to the e-shop

Nine files in `symfony/migrations/structures/`, dated so that they sort **after** every migration that was on
`main` when the e-shop was merged. Each is named for the end state it produces: no interim state exists, so no
compatibility view, no column that is added and dropped again, and no rewrite of a quick report that a later
migration rewrites once more. Earlier drafts (`2026-01-31-new-eshop-NN-*.sql`, `2026-09-02-1000NN_*`) are gone.

| File (prefix `2026-11-07-`) | What it does |
|---|---|
| `100001_doctrine-schema-alignment.php` | Not the shop: Doctrine index names, newsletter tables, singular Google table names, activity locations as a plain many-to-many |
| `100002_eshop-tables.sql` | The new tables and columns in their final shape |
| `100003_discount-rules.php` | Discount rules and the settings holding their counts |
| `100004_product-catalog.php` | Tags from `typ` and `podtyp`, `archived_at` from `model_rok`, `breakfast_included`, unique `kod_predmetu` |
| `100005_product-variants.php` | A variant for every row; sizes and nights regrouped under one product; room-type products |
| `100006_purchases-on-variants.php` | Orders, purchase snapshots, variants for purchases, cancelled purchases and desk grid cells |
| `100007_anonymous-buyer.php` | The `ANONYM` account (id 0), the history moved to it, payments linked to their orders |
| `100008_quick-reports.php` | The five stored quick reports rewritten to the new tables |
| `100009_legacy-catalog-removed.php` | Drops `id_predmetu` from purchases, the rows sizes and nights were made from, and the legacy columns |

## No compatibility view

Legacy queries read `shop_predmety`, `product_variant` and the tags directly and derive `typ`, `podtyp` and
`model_rok` themselves; see `docs/generated/nakup-produkt-a-varianta.md`. An earlier draft kept the legacy
columns alive through a view (`shop_predmety_s_typem`, and `shop_varianty_s_typem` per variant) until the
readers were converted. With the migrations replayed as one end state it is never created.

## Verifying afterwards

```bash
./bin-docker/php ./bin/console dbal:run-sql "SELECT product_tag.code, COUNT(*) FROM product_product_tag INNER JOIN product_tag ON product_tag.id = product_product_tag.tag_id GROUP BY product_tag.code"
./bin-docker/php ./bin/console dbal:run-sql "SELECT COUNT(*) FROM shop_predmety WHERE id_predmetu NOT IN (SELECT product_id FROM product_product_tag)"
./bin-docker/php ./bin/console dbal:run-sql "SELECT SUM(breakfast_included) AS hotel FROM shop_predmety"
```

Expect every product to carry a category tag (the second query returns 0), `mikina` among the tags
in the first and hotel rooms in the third if the source data had them. Verified against a production dump
from 2026-09-01: 1135 products, all tagged, 25 hotel rooms and 7 hoodies preserved.

## Rollback

The deploy writes a pre-migration dump before each migration; restore that (`migrations:continue` writes none, so dump first). The column drops
are destructive, so a database that has run `100009` cannot be walked back statement by
statement.
