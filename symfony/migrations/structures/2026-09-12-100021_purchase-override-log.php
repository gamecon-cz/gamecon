<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Kdo a co obešel, když se nákup zapsal mimo běžná pravidla — typicky prodej na infopultu
// po termínu nebo přes kapacitu. Doteď se dal dohledat jen objednatel, ne to, že se vůbec
// něco přeskočilo.
//
// Nullable bez defaultu: NULL znamená „žádné obcházení", což platí pro celou historii
// i pro každý samoobslužný nákup. Pole, ne objekt — jedna akce může obejít víc pravidel
// najednou (termín i kapacitu).
$this->q(<<<SQL
ALTER TABLE shop_nakupy
    ADD override_log JSON DEFAULT NULL
SQL);
