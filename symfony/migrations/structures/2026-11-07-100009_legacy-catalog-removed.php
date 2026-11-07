<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Everything that moved to products, variants and tags is removed from the old catalog: the purchases' copy of
// the catalog row id, the rows sizes and nights were made from, and the columns tags, archived_at and the
// variant's capacity replaced. An ALTER commits what came before it, so what can be checked before the first
// one is; what needs the columns gone (the quick reports) comes last, and every ALTER can be repeated.

// ── Checks, before anything is written ───────────────────────────────────────────────────────────────────────
//
// A catalog row whose code is a variant code, but whose variant belongs to another product, is only a leftover.
$this->q('DROP TEMPORARY TABLE IF EXISTS tmp_leftover_rows');
$this->q(<<<'SQL'
CREATE TEMPORARY TABLE tmp_leftover_rows AS
SELECT catalog_row.id_predmetu
FROM shop_predmety AS catalog_row
    INNER JOIN product_variant ON product_variant.code = catalog_row.kod_predmetu
WHERE product_variant.product_id <> catalog_row.id_predmetu
SQL);

// Only the tags point at these rows; anything else would lose what it points at.
foreach ([
    'product_discount' => 'product_id',
    'product_variant'  => 'product_id',
] as $table => $column) {
    $references = (int) $this->q("SELECT COUNT(*) FROM {$table} WHERE {$column} IN (SELECT id_predmetu FROM tmp_leftover_rows)")->fetchColumn();
    if ($references > 0) {
        throw new RuntimeException("{$references} rows of {$table}.{$column} point at a size or night row that is about to be removed; move them to the product and run the migration again.");
    }
}
$gridCells = (int) $this->q('SELECT COUNT(*) FROM obchod_bunky WHERE typ = 0 AND cil_id IN (SELECT id_predmetu FROM tmp_leftover_rows)')->fetchColumn();
if ($gridCells > 0) {
    throw new RuntimeException("{$gridCells} desk sale cells point at a size or night row that is about to be removed; move them to the product and run the migration again.");
}

// A variant takes its price, offer date, flags, archive date and description from its product, so a row that
// differs in any of them would lose its own value here.
$differing = $this->q(<<<'SQL'
SELECT leftover.kod_predmetu
FROM shop_predmety AS leftover
    INNER JOIN tmp_leftover_rows ON tmp_leftover_rows.id_predmetu = leftover.id_predmetu
    INNER JOIN product_variant ON product_variant.code = leftover.kod_predmetu
    INNER JOIN shop_predmety AS owner ON owner.id_predmetu = product_variant.product_id
WHERE NOT (leftover.cena_aktualni <=> owner.cena_aktualni)
   OR NOT (leftover.nabizet_do <=> owner.nabizet_do)
   OR NOT (leftover.vedlejsi <=> owner.vedlejsi)
   OR NOT (leftover.popis <=> owner.popis)
   OR NOT (leftover.reserved_for_organizers <=> owner.reserved_for_organizers)
   OR NOT (leftover.breakfast_included <=> owner.breakfast_included)
   OR NOT (leftover.archived_at <=> owner.archived_at)
ORDER BY leftover.kod_predmetu
SQL)->fetchAll(PDO::FETCH_COLUMN);
if ($differing !== []) {
    throw new RuntimeException('These size or night rows differ from their product in price, offer date, flags, archive date or description, which a variant cannot keep: ' . implode(', ', $differing) . '. Make them equal and run the migration again.');
}

// The code of a size group's owner is the code of its first size, so the product would be named after one of
// its sizes. The size its own variant is named is cut out of the code; where that leaves a code that is taken,
// the product id is appended.
$owners = $this->q(<<<'SQL'
SELECT shop_predmety.id_predmetu,
       shop_predmety.kod_predmetu,
       own_variant.name AS size
FROM shop_predmety
    INNER JOIN product_variant AS own_variant ON own_variant.product_id = shop_predmety.id_predmetu
        AND own_variant.code = shop_predmety.kod_predmetu
WHERE (SELECT COUNT(*) FROM product_variant WHERE product_variant.product_id = shop_predmety.id_predmetu) > 1
SQL)->fetchAll(PDO::FETCH_ASSOC);

// The column is case-insensitive, so the codes are compared in lower case.
$takenCodes = array_flip(array_map('mb_strtolower', array_merge(
    $this->q('SELECT kod_predmetu FROM shop_predmety')->fetchAll(PDO::FETCH_COLUMN),
    $this->q('SELECT code FROM product_variant')->fetchAll(PDO::FETCH_COLUMN),
)));
$newCodes = [];
foreach ($owners as $owner) {
    $codeTokens = preg_split('~[_-]~', $owner['kod_predmetu']);
    $sizeTokens = preg_split('~[_\- ]~', mb_strtolower((string) $owner['size']));
    $positions = [];
    for ($position = 0; $position + count($sizeTokens) <= count($codeTokens); ++$position) {
        if (array_map('mb_strtolower', array_slice($codeTokens, $position, count($sizeTokens))) === $sizeTokens) {
            $positions[] = $position;
        }
    }
    if (count($positions) !== 1) {
        throw new RuntimeException(sprintf(
            'Code "%s" contains the size "%s" %d times, so the code of the product cannot be told from it; rename it by hand and run the migration again.',
            $owner['kod_predmetu'],
            $owner['size'],
            count($positions),
        ));
    }
    array_splice($codeTokens, $positions[0], count($sizeTokens));
    $newCode = implode('_', $codeTokens);
    if (isset($takenCodes[mb_strtolower($newCode)])) {
        $newCode .= '_' . $owner['id_predmetu'];
    }
    $takenCodes[mb_strtolower($newCode)] = true;
    $newCodes[(int) $owner['id_predmetu']] = $newCode;
}

// ── The purchase no longer points at a catalog row ───────────────────────────────────────────────────────────
//
// A purchase's product is its variant's product. The foreign key and its index go with the column.
foreach (['shop_nakupy', 'shop_nakupy_zrusene'] as $table) {
    $foreignKeys = $this->q(<<<SQL
SELECT constraint_name
FROM information_schema.key_column_usage
WHERE table_schema = DATABASE()
  AND table_name = '{$table}'
  AND column_name = 'id_predmetu'
  AND referenced_table_name IS NOT NULL
SQL)->fetchAll(PDO::FETCH_COLUMN);
    foreach ($foreignKeys as $foreignKey) {
        $this->q("ALTER TABLE {$table} DROP FOREIGN KEY `{$foreignKey}`");
    }

    $indexes = $this->q(<<<SQL
SELECT DISTINCT index_name
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = '{$table}'
  AND column_name = 'id_predmetu'
SQL)->fetchAll(PDO::FETCH_COLUMN);
    foreach ($indexes as $index) {
        $this->q("ALTER TABLE {$table} DROP INDEX `{$index}`");
    }

    $this->q("ALTER TABLE {$table} DROP COLUMN IF EXISTS id_predmetu");
}

// ── Rows that sizes and nights were made from ────────────────────────────────────────────────────────────────
foreach ($newCodes as $productId => $newCode) {
    $this->q('UPDATE shop_predmety SET kod_predmetu = ' . $this->connection->quote($newCode) . ' WHERE id_predmetu = ' . $productId);
}

$this->q('DELETE FROM product_product_tag WHERE product_id IN (SELECT id_predmetu FROM tmp_leftover_rows)');
$this->q('DELETE FROM shop_predmety WHERE id_predmetu IN (SELECT id_predmetu FROM tmp_leftover_rows)');
$this->q('DROP TEMPORARY TABLE tmp_leftover_rows');

// ── Columns that tags, archived_at and the variant replaced ──────────────────────────────────────────────────
$this->q(<<<'SQL'
ALTER TABLE shop_predmety
    DROP COLUMN IF EXISTS model_rok,
    DROP COLUMN IF EXISTS typ,
    DROP COLUMN IF EXISTS podtyp,
    DROP COLUMN IF EXISTS je_letosni_hlavni,
    DROP COLUMN IF EXISTS kusu_vyrobeno
SQL);

// ── Quick reports ────────────────────────────────────────────────────────────────────────────────────────────
// A report that still names something dropped would fail on every run, unnoticed until somebody opens it, so
// ask the database: it sees aliased and unqualified references that no text search would. Only a single read
// is explained, and only an error naming what was removed counts. Fix the report, then run the migration again.
$removedNames = '/id_predmetu|model_rok|je_letosni_hlavni|kusu_vyrobeno|podtyp|_s_typem|[.\']typ[\']/';
$brokenReports = [];
foreach ($this->q("SELECT id, nazev, dotaz FROM reporty_quick WHERE dotaz LIKE '%shop\\_%'")->fetchAll(PDO::FETCH_ASSOC) as $report) {
    $query = str_ireplace(['{ROK}', '{ROCNIK}'], (string) date('Y'), rtrim((string) $report['dotaz'], " \t\r\n;"));
    // This connection runs several statements, so only text that is certainly one read goes to EXPLAIN; the rest
    // is searched for the names that were removed, which finds more than it should rather than less.
    $withoutLeadingComments = preg_replace('~^(?:\s+|/\*.*?\*/|(?:--|\#)[^\n]*(?:\n|$))+~s', '', $query);
    if (
        preg_match('~^\(*\s*(SELECT|WITH)\b~i', (string) $withoutLeadingComments) !== 1
        || str_contains($query, ';')
        || preg_match('~\bINTO\s+(OUTFILE|DUMPFILE)\b~i', $query) === 1
    ) {
        if (preg_match('/model_rok|je_letosni_hlavni|kusu_vyrobeno|podtyp|_s_typem/', $query) === 1) {
            $brokenReports[] = "{$report['id']} ({$report['nazev']}): names a removed column and cannot be explained here";
        }
        continue;
    }
    try {
        $this->q('EXPLAIN ' . $query);
    } catch (PDOException $exception) {
        if (in_array((int) ($exception->errorInfo[1] ?? 0), [1054, 1146], true) && preg_match($removedNames, $exception->getMessage()) === 1) {
            $brokenReports[] = "{$report['id']} ({$report['nazev']}): {$exception->errorInfo[2]}";
        }
    }
}
if ($brokenReports !== []) {
    throw new RuntimeException('Quick reports read a column or table that no longer exists: ' . implode('; ', $brokenReports) . '. Rewrite them over products, variants and tags and run the migration again.');
}
