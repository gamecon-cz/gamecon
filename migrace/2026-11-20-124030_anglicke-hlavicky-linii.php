<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// English text of who heads the line („šéf RPG“) in the header of a line page on the English website (/en/rpg).
// NULL = not translated, the website shows the Czech text (see KontaktSefaLinie::getSekceVJazyce()). It is edited
// next to the Czech one in the admin (Web → Hlavičky linií).
$this->q(<<<'SQL'
ALTER TABLE akce_typy_hlavicky
    ADD COLUMN sekce_en VARCHAR(255) NULL DEFAULT NULL COMMENT 'anglicky, kdo linii vede, NULL = jen česky'
        AFTER sekce
SQL,
);

// Rows are matched by the Czech text (the collation ignores case), so that a header whose Czech text differs
// on another database stays Czech rather than getting an English text that says something else (CASE without
// a match gives NULL, the column is new).
// The English is gender-neutral, a line can be headed by a man or a woman („šéf“, „šéfka“).
$this->q(<<<'SQL'
UPDATE akce_typy_hlavicky
SET sekce_en = CASE sekce
    WHEN 'šéf Deskoherny' THEN 'head of the board game library'
    WHEN 'šéfka Larpů' THEN 'head of larps'
    WHEN 'šéf Přednášek' THEN 'head of talks'
    WHEN 'šéf RPG' THEN 'head of RPG'
    WHEN 'šéf Wargamingu' THEN 'head of wargaming'
    WHEN 'šéf Akčních her' THEN 'head of action games'
    WHEN 'šéf LKD' THEN 'head of the Legends of the Adventurers’ Club'
    WHEN 'šéf mDrD' THEN 'head of the DrD Championship'
    WHEN 'šéf Epických deskovek' THEN 'head of epic board games'
    WHEN 'šéf Doprovodného programu' THEN 'head of the side programme'
    WHEN 'Šéf D&D/JaD turnaje' THEN 'head of the D&D tournament'
END
SQL,
);
