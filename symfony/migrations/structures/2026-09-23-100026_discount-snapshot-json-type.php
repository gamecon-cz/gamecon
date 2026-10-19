<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Sloupec vznikl jako LONGTEXT s `JSON_VALID` checkem, ale entita ho mapuje Doctrine
// typem `json`, který očekává deklaraci `JSON` i s jeho značkou v komentáři. Bez toho
// `doctrine:schema:validate` hlásí rozjeté schéma, i když je sloupec namapovaný.
// V MariaDB je `JSON` alias pro LONGTEXT s checkem, takže se data nepřevádějí.
$this->q(<<<'SQL'
ALTER TABLE shop_nakupy
    MODIFY discount_snapshot JSON DEFAULT NULL COMMENT '(DC2Type:json)'
SQL);
