<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Doctrine načte jen přesně `m` nebo `f` (GenderEnum), prázdné pohlaví shodí každé načtení účtu.
// Jiný zápis `f` (`F`, `f `) zůstane ženou, vše ostatní dostane `m` jako v Uzivatel::rychloregistrace().
// Bajtové porovnání, protože utf8mb4_czech_ci bere `F` i `f ` jako rovné `f`.
$this->q(<<<'SQL'
UPDATE uzivatele_hodnoty
SET pohlavi = IF(LOWER(TRIM(pohlavi)) = 'f', 'f', 'm')
WHERE pohlavi COLLATE utf8mb4_nopad_bin NOT IN ('m', 'f')
SQL);
