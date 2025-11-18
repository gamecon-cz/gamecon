<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
/* Přidej dočasný TEXT sloupec */
ALTER TABLE `novinky`
    ADD COLUMN `_text` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NULL COMMENT 'markdown' AFTER `text`;

/* Naplň _popis z tabulky texty */
UPDATE `novinky` LEFT JOIN `texty` ON texty.`id` = novinky.`text`
SET novinky.`_text` = texty.`text`;

/* Zaruč NOT NULL */
UPDATE `novinky`
SET `_text` = ''
WHERE `_text` IS NULL;

ALTER TABLE `novinky`
    DROP FOREIGN KEY IF EXISTS FK_626265713B8BA7C7,
    DROP FOREIGN KEY IF EXISTS FK_novinky_to_texty,
    DROP COLUMN `text`;

ALTER TABLE `novinky`
    CHANGE COLUMN `_text` `text` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci NULL COMMENT 'markdown';

/* Přidej dočasný TEXT sloupec */
ALTER TABLE `akce_seznam`
    ADD COLUMN `_popis` TEXT CHARACTER SET utf8mb3 COLLATE utf8mb3_czech_ci NULL COMMENT 'markdown' AFTER `popis`;

/* Naplň _popis z tabulky texty */
UPDATE `akce_seznam` LEFT JOIN `texty` ON texty.`id` = akce_seznam.`popis`
SET akce_seznam.`_popis` = texty.`text`;

/* Zaruč NOT NULL */
UPDATE `akce_seznam`
SET `_popis` = ''
WHERE `_popis` IS NULL;

/* Odstraň původní INT sloupec popis */
ALTER TABLE `akce_seznam`
    DROP FOREIGN KEY FK_2EE8EBF0757768BF,
    DROP COLUMN `popis`;

/* Přejmenuj _popis -> popis */
ALTER TABLE `akce_seznam`
    CHANGE COLUMN `_popis` `popis` LONGTEXT NOT NULL COMMENT 'markdown';

DROP TABLE `texty`;
SQL,
);

if (file_exists(__DIR__ . '/../logy/markdown.sqlite')) {
    unlink(__DIR__ . '/../logy/markdown.sqlite');
}

if (file_exists(__DIR__ . '/../cache/private/markdown.sqlite')) {
    unlink(__DIR__ . '/../cache/private/markdown.sqlite');
}
