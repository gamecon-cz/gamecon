/* === MIGRACE: sloučení textu do jedné tabulky (novinky.text = LONGTEXT) === */

/* 1) Přidej dočasný LONGTEXT sloupec, pokud neexistuje */
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'novinky' AND COLUMN_NAME = 'text_md'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `novinky` ADD COLUMN `text_md` LONGTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_czech_ci NULL AFTER `autor`',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 2) Naplň text_md z tabulky texty (pokud existuje), jinak prázdným řetězcem – jen tam, kde je NULL */
SET @has_texty := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'texty'
);
SET @sql := IF(@has_texty > 0,
  'UPDATE `novinky` n LEFT JOIN `texty` t ON t.`id` = n.`text` SET n.`text_md` = COALESCE(n.`text_md`, t.`text`) WHERE n.`text_md` IS NULL',
  'UPDATE `novinky` n SET n.`text_md` = COALESCE(n.`text_md`, '''') WHERE n.`text_md` IS NULL'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 3) Zaruč NOT NULL pro text_md */
UPDATE `novinky` SET `text_md` = '' WHERE `text_md` IS NULL;
ALTER TABLE `novinky` MODIFY COLUMN `text_md` LONGTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_czech_ci NOT NULL;

/* 4) Dropni případný cizí klíč na novinky.text */
SET @fk := (
  SELECT CONSTRAINT_NAME
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'novinky'
    AND COLUMN_NAME = 'text'
    AND REFERENCED_TABLE_NAME IS NOT NULL
  LIMIT 1
);
SET @sql := IF(@fk IS NULL, 'SELECT 1', CONCAT('ALTER TABLE `novinky` DROP FOREIGN KEY `', @fk, '`'));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 5) Dropni všechny sekundární indexy, které používají sloupec novinky.text */
SET @drops := (
  SELECT GROUP_CONCAT(CONCAT('DROP INDEX `', INDEX_NAME, '`') SEPARATOR ', ')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'novinky'
    AND COLUMN_NAME = 'text'
    AND INDEX_NAME <> 'PRIMARY'
);
SET @sql := IF(@drops IS NULL, 'SELECT 1', CONCAT('ALTER TABLE `novinky` ', @drops));
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 6) Odstraň původní BIGINT sloupec `text`, jen pokud je skutečně číselný */
SET @is_numeric_text := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'novinky'
    AND COLUMN_NAME = 'text'
    AND DATA_TYPE IN ('bigint','int','integer','smallint','mediumint')
);
SET @sql := IF(@is_numeric_text = 1, 'ALTER TABLE `novinky` DROP COLUMN `text`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 7) Přejmenuj text_md -> text, ale jen pokud `text` ještě neexistuje */
SET @has_text_md := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'novinky' AND COLUMN_NAME = 'text_md'
);
SET @has_text := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'novinky' AND COLUMN_NAME = 'text'
);
SET @sql := IF(@has_text_md = 1 AND @has_text = 0,
  'ALTER TABLE `novinky` CHANGE COLUMN `text_md` `text` LONGTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_czech_ci NOT NULL',
  'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

/* 8) Ujisti se, že výsledný sloupec má správné charset/collation */
ALTER TABLE `novinky` MODIFY COLUMN `text` LONGTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_czech_ci NOT NULL;

/* === KONEC MIGRACE === */
