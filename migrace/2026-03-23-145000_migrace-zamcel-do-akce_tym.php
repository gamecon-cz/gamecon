<?php

declare(strict_types=1);

// todo(tym): zatím nefunguje přesun na nový systém, dodělá se potom (tahle migrace je potřeba jen pro odstranění starých sloupečků)

/*
// Migrace dat ze zamcel/zamcel_cas v akce_seznam do akce_tym a akce_tym_prihlaseni.
// Pro každou teamovou aktivitu která má zamcel (= kapitán) ale ještě nemá záznam v akce_tym
// vytvoříme tým a přiřadíme do něj všechny přihlášené účastníky.
// Po migraci smazeme staré sloupce ze acke_seznam.

// Vytvoříme záznamy v akce_tym pro aktivity které mají zamcel ale nemají akce_tym řádek
// Přesuneme také team_nazev a team_limit
$this->q(<<<SQL
    INSERT INTO akce_tym (id_akce, kod, id_kapitan, zalozen, nazev, `limit`)
    SELECT
        akce_seznam.id_akce,
        FLOOR(1000 + RAND() * 9000),
        akce_seznam.zamcel,
        COALESCE(akce_seznam.zamcel_cas, NOW()),
        akce_seznam.team_nazev,
        akce_seznam.team_limit
    FROM akce_seznam
    WHERE akce_seznam.zamcel IS NOT NULL
      AND akce_seznam.id_akce NOT IN (SELECT akce_tym.id_akce FROM akce_tym)
SQL,
);

// Přiřadíme všechny přihlášené účastníky těchto aktivit do nově vytvořených týmů
$this->q(<<<SQL
    INSERT IGNORE INTO akce_tym_prihlaseni (id_uzivatele, id_tymu)
    SELECT
        akce_prihlaseni.id_uzivatele,
        akce_tym.id
    FROM akce_prihlaseni
    JOIN akce_tym ON akce_tym.id_akce = akce_prihlaseni.id_akce
    LEFT JOIN akce_tym_prihlaseni ON akce_tym_prihlaseni.id_uzivatele = akce_prihlaseni.id_uzivatele
                                 AND akce_tym_prihlaseni.id_tymu = akce_tym.id
    WHERE akce_tym_prihlaseni.id IS NULL
SQL,
);

// Smazeme trigger na team_limit
$this->q(<<<SQL
    DROP TRIGGER IF EXISTS `trigger_check_and_apply_team_limit`
SQL,
);

// Smazeme všechny foreign key constrainty na sloupci zamcel
$dbName = DB_NAME;
$result = $this->q(<<<SQL
    SELECT kcu.CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE kcu
    WHERE kcu.CONSTRAINT_SCHEMA = '{$dbName}'
      AND kcu.TABLE_NAME = 'akce_seznam'
      AND kcu.COLUMN_NAME = 'zamcel'
      AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
SQL,
);
$fkNames = [];
while ($row = $result->fetch_assoc()) {
    $fkNames[] = $row['CONSTRAINT_NAME'];
}
foreach ($fkNames as $fkName) {
    $this->q("ALTER TABLE akce_seznam DROP FOREIGN KEY `$fkName`");
}

// Smazeme staré sloupce
$this->q(<<<SQL
    ALTER TABLE akce_seznam
    DROP COLUMN zamcel,
    DROP COLUMN zamcel_cas,
    DROP COLUMN team_nazev,
    DROP COLUMN team_limit
SQL,
);

// Aktualizujeme komentář u team_kapacita
$this->q(<<<SQL
    ALTER TABLE akce_seznam
    MODIFY COLUMN team_kapacita int(11) DEFAULT NULL COMMENT 'max. počet týmů na aktivitě'
SQL,
);
*/

