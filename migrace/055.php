<?php
/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
DROP PROCEDURE IF EXISTS `GDPR_PROCISTENI_UDAJU`;

CREATE PROCEDURE `GDPR_PROCISTENI_UDAJU`(IN id_uzivatele_param INT)
    MODIFIES SQL DATA
UPDATE uzivatele_hodnoty
SET jmeno_uzivatele      = 'ANON',
    prijmeni_uzivatele   = 'ANON',
    datum_narozeni       = DATE_FORMAT(datum_narozeni, '%y-01-01'),
    telefon_uzivatele    = 'ANON',
    email1_uzivatele     = UUID(),
    email2_uzivatele     = UUID(),
    ulice_a_cp_uzivatele = 'ANON',
    ubytovan_s           = 'ANON',
    op                   = 'ANON',
    login_uzivatele      = UUID(),
    heslo_md5            = 'ANON',
    jine_uzivatele       = 'ANON',
    skola                = 'ANON',
    pomoc_typ            = 'ANON',
    pomoc_vice           = 'ANON'
WHERE id_uzivatele = id_uzivatele_param
SQL
);
