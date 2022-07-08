<?php
/** @var \Godric\DbMigrations\Migration $this */

$random = randHex(20);

$this->q(<<<SQL
INSERT IGNORE INTO uzivatele_hodnoty
SET
    id_uzivatele = 1,
    login_uzivatele = 'SYSTEM',
    jmeno_uzivatele = 'SYSTEM',
    prijmeni_uzivatele = 'SYSTEM',
    ulice_a_cp_uzivatele = 'SYSTEM',
    mesto_uzivatele = 'SYSTEM',
    stat_uzivatele = 1,
    psc_uzivatele = 'SYSTEM',
    telefon_uzivatele = 'SYSTEM',
    email1_uzivatele = 'system@gamecon.cz',
    email2_uzivatele = 'system@gamecon.cz',
    jine_uzivatele = '',
    forum_razeni = '',
    datum_narozeni = CURRENT_DATE,
    heslo_md5 = '',
    funkce_uzivatele = 0,
    nechce_maily = CURRENT_DATE,
    mrtvy_mail = 1,
    zustatek = 0.0,
    poznamka = '',
    random = '$random',
    registrovan = CURRENT_DATE,
    op = '',
    pomoc_typ = '',
    pomoc_vice = ''
SQL
);
