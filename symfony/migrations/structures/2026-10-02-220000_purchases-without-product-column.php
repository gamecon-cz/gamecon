<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A purchase's product is its variant's product; the id_predmetu copy goes. Nothing in the
// database keeps the two equal, so stop before dropping anything if they ever drifted.
// A table that already lost the column (a re-run after a failure) is left alone.
$tabulky = $this->q(<<<'SQL'
    SELECT table_name
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name IN ('shop_nakupy', 'shop_nakupy_zrusene')
      AND column_name = 'id_predmetu'
    SQL)->fetchAll(\PDO::FETCH_COLUMN);
foreach ($tabulky as $tabulka) {
    $rozdilnych = (int) $this->q(<<<SQL
        SELECT COUNT(*)
        FROM {$tabulka}
        INNER JOIN product_variant ON product_variant.id = {$tabulka}.variant_id
        WHERE {$tabulka}.id_predmetu <> product_variant.product_id
        SQL)->fetchColumn();
    if ($rozdilnych > 0) {
        throw new \RuntimeException("{$rozdilnych} řádků {$tabulka} má v id_predmetu jiný produkt, než ke kterému patří jejich varianta — sjednoť je a migraci spusť znovu.");
    }
}

// Quick reports keep their SQL in the database; the one that shows the column moves to the variant.
$puvodniNakupy = <<<'SQL'
select shop_nakupy.id_uzivatele, shop_nakupy.id_predmetu AS product_id, shop_nakupy.product_name AS nazev, shop_nakupy.variant_name AS varianta,
       shop_nakupy.rok, shop_nakupy.cena_nakupni, shop_nakupy.datum
from shop_nakupy
SQL;
$noveNakupy = <<<'SQL'
select shop_nakupy.id_uzivatele, product_variant.product_id AS product_id, shop_nakupy.product_name AS nazev, shop_nakupy.variant_name AS varianta,
       shop_nakupy.rok, shop_nakupy.cena_nakupni, shop_nakupy.datum
from shop_nakupy
inner join product_variant ON product_variant.id = shop_nakupy.variant_id
order by shop_nakupy.id_nakupu
SQL;
$neprepsane = [];
foreach ($this->q("SELECT id, nazev, dotaz FROM reporty_quick WHERE dotaz LIKE '%id\\_predmetu%'")->fetchAll(\PDO::FETCH_ASSOC) as $report) {
    // A report saved through the admin form may end its lines with CRLF.
    $dotaz = rtrim(str_replace("\r", '', (string) $report['dotaz']));
    if ($dotaz === $puvodniNakupy) {
        $this->q('UPDATE reporty_quick SET dotaz = ' . $this->connection->quote($noveNakupy) . ' WHERE id = ' . (int) $report['id']);
    } elseif (preg_match('/\bshop_nakupy(_zrusene)?\.id_predmetu\b/i', $dotaz) === 1) {
        $neprepsane[] = "{$report['id']} ({$report['nazev']})";
    }
}
// Dropping the column would leave such a report failing on every run, unnoticed until someone opens it.
if ($neprepsane !== []) {
    throw new \RuntimeException('Quick reporty ' . implode(', ', $neprepsane) . ' čtou id_predmetu nákupu a migrace je neumí přepsat — přepiš je přes variantu a migraci spusť znovu.');
}

foreach ($tabulky as $tabulka) {
    $cizichKlicu = $this->q(<<<SQL
        SELECT constraint_name
        FROM information_schema.key_column_usage
        WHERE table_schema = DATABASE()
          AND table_name = '{$tabulka}'
          AND column_name = 'id_predmetu'
          AND referenced_table_name IS NOT NULL
        SQL)->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($cizichKlicu as $ciziKlic) {
        $this->q("ALTER TABLE {$tabulka} DROP FOREIGN KEY `{$ciziKlic}`");
    }
    $indexu = $this->q(<<<SQL
        SELECT DISTINCT index_name
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = '{$tabulka}'
          AND column_name = 'id_predmetu'
        SQL)->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($indexu as $index) {
        $this->q("ALTER TABLE {$tabulka} DROP INDEX `{$index}`");
    }
    $this->q("ALTER TABLE {$tabulka} DROP COLUMN id_predmetu");
}

// The pattern above misses aliased or unqualified references, so ask the database: a report that
// still names the dropped column fails here rather than on its next run. Only what the report
// runner itself would execute is explained; a report already failing for another reason hides it.
$rozbite = [];
foreach ($this->q("SELECT id, nazev, dotaz FROM reporty_quick WHERE dotaz LIKE '%id\\_predmetu%'")->fetchAll(\PDO::FETCH_ASSOC) as $report) {
    $dotaz = str_ireplace(['{ROK}', '{ROCNIK}'], (string) date('Y'), rtrim((string) $report['dotaz'], " \t\r\n;"));
    try {
        (new \Gamecon\Report\QuickReportSqlGuard())->assertSingleReadStatement($dotaz);
    } catch (\Gamecon\Report\Exceptions\QuickReportSqlNotAllowed) {
        continue;
    }
    try {
        $this->q('EXPLAIN ' . $dotaz);
    } catch (\PDOException $chyba) {
        if ((int) ($chyba->errorInfo[1] ?? 0) === 1054 && str_contains($chyba->getMessage(), 'id_predmetu')) {
            $rozbite[] = "{$report['id']} ({$report['nazev']})";
        }
    }
}
if ($rozbite !== []) {
    throw new \RuntimeException('Quick reporty ' . implode(', ', $rozbite) . ' čtou id_predmetu nákupu, který už neexistuje — přepiš je přes variantu a migraci spusť znovu.');
}
