<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Sloupec pro evidenci, že infopult vybral od ubytovaného cizince registrační formulář.
// Chování zrcadlí potvrzeni_zakonneho_zastupce (datum = kdy potvrzeno, NULL = chybí).
$this->q(<<<SQL
ALTER TABLE uzivatele_hodnoty
    ADD COLUMN formular_cizince_od date DEFAULT NULL AFTER statni_obcanstvi
SQL);

// Registrace reportu ubytovaných cizinců (jinak by SELECT id FROM reporty vrátil NULL
// a INSERT do reporty_log_pouziti by porušil NOT NULL na id_reportu).
$this->q(<<<SQL
INSERT INTO reporty (skript, nazev, format_xlsx, format_html, viditelny)
    VALUES ('finance-report-ubytovani-cizinci', 'Ubytovaní cizinci', 1, 1, 0)
SQL);
