<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Půjčovna larpových kostýmů: katalog, fotky, rezervace na aktivitu a výdeje, viz docs/generated/pujcovna-kostymu.md.

$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS `kostym` (
  `id`               bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nazev`            varchar(255)        NOT NULL,
  `popis`            text                NOT NULL,
  `velikost`         varchar(32)         NOT NULL,
  `miry`             varchar(255)        NOT NULL,
  `strih`            varchar(32)         NOT NULL,
  `kategorie`        varchar(255)        NOT NULL,
  `stav`             varchar(32)         NOT NULL,
  `interni_poznamka` text                NOT NULL,
  `poradi`           int(11)             NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_czech_ci
SQL);

// Soubory leží v web/soubory/systemove/kostymy, smazání souboru má na starosti kód.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS `kostym_foto` (
  `id`         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_kostymu` bigint(20) unsigned NOT NULL,
  `soubor`     varchar(255)        NOT NULL,
  `poradi`     int(11)             NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_soubor` (`soubor`),
  KEY `IDX_id_kostymu_poradi` (`id_kostymu`, `poradi`),
  CONSTRAINT `FK_kostym_foto_kostym` FOREIGN KEY (`id_kostymu`)
    REFERENCES `kostym` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_czech_ci
SQL);

// Rezervace platí po dobu aktivity, čas se bere z akce_seznam. Cizí klíč na přihlášku rezervaci smaže,
// ať přihláška zmizí jakkoli. Vlastní cizí klíč na uzivatele_hodnoty schválně chybí: slučování uživatelů
// (UzivatelSlucovani) by tabulku mohlo vzít dřív než akce_prihlaseni a rezervaci smazat, takhle se
// přenese kaskádou z přihlášky. Stejně tak posun ID při anonymizaci databáze.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS `kostym_rezervace` (
  `id`           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_kostymu`   bigint(20) unsigned NOT NULL,
  `id_akce`      bigint(20) unsigned NOT NULL,
  `id_uzivatele` bigint(20) unsigned NOT NULL,
  `rezervoval`   bigint(20) unsigned          DEFAULT NULL,
  `rezervovano`  datetime            NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_id_akce_id_uzivatele` (`id_akce`, `id_uzivatele`),
  KEY `IDX_id_kostymu` (`id_kostymu`),
  KEY `IDX_rezervoval` (`rezervoval`),
  CONSTRAINT `FK_kostym_rezervace_kostym` FOREIGN KEY (`id_kostymu`)
    REFERENCES `kostym` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `FK_kostym_rezervace_prihlaseni` FOREIGN KEY (`id_akce`, `id_uzivatele`)
    REFERENCES `akce_prihlaseni` (`id_akce`, `id_uzivatele`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `FK_kostym_rezervace_rezervoval` FOREIGN KEY (`rezervoval`)
    REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_czech_ci
SQL);

// Výdej a vrácení. Záznam přežije zánik rezervace (odhlášený účastník má kostým pořád u sebe), proto
// si drží aktivitu: termín vrácení se počítá z jejího konce.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS `kostym_vydej` (
  `id`               bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_kostymu`       bigint(20) unsigned NOT NULL,
  `id_rezervace`     bigint(20) unsigned          DEFAULT NULL,
  `id_akce`          bigint(20) unsigned          DEFAULT NULL,
  `id_uzivatele`     bigint(20) unsigned NOT NULL,
  `vydano`           datetime            NOT NULL,
  `vydal`            bigint(20) unsigned          DEFAULT NULL,
  `vraceno`          datetime                     DEFAULT NULL,
  `prijal`           bigint(20) unsigned          DEFAULT NULL,
  `stav_pri_vraceni` varchar(32)                  DEFAULT NULL,
  `poznamka`         text                NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_id_kostymu_vraceno` (`id_kostymu`, `vraceno`),
  KEY `IDX_id_rezervace` (`id_rezervace`),
  KEY `IDX_id_akce` (`id_akce`),
  KEY `IDX_id_uzivatele` (`id_uzivatele`),
  KEY `IDX_vydal` (`vydal`),
  KEY `IDX_prijal` (`prijal`),
  CONSTRAINT `FK_kostym_vydej_kostym` FOREIGN KEY (`id_kostymu`)
    REFERENCES `kostym` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `FK_kostym_vydej_rezervace` FOREIGN KEY (`id_rezervace`)
    REFERENCES `kostym_rezervace` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_kostym_vydej_akce` FOREIGN KEY (`id_akce`)
    REFERENCES `akce_seznam` (`id_akce`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_kostym_vydej_uzivatel` FOREIGN KEY (`id_uzivatele`)
    REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE,
  CONSTRAINT `FK_kostym_vydej_vydal` FOREIGN KEY (`vydal`)
    REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `FK_kostym_vydej_prijal` FOREIGN KEY (`prijal`)
    REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_czech_ci
SQL);

// Nastavení: `vlastni = 1`, protože klíče nemají záznam v SystemoveNastaveni::dejVychoziHodnoty(),
// jinak by se konstanta při bootu definovala prázdná.
$this->q(<<<'SQL'
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, vlastni, datovy_typ, nazev, popis, skupina, poradi,
                                        pouze_pro_cteni, rocnik_nastaveni)
VALUES ('KOSTYMY_KATALOG_ZVEREJNEN', '0', 1, 'boolean', 'Katalog kostýmů je zveřejněný',
        'Zapne veřejnou stránku /kostymy a rezervace kostýmů účastníky. Správa půjčovny v adminu funguje i bez toho.',
        'Půjčovna kostýmů', 1, 0, -1),
       ('KOSTYMY_TYPY_AKTIVIT', '2', 1, 'text', 'Na jaké typy aktivit jde půjčit kostým',
        'ID typů aktivit oddělená čárkou (2 = larp, 4 = RPG, …).',
        'Půjčovna kostýmů', 2, 0, -1),
       ('KOSTYMY_LIMIT_NA_OSOBU', '0', 1, 'integer', 'Kolik kostýmů si jeden člověk rezervuje za ročník',
        'Nejvýš tolik rezervací kostýmů na aktivity jednoho ročníku. 0 = bez limitu. Správce půjčovny v adminu limit nehlídá.',
        'Půjčovna kostýmů', 3, 0, -1),
       ('KOSTYMY_CAS_NA_VYMENU_MINUT', '60', 1, 'integer', 'Čas na výměnu kostýmu mezi rezervacemi (minuty)',
        'Kolik minut po konci aktivity zůstane kostým volný, než ho může mít další rezervace.',
        'Půjčovna kostýmů', 4, 0, -1),
       ('KOSTYMY_VYDEJ_MINUT_PRED_ZACATKEM', '60', 1, 'integer', 'Kdy nejdřív jde vydat kostým (minuty před začátkem)',
        'Kolik minut před začátkem aktivity si účastník může kostým vyzvednout.',
        'Půjčovna kostýmů', 5, 0, -1),
       ('KOSTYMY_VRACENI_MINUT_PO_KONCI', '60', 1, 'integer', 'Dokdy se vrací kostým (minuty po konci)',
        'Kolik minut po konci aktivity má účastník na vrácení kostýmu. Potom je kostým v přehledu nevrácených.',
        'Půjčovna kostýmů', 6, 0, -1),
       ('KOSTYMY_UZAVERKA_HODIN_PRED_ZACATKEM', '2', 1, 'integer', 'Dokdy jde zrušit nebo vyměnit kostým (hodiny před začátkem)',
        'Potom už rezervaci kostýmu zruší nebo vymění jen správce půjčovny v adminu.',
        'Půjčovna kostýmů', 7, 0, -1)
SQL);

// ---------- právo: ADMINISTRACE_PUJCOVNA_KOSTYMU (115) ----------
$this->q(<<<'SQL'
INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES (115, 'Administrace - panel Půjčovna kostýmů', 'Katalog kostýmů, výdej a vrácení, rezervace kostýmů za účastníky')
ON DUPLICATE KEY UPDATE
    jmeno_prava = VALUES(jmeno_prava),
    popis_prava = VALUES(popis_prava)
SQL);

// ---------- role: SPRAVCE_PUJCOVNY_KOSTYMU (31) ----------
$this->q(<<<'SQL'
INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role, skryta,
                         kategorie_role)
VALUES (31, 'SPRAVCE_PUJCOVNY_KOSTYMU', 'Správce půjčovny kostýmů',
        'Spravuje katalog kostýmů, vydává a přijímá kostýmy, rezervuje kostýmy za účastníky', -1, 'trvala',
        'SPRAVCE_PUJCOVNY_KOSTYMU', 0, 1)
ON DUPLICATE KEY UPDATE
    kod_role = VALUES(kod_role),
    nazev_role = VALUES(nazev_role),
    popis_role = VALUES(popis_role),
    rocnik_role = VALUES(rocnik_role),
    typ_role = VALUES(typ_role),
    vyznam_role = VALUES(vyznam_role),
    skryta = VALUES(skryta),
    kategorie_role = VALUES(kategorie_role)
SQL);

$this->q(<<<'SQL'
INSERT INTO prava_role (id_role, id_prava)
VALUES (31, 115)
ON DUPLICATE KEY UPDATE id_role = id_role
SQL);
