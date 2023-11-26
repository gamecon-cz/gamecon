<?php

// This is a fix of a 058.php where renamed category (styl Desk => styl Deskovky) in tags, but not in categories itself causes invalid pairing

/** @var \Godric\DbMigrations\Migration $this */

// CATEGORIES
$categories = require __DIR__ . '/pomocne/062/fetchCategories.php';

// SUB-CATEGORIES
$mainCategories = [];
$parentCategoryName = null;
$subCategories = [];
foreach ($categories as $category) {
  if ($category[0]) { // "Kategorie"
    $mainCategories[] = $category;
    $parentCategoryName = $category[0];
  } else {
    if (!$parentCategoryName) {
      throw new \LogicException(sprintf('Chybi hlavni kategorie pro %s', var_export($category, true)));
    }
    $subCategories[$parentCategoryName][] = $category;
  }
}

// TAGS

$tags = require __DIR__ . '/pomocne/062/fetchTags.php';

$checkNameUniqueness = require __DIR__ . '/pomocne/062/checkNameUniquenessFunction.php';

$checkNameUniqueness($tags);

// TAGS FOR SQL
$tagsForSql = array_map(
  function(array $row): array {
    if (!$row[1]) {
      $row[1] = null; // turn empty ID (empty string) into null to activate MySQL auto-increment
    }
    return $row;
  },
  $tags
);
// has to move tags without ID to end to avoid conflict of auto-generated ID with an existing ID, if auto-generated were inserted first
usort($tagsForSql, function(array $someRow, array $anotherRow) {
  $someId = $someRow[1];
  $anotherId = $anotherRow[1];
  if ($someId && $anotherId) {
    return $someId <=> $anotherId; // lower first
  }
  if ($someId) {
    return -1; // someId exists and goes first, anotherId is empty and goes last
  }
  if ($anotherId) {
    return 1; // someId is empty and goes last, anotherId exist and goes first
  }
  return strcmp($someRow[5], $anotherRow[5]); // both IDs are empty, just sort them alphabetically by name
});

$intoSqlValues = require __DIR__ . '/pomocne/062/intToSqlValuesFunction.php';
$fixedTagsSql = $intoSqlValues($tagsForSql, $this->connection);
$tagsAutoIncrementStart = 0;
foreach ($tags as $tag) {
  $tagsAutoIncrementStart = max($tagsAutoIncrementStart, (int)$tag[1] /* id */);
}
$tagsAutoIncrementStart++; // start after previous last ID to avoid (almost impossible) accidental usage of new record instead of old one
// CATEGORIES FOR SQL
$mainCategoriesForSql = array_map(
  static function(array $mainCategory): array {
    $mainCategory[1] = null; // no parent category
    return $mainCategory;
  },
  $mainCategories
);
$mainCategoriesSql = $intoSqlValues($mainCategoriesForSql, $this->connection);
$subCategoriesForSql = [];
foreach ($subCategories as $parentCategoryName => $subCategoriesWithSameParent) {
  $subCategoriesWithSameParentForSql = array_map(
    function(array $subCategory) use ($parentCategoryName): array {
      $subCategory[0] = $subCategory[1]; // sub-category name moved to first position
      $subCategory[1] = sprintf(<<<'SQL'
(SELECT id FROM kategorie_sjednocenych_tagu_62 AS parent_category WHERE nazev = '%s')
SQL
        ,
        mysqli_real_escape_string($this->connection, $parentCategoryName)
      ); // parent category ID
      return $subCategory;
    },
    $subCategoriesWithSameParent
  );
  foreach ($subCategoriesWithSameParentForSql as $subCategoryWithSameParentForSql) {
    $subCategoriesForSql[] = $subCategoryWithSameParentForSql;
  }
}
$subCategoriesSql = $intoSqlValues($subCategoriesForSql, $this->connection);
$queries = [];
//TODO REMOVE THIS development CLEANUP
$queries[] = <<<SQL
DROP TABLE IF EXISTS sjednocene_tagy_62;
DROP TABLE IF EXISTS kategorie_sjednocenych_tagu_62;
SQL;

$queries[] = <<<SQL
CREATE TEMPORARY TABLE sjednocene_tagy_temp LIKE tagy;
ALTER TABLE sjednocene_tagy_temp ADD COLUMN nazev_kategorie VARCHAR(128), ADD COLUMN opraveny_nazev VARCHAR(128), ADD COLUMN poznamka TEXT;
INSERT INTO sjednocene_tagy_temp(id, nazev, nazev_kategorie, opraveny_nazev, poznamka) VALUES {$fixedTagsSql};
ALTER TABLE sjednocene_tagy_temp ADD INDEX (nazev_kategorie), ADD INDEX (opraveny_nazev);
SQL;
$queries[] = <<<SQL
CREATE TABLE IF NOT EXISTS kategorie_sjednocenych_tagu_62(
    id INT UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT,
    nazev VARCHAR(128) PRIMARY KEY,
    id_hlavni_kategorie INT UNSIGNED,
    poradi INT UNSIGNED NOT NULL,
    FOREIGN KEY (id_hlavni_kategorie) REFERENCES kategorie_sjednocenych_tagu_62(id) /* itself (parent row) */ ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
INSERT INTO kategorie_sjednocenych_tagu_62(nazev, id_hlavni_kategorie, poradi)
VALUES {$mainCategoriesSql};
INSERT INTO kategorie_sjednocenych_tagu_62(nazev, id_hlavni_kategorie, poradi)
VALUES {$subCategoriesSql};
SQL;

$queries[] = <<<SQL
CREATE TABLE IF NOT EXISTS sjednocene_tagy_62 (
    id INT UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT,
    id_kategorie_tagu INT UNSIGNED NOT NULL,
    nazev VARCHAR(128) PRIMARY KEY,
    poznamka TEXT NOT NULL DEFAULT '',
    FOREIGN KEY FK_kategorie_sjednocenych_tagu_62(id_kategorie_tagu) REFERENCES kategorie_sjednocenych_tagu_62(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;
ALTER TABLE sjednocene_tagy_62 AUTO_INCREMENT={$tagsAutoIncrementStart};
INSERT /* intentionally not IGNORE to detect invalid input data, see bellow */ INTO sjednocene_tagy_62(id, id_kategorie_tagu, nazev, poznamka)
SELECT sjednocene_tagy_temp.id, kategorie_sjednocenych_tagu_62.id, sjednocene_tagy_temp.opraveny_nazev, GROUP_CONCAT(DISTINCT sjednocene_tagy_temp.poznamka SEPARATOR '; ')
FROM sjednocene_tagy_temp
JOIN kategorie_sjednocenych_tagu_62 ON kategorie_sjednocenych_tagu_62.nazev = sjednocene_tagy_temp.nazev_kategorie
WHERE sjednocene_tagy_temp.opraveny_nazev != '-' -- strange records marked for deletion
GROUP BY sjednocene_tagy_temp.opraveny_nazev, kategorie_sjednocenych_tagu_62.id; -- intentionally grouped also by kategorie_sjednocenych_tagu.id to get fatal in case of duplicated opraveny_nazev but different kategorie_sjednocenych_tagu.id => logic error in source data
SQL;
$queries[] = <<<SQL
CREATE TABLE IF NOT EXISTS akce_sjednocene_tagy_62 LIKE akce_tagy;
INSERT IGNORE INTO akce_sjednocene_tagy_62(id_akce, id_tagu)
SELECT akce_tagy.id_akce, sjednocene_tagy_62.id
FROM akce_tagy
INNER JOIN sjednocene_tagy_temp ON sjednocene_tagy_temp.id = akce_tagy.id_tagu
INNER JOIN sjednocene_tagy_62 ON sjednocene_tagy_62.nazev = sjednocene_tagy_temp.opraveny_nazev;
SQL;

$queries[] = <<<SQL
RENAME TABLE sjednocene_tagy TO sjednocene_tagy_bug_058_zaloha;
RENAME TABLE kategorie_sjednocenych_tagu TO kategorie_sjednocenych_tagu_bug_058_zaloha;
RENAME TABLE akce_sjednocene_tagy TO akce_sjednocene_tagy_bug_058_zaloha;
SQL;

$queries[] = <<<SQL
RENAME TABLE sjednocene_tagy_62 TO sjednocene_tagy;
RENAME TABLE kategorie_sjednocenych_tagu_62 TO kategorie_sjednocenych_tagu;
RENAME TABLE akce_sjednocene_tagy_62 TO akce_sjednocene_tagy;
SQL;

$queries[] = <<<SQL
DROP TEMPORARY TABLE sjednocene_tagy_temp;
SQL;
try {
  foreach ($queries as $query) {
    $this->q($query);
  }
} catch (\Exception $exception) {
  throw new RuntimeException(
    sprintf("Migration %s failed: '%s'. Check it: \n%s", basename(__FILE__, '.php'), $exception->getMessage(), $query),
    $exception->getCode(),
    $exception
  );
}
