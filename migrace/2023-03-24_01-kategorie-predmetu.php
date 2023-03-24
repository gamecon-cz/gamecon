<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE shop_predmety
    ADD COLUMN kategorie_predmetu INT UNSIGNED NULL DEFAULT NULL,
    ADD INDEX kategorie_predmetu(kategorie_predmetu)
SQL
);

$this->q(<<<SQL
UPDATE shop_predmety
    SET kategorie_predmetu = CASE
        WHEN nazev LIKE '%placka%' COLLATE utf8_czech_ci THEN 1
        WHEN nazev LIKE '%kostka%' COLLATE utf8_czech_ci THEN 2
        WHEN nazev LIKE '%tričko%' COLLATE utf8_czech_ci THEN 3
        WHEN nazev LIKE '%tílko%' COLLATE utf8_czech_ci THEN 4
        WHEN nazev LIKE '%blok%' COLLATE utf8_czech_ci THEN 5
        WHEN nazev LIKE '%nicknack%' COLLATE utf8_czech_ci THEN 6
        WHEN nazev LIKE '%ponožky%' COLLATE utf8_czech_ci THEN 7
        WHEN nazev LIKE '%covid%' COLLATE utf8_czech_ci THEN 8
        ELSE NULL
    END
SQL
);

$this->q(<<<SQL
ALTER TABLE shop_predmety
    ADD COLUMN se_slevou TINYINT UNSIGNED NOT NULL DEFAULT 0
SQL
);

$this->q(<<<SQL
UPDATE shop_predmety
    SET se_slevou = 1
WHERE kategorie_predmetu IN (3, 4)
    AND (nazev LIKE '%modré%' COLLATE utf8_czech_ci OR nazev LIKE '%červené%' COLLATE utf8_czech_ci) 
SQL
);
