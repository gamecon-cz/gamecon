<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// „(bez typu – organizační)“ je pseudotyp starých aktivit (2010–2014). Neaktivní typ si aktivita může jen ponechat,
// nově ho nedostane z editoru ani z importu, viz Aktivita::zkontrolujTyp() a ImportRequirementsGuardian.
$this->q(<<<'SQL'
UPDATE akce_typy SET aktivni = 0 WHERE id_typu = 0
SQL);
