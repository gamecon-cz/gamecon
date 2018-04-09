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
$this->q('
  INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava) VALUES
    ('.P_DVE_TRICKA_ZDARMA.',     "Dvě jakákoli trička zdarma", ""),
    ('.P_TRICKO_MODRA_BARVA.',    "Právo na modré tričko", "Může si objednávat modrá trička"),
    ('.P_TRICKO_CERVENA_BARVA.',  "Právo na červené tričko", "Může si objednávat červená trička")
');

// vytvoření a úpravy židlí
$this->q("UPDATE `r_zidle_soupis` SET
`id_zidle` = '2',
`jmeno_zidle` = 'Organizátor (zdarma)',
`popis_zidle` = 'Člen organizačního týmu GC'
WHERE `id_zidle` = '2';");
$this->q("INSERT INTO `r_zidle_soupis` (`id_zidle`, `jmeno_zidle`, `popis_zidle`)
VALUES ('21', 'Organizátor (s bonusy 1)', ''), ('22', 'Organizátor (s bonusy 2)', '');");

// přiřazení práv k židlím
$this->q('
  INSERT INTO r_prava_zidle(id_zidle, id_prava) VALUES
    ('.Z_ORG.',       '.P_TRICKO_CERVENA_BARVA.'),
    ('.Z_ORG.',       '.P_TRICKO_MODRA_BARVA.'),
    ('.Z_ORG.',       '.P_DVE_TRICKA_ZDARMA.'),
    ('.Z_ORG_AKCI.',  '.P_TRICKO_MODRA_BARVA.'),
    -- ('.Z_ORG_AKCI.',  '.P_TRICKO_ZA_SLEVU_MODRE.'), -- už má

    (21, 1008), -- ubytování zdarma
    (21, 1004), -- jídlo se slevou
    (21, 1019), -- sleva na aktivity
    (21, 1021), -- modré tričko
    (21, 1022), -- červené tričko
    (21, 1009), -- sleva za aktivity
    (21, 1012), -- modré tričko za slevu
    (21,    4), -- může vést aktivity
    (21, 1016), -- nerušit automaticky objednávky

    (22, 1015), -- středa zdarma
    (22, 1018), -- neděle zdarma
    (22, 1020), -- dvě trička zdarma
    (22, 1004), -- jídlo se slevou
    (22, 1019), -- sleva na aktivity
    (22, 1021), -- modré tričko
    (22, 1022), -- červené tričko
    (22, 1009), -- sleva za aktivity
    (22, 1012), -- modré tričko za slevu
    (22,    4), -- může vést aktivity
    (22, 1016)  -- nerušit automaticky objednávky
');
