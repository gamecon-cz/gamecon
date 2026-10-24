<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Stránka Jídelníček slibovala jídelníček „pár dní před GameConem" a tvrdila, že se objednává
// jen porce. Od teď si účastník k porci vybírá konkrétní jídlo a jídelníček stránce vykreslí
// widget. Text se mění jen tam, kde ho od té doby nikdo neupravil; jinde je potřeba vložit
// `(widget:jidelnicek)` ručně v Editaci stránek.
$this->q(<<<'SQL'
UPDATE stranky
SET obsah = REPLACE(
        REPLACE(
            obsah,
            'Nabídka pro obědy a večeře je obvykle stejná a dopředu si objednáváš pouze porci, ne konkrétní jídlo. Ne všechna jídla tak musí být dostupná po celou dobu výdeje.',
            'Nabídka pro obědy a večeře je obvykle stejná. Konkrétní jídlo si k objednané porci vybereš, jakmile zveřejníme jídelníček (14 dní před GameConem), a to v přihlášce nebo přímo tady. Kdo si nevybere, dostane jídlo označené jako výchozí.'
        ),
        '##Jídelníček zveřejníme pár dní před GameConem.',
        '##Jídelníček\n\n(widget:jidelnicek)'
    )
WHERE url_stranky = 'jidelnicek'
    AND obsah LIKE '%##Jídelníček zveřejníme pár dní před GameConem.%'
SQL,
);
