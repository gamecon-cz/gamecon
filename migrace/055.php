<?php
/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
DROP PROCEDURE IF EXISTS GDPR_PROCISTENI_UDAJU;

CREATE PROCEDURE `GDPR_PROCISTENI_UDAJU`(IN cilove_id_uzivatele INT)
    MODIFIES SQL DATA
UPDATE uzivatele_hodnoty
SET jmeno_uzivatele      = '',
    prijmeni_uzivatele   = '',
    datum_narozeni       = DATE_FORMAT(datum_narozeni, '%y-01-01'),
    telefon_uzivatele    = '',
    email1_uzivatele     = UUID(),
    email2_uzivatele     = '',
    ulice_a_cp_uzivatele = '',
    ubytovan_s           = '',
    op                   = '',
    login_uzivatele      = UUID(),
    heslo_md5            = '',
    jine_uzivatele       = '',
    poznamka             = '',
    skola                = '',
    pomoc_typ            = '',
    pomoc_vice           = ''
WHERE id_uzivatele = cilove_id_uzivatele
SQL
);