<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Peněžní sloupce zavedené e-shopem měly každý jinou přesnost: total_price 8,2 (strop
// 999 999,99), zbytek 6,2 (strop 9 999,99). Sjednoceno na 10,2 — to je výchozí přesnost
// MariaDB i Doctrine a používá ji už účetní strana (platby.castka, slevy.castka).
//
// Rozšíření je bezztrátové, měřítko zůstává 2. Legacy sloupce (cena_nakupni,
// cena_aktualni) tu schválně nejsou, ty chtějí vlastní ověření.
$this->q(<<<SQL
ALTER TABLE shop_order
    MODIFY total_price NUMERIC(10, 2) DEFAULT '0.00' NOT NULL
SQL);

$this->q(<<<SQL
ALTER TABLE shop_nakupy
    MODIFY original_price  NUMERIC(10, 2) DEFAULT NULL COMMENT 'Original price before discounts',
    MODIFY discount_amount NUMERIC(10, 2) DEFAULT NULL COMMENT 'Discount amount in CZK'
SQL);

$this->q(<<<SQL
ALTER TABLE product_variant
    MODIFY price NUMERIC(10, 2) DEFAULT NULL
SQL);
