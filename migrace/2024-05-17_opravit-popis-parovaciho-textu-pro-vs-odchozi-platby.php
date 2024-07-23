<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET popis = 'Přesné znění textu v "Poznámka", za kterým následuje ID účastníka GC (jemuž odesíláme z banky peníze) abychom podle tohoto textu spárovali odchozí platbu (poradí si s různou velikostí písmen i chybějící diakritikou)'
WHERE klic = 'TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY'
SQL
);
