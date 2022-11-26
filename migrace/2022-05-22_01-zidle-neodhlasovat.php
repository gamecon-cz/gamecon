<?php
/** @var \Godric\DbMigrations\Migration $this */

$pravoNerusitAutomatickyObjednavky = \Gamecon\Pravo::NERUSIT_AUTOMATICKY_OBJEDNAVKY;
$this->q(<<<SQL
INSERT IGNORE INTO r_zidle_soupis(id_zidle, jmeno_zidle, popis_zidle) VALUES
(23, 'Neodhlašovat', 'Může zaplatit až na místě. Je chráněn před odhlašováním neplatičů a nezaplacených objednávek.');

INSERT IGNORE INTO r_prava_zidle(id_zidle, id_prava) VALUES
(23, $pravoNerusitAutomatickyObjednavky);
SQL
);
