<?php

// smazání starých práv
$this->q('DELETE FROM r_prava_zidle  WHERE id_prava = 1000'); // P_TRIKO_ZDARMA
$this->q('DELETE FROM r_prava_soupis WHERE id_prava = 1000');
$this->q('DELETE FROM r_prava_zidle  WHERE id_prava = 1011'); // P_TRIKO_ZA_SLEVU
$this->q('DELETE FROM r_prava_soupis WHERE id_prava = 1011');
$this->q('DELETE FROM r_prava_zidle  WHERE id_prava = 1013'); // P_TRIKO_SLEVA
$this->q('DELETE FROM r_prava_soupis WHERE id_prava = 1013');
$this->q('DELETE FROM r_prava_zidle  WHERE id_prava = 1014'); // P_TRIKO_SLEVA_MODRE
$this->q('DELETE FROM r_prava_soupis WHERE id_prava = 1014');

// přidání nových práv
$this->q(<<<SQL
  INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava) VALUES
    (1020, 'Dvě jakákoli trička zdarma', ''),
    (1021, 'Právo na modré tričko', 'Může si objednávat modrá trička'),
    (1022, 'Právo na červené tričko', 'Může si objednávat červená trička')
SQL
);

// vytvoření a úpravy židlí
$this->q("UPDATE `r_zidle_soupis` SET
`id_zidle` = '2',
`jmeno_zidle` = 'Organizátor (zdarma)',
`popis_zidle` = 'Člen organizačního týmu GC'
WHERE `id_zidle` = '2';");

$this->q("INSERT INTO `r_zidle_soupis` (`id_zidle`, `jmeno_zidle`, `popis_zidle`)
VALUES ('21', 'Organizátor (s bonusy 1)', ''), ('22', 'Organizátor (s bonusy 2)', '');");
