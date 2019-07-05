<?php
/** @var \Godric\DbMigrations\Migration $this */
$this->q("
CREATE PROCEDURE `GDPR_PROCISTENI_UDAJU` (IN `ID_UZIVATELE` INT) 
COMMENT 'Procedura pro vymazání citlivých údajù u uživatele.' 
NOT DETERMINISTIC MODIFIES SQL DATA SQL SECURITY DEFINER 
UPDATE uzivatele_hodnoty 
SET jmeno_uzivatele = null, 
prijmeni_uzivatele = null, 
datum_narozeni = DATE_FORMAT(datum_narozeni, '%y-00-00'), 
telefon_uzivatele = null, 
email1_uzivatele = UUID(),
email2_uzivatele = null, 
ulice_a_cp_uzivatele = null, 
ubytovan_s = null, 
op = null, 
login_uzivatele = UUID(), 
heslo_md5 = null, 
jine_uzivatele = null, 
poznamka = null, 
skola = null, 
pomoc_typ = null, 
pomoc_vice = null 
WHERE id_uzivatele = ID_UZIVATELE;
");