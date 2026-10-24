<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Exporty jídelníčku pro jídelnu a infopult. Bez řádku v `reporty` by je modul Reporty
// nenabídl a záznam o použití by porušil NOT NULL na id_reportu.
$this->q(<<<'SQL'
INSERT INTO reporty (skript, nazev, format_xlsx, format_html, viditelny)
SELECT novy.skript, novy.nazev, 1, 1, 1
FROM (
    SELECT 'jidlo-pocty-jidel' AS skript, 'Jídlo: počty konkrétních jídel {ROK}' AS nazev
    UNION ALL
    SELECT 'jidlo-vyber-ucastniku', 'Jídlo: výběr jídel účastníků {ROK}'
) AS novy
WHERE NOT EXISTS (SELECT 1 FROM reporty WHERE reporty.skript = novy.skript)
SQL,
);
