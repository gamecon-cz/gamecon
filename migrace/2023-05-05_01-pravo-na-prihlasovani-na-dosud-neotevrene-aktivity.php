<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO r_prava_soupis
SET id_prava = 9,
    jmeno_prava = 'Přhlašování na dosud neotevřené aktivity',
    popis_prava = 'Může přihlašovat a odhlašovat lidi z aktivit, které ještě nejsou Připravené (jsou teprve Publikované)'
SQL,
);

$this->q(<<<SQL
INSERT INTO role_seznam
SET
    id_role = 25,
    kod_role = 'SEF_PROGRAMU',
    nazev_role = 'Šéf programu',
    popis_role = 'Všeobecné "vedení" programu - obecná dramaturgie, rozvoj sekcí, finance programu',
    rocnik_role = -1,
    typ_role = 'trvala',
    vyznam_role = 'SEF_PROGRAMU',
    skryta = 0,
    kategorie_role = 0
SQL,
);

$this->q(<<<SQL
INSERT INTO prava_role
SET id_role = 25,
    id_prava = 9
SQL,
);


$this->q(<<<SQL
INSERT IGNORE /* IGNORE protože na testovacích databázích tento uživatel není */ INTO uzivatele_role
SET id_role = 25,
    id_uzivatele = (SELECT id_uzivatele FROM uzivatele_hodnoty WHERE login_uzivatele = 'sirien'),
    posazen = NOW(),
    posadil = 1
SQL,
);
