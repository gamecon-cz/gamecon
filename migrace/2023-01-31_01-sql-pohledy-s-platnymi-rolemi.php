<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE systemove_nastaveni
    ADD COLUMN pouze_pro_cteni TINYINT(1) DEFAULT 0
SQL
);

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni
    SET
        klic='ROCNIK',
        hodnota=2023,
        aktivni=1,
        datovy_typ= 'integer',
        nazev='Ročník',
        popis='Který ročník GC je aktivní',
        skupina='Časy',
        poradi=(SELECT MAX(poradi)+1 FROM systemove_nastaveni AS predchozi),
        pouze_pro_cteni=1
SQL
);

$this->q(<<<SQL
DROP VIEW IF EXISTS platne_zidle
SQL
);

$jakykoliRocnik = \Gamecon\Role\Zidle::JAKYKOLI_ROCNIK;
$this->q(<<<SQL
CREATE SQL SECURITY INVOKER VIEW platne_zidle
AS SELECT * FROM r_zidle_soupis
WHERE rocnik IN ((SELECT hodnota FROM systemove_nastaveni WHERE klic = 'ROCNIK' LIMIT 1), $jakykoliRocnik)
SQL
);

$this->q(<<<SQL
DROP VIEW IF EXISTS platne_zidle_uzivatelu
SQL
);

$this->q(<<<SQL
CREATE SQL SECURITY INVOKER VIEW platne_zidle_uzivatelu
AS SELECT r_uzivatele_zidle.*
   FROM r_uzivatele_zidle
   JOIN platne_zidle ON r_uzivatele_zidle.id_zidle = platne_zidle.id_zidle
SQL
);
