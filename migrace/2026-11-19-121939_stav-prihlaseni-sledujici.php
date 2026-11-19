<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Sledování aktivity se první rok jmenovalo „náhradník“ a ten název u stavu přihlášení zůstal. Náhradník je ale ten,
// kdo dorazil na volné místo bez přihlášení (stav 2), sledující je ten, komu přijde mail o uvolněném místě (stav 5).
$this->q(<<<'SQL'
UPDATE akce_prihlaseni_stavy
SET nazev = 'sledující'
WHERE id_stavu_prihlaseni = 5
SQL);
