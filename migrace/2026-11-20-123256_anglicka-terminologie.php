<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// English terminology settled after the first review: "vypravěč" is a game master (not a storyteller) and
// "přednáška" is a talk (not a lecture). The earlier migrations of this branch already use the new terms; this one
// brings a database that ran their first version (the preview of the branch) to the same state. On a fresh database
// it only writes the same values again.

$this->q(<<<'SQL'
UPDATE akce_typy
SET typ_1p_en = 'talk', typ_1pmn_en = 'talks'
WHERE id_typu = 3
SQL,
);

$this->q(<<<'SQL'
UPDATE kategorie_sjednocenych_tagu
SET nazev_en = CONCAT('talk ', SUBSTRING(nazev_en, LENGTH('lecture ') + 1))
WHERE nazev_en LIKE 'lecture %'
SQL,
);

// The English content of the CMS pages: the translation migrations only set obsah_en, so running them again with
// the new terms is safe.
require __DIR__ . '/2026-11-20-102000_preklady-stranek.php';
require __DIR__ . '/2026-11-20-102100_preklady-stranek-2.php';
