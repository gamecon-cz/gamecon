/* === MIGRACE: novinky.text (BIGINT -> LONGTEXT) + přesun z texty === */

/* 1) Pokud není, přidej dočasný sloupec text_md (LONGTEXT) */
SET @col_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'novinky' AND COLUMN_NAME = 'text_md'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `novinky` ADD COLUMN `text_md` LONGTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_czech_ci NULL AFTER `autor`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* 2) Naplň text_md obsahem z texty, pokud tabulka existuje; jinak dej prázdný řetězec */
/* Jen pro řádky, kde text_md je zatím NULL (idempotentní) */
SET @has_texty := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'texty'
);
SET @sql := IF(@has_texty > 0,
  'UPDATE `novinky` n LEFT JOIN `texty` t ON t.`id` = n.`text` SET n.`text_md` = COALESCE(n.`text_md`, t.`text`) WHERE n.`text_md` IS NULL',
  'UPDATE `novinky` n SET n.`text_md` = COALESCE(n.`text_md`, '''') WHERE n.`text_md` IS NULL'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* 3) Zaruč NOT NULL (LONGTEXT nemá DEFAULT, ale prázdný string je OK) */
UPDATE `novinky` SET `text_md` = '' WHERE `text_md` IS NULL;
ALTER TABLE `novinky` MODIFY COLUMN `text_md` LONGTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_czech_ci NOT NULL;

/* 4) Pokud existuje cizí klíč z novinky.text -> texty.id, zahoď ho */
SET @fk_name := (
  SELECT CONSTRAINT_NAME
  FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'novinky'
    AND COLUMN_NAME = 'text'
    AND REFERENCED_TABLE_NAME IS NOT NULL
  LIMIT 1
);
SET @sql := IF(@fk_name IS NULL, 'SELECT 1', CONCAT('ALTER TABLE `novinky` DROP FOREIGN KEY `', @fk_name, '`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* 5) Zahoď všechny sekundární indexy, které používají sloupec novinky.text (pokud nějaké jsou) */
SET @alter_drops := (
  SELECT GROUP_CONCAT(CONCAT('DROP INDEX `', INDEX_NAME, '`') SEPARATOR ', ')
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'novinky'
    AND COLUMN_NAME = 'text'
    AND INDEX_NAME <> 'PRIMARY'
);
SET @sql := IF(@alter_drops IS NULL, 'SELECT 1', CONCAT('ALTER TABLE `novinky` ', @alter_drops));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* 6) Zahoď starý BIGINT sloupec `text` (pokud ještě existuje) */
SET @col_text_bigint := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'novinky'
    AND COLUMN_NAME = 'text'
    AND DATA_TYPE IN ('bigint','int','integer')
);
SET @sql := IF(@col_text_bigint = 1, 'ALTER TABLE `novinky` DROP COLUMN `text`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* 7) Přejmenuj text_md -> text (pokud text_md existuje) */
SET @col_text_md_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'novinky'
    AND COLUMN_NAME = 'text_md'
);
SET @sql := IF(@col_text_md_exists = 1,
  'ALTER TABLE `novinky` CHANGE COLUMN `text_md` `text` LONGTEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_czech_ci NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

/* (VOLITELNĚ) 8) Fulltext index pro hledání v titulku a obsahu */
/* ALTER TABLE `novinky` ADD FULLTEXT `ft_novinky` (`nazev`, `text`);-*/

/* (VOLITELNĚ) 9) Index pro běžné výpisy podle typu a data vydání */
// CREATE INDEX `idx_novinky_typ_vydat` ON `novinky` (`typ`, `vydat`);

/* Hotovo: `novinky`.`text` je LONGTEXT a UI se přepne na textarea. */
