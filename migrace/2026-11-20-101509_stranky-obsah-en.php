<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// English content of a CMS page for the English website (/en/…). NULL or empty = the page is only in Czech,
// the website then shows the Czech content with a note (see Stranka). The comment starts with "markdown",
// so the generic admin form (DbFormGc) gives the column the same markdown editor as `obsah`, and the text
// after the comma is the label of that editor (DbffMarkdownDirect::label()).
$this->q(<<<'SQL'
ALTER TABLE stranky
    ADD COLUMN obsah_en LONGTEXT NULL DEFAULT NULL COMMENT 'markdown, Obsah anglicky (prázdné = stránka je jen česky)'
        AFTER obsah
SQL,
);
