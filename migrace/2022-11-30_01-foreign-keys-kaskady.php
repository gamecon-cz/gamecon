<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_organizatori
    DROP FOREIGN KEY akce_organizatori_ibfk_1,
    DROP FOREIGN KEY akce_organizatori_ibfk_2
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_organizatori
    ADD CONSTRAINT FK_akce_organizatori_to_akce_seznam FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`)
        ON UPDATE CASCADE ON DELETE CASCADE,
    ADD CONSTRAINT FK_akce_organizatori_to_uzivatele_hodnoty FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
        ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni
    DROP FOREIGN KEY `akce_prihlaseni_ibfk_1`,
    DROP FOREIGN KEY `akce_prihlaseni_ibfk_2`,
    DROP FOREIGN KEY `akce_prihlaseni_ibfk_3`
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni
    ADD CONSTRAINT `FK_akce_prihlaseni_to_akce_seznam` FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT `FK_akce_prihlaseni_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT `FK_akce_prihlaseni_to_akce_prihlaseni_stavy` FOREIGN KEY (`id_stavu_prihlaseni`) REFERENCES `akce_prihlaseni_stavy` (`id_stavu_prihlaseni`)
        ON UPDATE CASCADE ON DELETE RESTRICT
SQL
);

$this->q(<<<SQL
DELETE akce_prihlaseni_log.*
FROM akce_prihlaseni_log
LEFT JOIN akce_seznam on akce_prihlaseni_log.id_akce = akce_seznam.id_akce
LEFT JOIN uzivatele_hodnoty on akce_prihlaseni_log.id_uzivatele = uzivatele_hodnoty.id_uzivatele
WHERE akce_seznam.id_akce IS NULL
    OR uzivatele_hodnoty.id_uzivatele IS NULL
SQL
);

$this->q(<<<SQL
DELETE akce_prihlaseni_log.*
FROM akce_prihlaseni_log
LEFT JOIN uzivatele_hodnoty on akce_prihlaseni_log.id_zmenil = uzivatele_hodnoty.id_uzivatele
WHERE akce_prihlaseni_log.id_zmenil IS NOT NULL
  AND uzivatele_hodnoty.id_uzivatele IS NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_log
    ADD PRIMARY KEY (id_log)
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_log
    DROP KEY id_log,
    DROP KEY i_akce_prihlaseni_log_id_akce,
    DROP KEY i_akce_prihlaseni_log_id_uzivatele
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_log
    ENGINE=InnoDB
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_log
    ADD CONSTRAINT FK_akce_prihlaseni_log_to_akce_seznam FOREIGN KEY (id_akce) REFERENCES akce_seznam(id_akce)
        ON UPDATE CASCADE ON DELETE CASCADE,
    ADD CONSTRAINT FK_akce_prihlaseni_log_to_uzivatele_hodnoty FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele)
        ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_spec
    DROP FOREIGN KEY akce_prihlaseni_spec_ibfk_2,
    DROP FOREIGN KEY akce_prihlaseni_spec_ibfk_3,
    DROP FOREIGN KEY akce_prihlaseni_spec_ibfk_4
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_spec
    ADD CONSTRAINT FK_akce_prihlaseni_spec_to_uzivatele_hodnoty FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT FK_akce_prihlaseni_spec_to_akce_prihlaseni_stavy FOREIGN KEY (id_stavu_prihlaseni) REFERENCES akce_prihlaseni_stavy(id_stavu_prihlaseni)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT FK_akce_prihlaseni_spec_to_akce_seznam FOREIGN KEY (id_akce) REFERENCES akce_seznam(id_akce)
        ON UPDATE CASCADE ON DELETE RESTRICT
SQL
);

$this->dropForeignKeysIfExist(
    ['akce_seznam_ibfk_1', 'akce_seznam_ibfk_2', 'akce_seznam_ibfk_3', 'FK_akce_seznam_to_akce_instance', 'FK_akce_seznam_to_akce_stav'],
    'akce_seznam'
);

$this->q(<<<SQL
ALTER TABLE akce_seznam
    ADD CONSTRAINT FK_akce_seznam_to_popis FOREIGN KEY (popis) REFERENCES texty(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT FK_akce_seznam_to_akce_instance FOREIGN KEY (patri_pod) REFERENCES akce_instance(id_instance) ON UPDATE CASCADE ON DELETE SET NULL,
    ADD CONSTRAINT FK_akce_seznam_to_akce_stav FOREIGN KEY (stav) REFERENCES akce_stav(id_stav) ON UPDATE CASCADE ON DELETE RESTRICT
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_sjednocene_tagy
    MODIFY COLUMN id_tagu INT UNSIGNED,
    DROP KEY id_tagu
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_sjednocene_tagy
    ADD CONSTRAINT FK_akce_sjednocene_tagy_to_sjednocene_tagy FOREIGN KEY (id_tagu) REFERENCES sjednocene_tagy(id) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_stavy_log
    DROP FOREIGN KEY akce_stavy_log_ibfk_1,
    DROP FOREIGN KEY akce_stavy_log_ibfk_2,
    DROP KEY id_akce,
    DROP KEY id_stav
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_stavy_log
    ADD CONSTRAINT FK_akce_stavy_log_to_akce_seznam FOREIGN KEY (id_akce) REFERENCES akce_seznam(id_akce) ON UPDATE CASCADE ON DELETE CASCADE,
    ADD CONSTRAINT FK_akce_stavy_log_to_akce_stav FOREIGN KEY (id_stav) REFERENCES akce_stav(id_stav) ON UPDATE CASCADE ON DELETE RESTRICT
SQL
);

$this->q(<<<SQL
DROP TABLE IF EXISTS akce_tagy -- replaced by akce_sjednocene_tagy two years back
SQL
);

$this->dropForeignKeysIfExist(['akce_typy_ibfk_1', 'akce_typy_ibfk_2'], 'akce_typy');

$this->q(<<<SQL
ALTER TABLE akce_typy
    MODIFY COLUMN typ_1p VARCHAR(32) NOT NULL,
    MODIFY COLUMN typ_1pmn VARCHAR(32) NOT NULL,
    MODIFY COLUMN url_typu_mn VARCHAR(32) NOT NULL,
    MODIFY COLUMN popis_kratky VARCHAR(255) NOT NULL,
    DROP KEY stranka_o
SQL
);

$this->q(<<<SQL
ALTER TABLE akce_typy
    ADD CONSTRAINT FK_akce_typy_to_stranka_o FOREIGN KEY (stranka_o) REFERENCES stranky (id_stranky) ON UPDATE CASCADE ON DELETE RESTRICT
SQL
);

$this->dropForeignKeysIfExist(['google_api_user_tokens_ibfk_1', 'FK_google_api_user_tokens_to_uzivatele_hodnoty'], 'google_api_user_tokens');

$this->q(<<<SQL
ALTER TABLE google_api_user_tokens
    ADD CONSTRAINT FK_google_api_user_tokens_to_uzivatele_hodnoty FOREIGN KEY (user_id) REFERENCES uzivatele_hodnoty (id_uzivatele) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->dropForeignKeysIfExist(['google_drive_dirs_ibfk_1', 'FK_google_drive_dirs_to_uzivatele_hodnoty'], 'google_drive_dirs');

$this->q(<<<SQL
ALTER TABLE google_drive_dirs
    MODIFY COLUMN dir_id VARCHAR(128) NOT NULL,
    MODIFY COLUMN original_name VARCHAR(64) NOT NULL,
    MODIFY COLUMN tag VARCHAR(128) NOT NULL DEFAULT ''
SQL
);

$this->q(<<<SQL
ALTER TABLE google_drive_dirs
    ADD CONSTRAINT FK_google_drive_dirs_to_uzivatele_hodnoty FOREIGN KEY (`user_id`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
ALTER TABLE kategorie_sjednocenych_tagu
    MODIFY COLUMN nazev VARCHAR(128) NOT NULL,
    DROP CONSTRAINT `kategorie_sjednocenych_tagu_ibfk_1`,
    DROP KEY `id_hlavni_kategorie`
SQL
);

$this->q(<<<SQL
ALTER TABLE kategorie_sjednocenych_tagu
    ADD CONSTRAINT FK_kategorie_sjednocenych_tagu_to_kategorie_sjednocenych_tagu FOREIGN KEY (`id_hlavni_kategorie`) REFERENCES `kategorie_sjednocenych_tagu` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
ALTER TABLE log_udalosti
    DROP CONSTRAINT `log_udalosti_ibfk_1`,
    DROP KEY `id_logujiciho`
SQL
);

$this->q(<<<SQL
ALTER TABLE log_udalosti
    ADD CONSTRAINT FK_log_udalosti_to_uzivatele_hodnoty FOREIGN KEY (`id_logujiciho`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
ALTER TABLE `medailonky`
    CHANGE COLUMN `id` id_uzivatele int(11) NOT NULL,
    MODIFY COLUMN `o_sobe` MEDIUMTEXT NOT NULL COMMENT 'markdown',
    MODIFY COLUMN `drd` MEDIUMTEXT NOT NULL COMMENT 'markdown -- profil pro DrD',
    DROP FOREIGN KEY `medailonky_ibfk_1`
SQL
);

$this->q(<<<SQL
ALTER TABLE medailonky
    ADD CONSTRAINT FK_medailonky_to_uzivatele_hodnoty FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->dropForeignKeysIfExist(['mutex_ibfk_1', 'FK_mutex_to_uzivatele_hodnoty'], 'mutex');

$this->q(<<<SQL
ALTER TABLE mutex
    MODIFY COLUMN `akce` VARCHAR(128) NOT NULL,
    MODIFY COLUMN `klic` VARCHAR(128) NOT NULL,
    DROP KEY `FK_mutex_to_uzivatele_hodnoty`
SQL
);

$this->q(<<<SQL
ALTER TABLE mutex
    ADD CONSTRAINT FK_mutex_to_uzivatele_hodnoty FOREIGN KEY (`zamknul`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE SET NULL ON UPDATE CASCADE
SQL
);

$this->q(<<<SQL
ALTER TABLE novinky
    MODIFY COLUMN `url` VARCHAR(100) NOT NULL,
    MODIFY COLUMN `nazev` VARCHAR(200) NOT NULL,
    MODIFY COLUMN `autor` VARCHAR(100) DEFAULT NULL,
    DROP KEY `text`,
    DROP FOREIGN KEY novinky_ibfk_1
SQL
);

$this->q(<<<SQL
ALTER TABLE novinky
    ADD CONSTRAINT `FK_novinky_to_texty` FOREIGN KEY (`text`) REFERENCES `texty` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
SQL
);

$this->q(<<<SQL
ALTER TABLE obchod_bunky
    MODIFY COLUMN `text` VARCHAR(255) DEFAULT NULL,
    MODIFY COLUMN `barva` VARCHAR(255) DEFAULT NULL,
    DROP FOREIGN KEY `obchod_bunky_fk_mrizky`,
    DROP KEY `obchod_bunky_fk_mrizky`
SQL
);

$this->q(<<<SQL
ALTER TABLE obchod_bunky
    ADD CONSTRAINT FK_obchod_bunky_to_obchod_mrizky FOREIGN KEY (mrizka_id) REFERENCES obchod_mrizky(id)
SQL
);

$this->q(<<<SQL
ALTER TABLE obchod_mrizky
    MODIFY COLUMN `text` VARCHAR(255) DEFAULT NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE platby
    MODIFY COLUMN `poznamka` TEXT DEFAULT NULL,
    DROP FOREIGN KEY `platby_ibfk_1`
SQL
);

$this->q(<<<SQL
ALTER TABLE platby
    ADD CONSTRAINT `FK_platby_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE RESTRICT
SQL
);

$this->q(<<<SQL
ALTER TABLE r_prava_soupis
    MODIFY COLUMN `jmeno_prava` VARCHAR(255) NOT NULL,
    MODIFY COLUMN `popis_prava` TEXT NOT NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE r_prava_zidle
    DROP FOREIGN KEY `r_prava_zidle_ibfk_1`,
    DROP FOREIGN KEY `r_prava_zidle_ibfk_2`
SQL
);

$this->q(<<<SQL
ALTER TABLE r_prava_zidle
     ADD CONSTRAINT `FK_r_prava_zidle_to_r_prava_soupis` FOREIGN KEY (`id_prava`) REFERENCES `r_prava_soupis` (`id_prava`) ON UPDATE CASCADE ON DELETE CASCADE,
     ADD CONSTRAINT `FK_r_prava_zidle_to_r_zidle_soupis` FOREIGN KEY (`id_zidle`) REFERENCES `r_zidle_soupis` (`id_zidle`) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle
    DROP FOREIGN KEY `r_uzivatele_zidle_ibfk_2`,
    DROP FOREIGN KEY `r_uzivatele_zidle_ibfk_3`,
    DROP FOREIGN KEY `r_uzivatele_zidle_ibfk_4`
SQL
);

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle_log
    DROP FOREIGN KEY `r_uzivatele_zidle_log_ibfk_1`,
    DROP FOREIGN KEY `r_uzivatele_zidle_log_ibfk_2`,
    DROP FOREIGN KEY `r_uzivatele_zidle_log_ibfk_3`,
    MODIFY COLUMN `zmena` VARCHAR(128) NOT NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle_log
    ADD CONSTRAINT `FK_r_uzivatele_zidle_log_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE CASCADE,
    ADD CONSTRAINT `FK_r_uzivatele_zidle_log_to_r_zidle_soupis` FOREIGN KEY (`id_zidle`) REFERENCES `r_zidle_soupis` (`id_zidle`) ON UPDATE CASCADE ON DELETE CASCADE,
    ADD CONSTRAINT `FK_r_uzivatele_zidle_log_zmenil_to_uzivatele_hodnoty` FOREIGN KEY (`id_zmenil`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE SET NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
    MODIFY `jmeno_zidle` VARCHAR(255) NOT NULL,
    MODIFY `popis_zidle` TEXT NOT NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE reporty
    MODIFY COLUMN `skript` VARCHAR(100) NOT NULL,
    MODIFY COLUMN `nazev` VARCHAR(200) DEFAULT NULL
SQL
);

$this->dropForeignKeysIfExist(['reporty_log_pouziti_ibfk_1', 'reporty_log_pouziti_ibfk_2'], 'reporty_log_pouziti');

$this->q(<<<SQL
ALTER TABLE reporty_log_pouziti
    MODIFY COLUMN `format`       VARCHAR(10) NOT NULL,
    MODIFY COLUMN `casova_zona`  VARCHAR(100) DEFAULT NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE reporty_log_pouziti
    ADD CONSTRAINT `FK_reporty_log_pouziti_to_reporty` FOREIGN KEY (`id_reportu`) REFERENCES `reporty` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
    ADD CONSTRAINT `FK_reporty_log_pouziti_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
ALTER TABLE reporty_quick
    MODIFY COLUMN `nazev` VARCHAR(100) NOT NULL,
    MODIFY COLUMN `dotaz` TEXT NOT NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE `shop_nakupy`
    DROP FOREIGN KEY `shop_nakupy_ibfk_1`,
    DROP FOREIGN KEY `shop_nakupy_ibfk_2`
SQL
);

$this->q(<<<SQL
ALTER TABLE `shop_nakupy`
    ADD CONSTRAINT `FK_shop_nakupy_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE RESTRICT,
    ADD CONSTRAINT `FK_shop_nakupy_to_shop_predmety` FOREIGN KEY (`id_predmetu`) REFERENCES `shop_predmety` (`id_predmetu`) ON UPDATE CASCADE ON DELETE RESTRICT
SQL
);

$this->q(<<<SQL
ALTER TABLE shop_predmety
    MODIFY COLUMN `nazev` VARCHAR(255) NOT NULL,
    MODIFY COLUMN `popis` VARCHAR(2000) NOT NULL
SQL
);

$this->dropForeignKeysIfExist(['sjednocene_tagy_ibfk_1', 'FK_kategorie_sjednocenych_tagu_62'], 'sjednocene_tagy');

$this->q(<<<SQL
ALTER TABLE `sjednocene_tagy`
    MODIFY COLUMN `nazev` VARCHAR(128) NOT NULL,
    MODIFY COLUMN `poznamka` TEXT NOT NULL,
    DROP KEY `FK_kategorie_sjednocenych_tagu_62`
SQL
);

$this->q(<<<SQL
ALTER TABLE `sjednocene_tagy`
    ADD CONSTRAINT `FK_sjednocene_tagy_to_kategorie_sjednocenych_tagu` FOREIGN KEY (`id_kategorie_tagu`) REFERENCES `kategorie_sjednocenych_tagu` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
DROP TABLE IF EXISTS sjednocene_tagy_bug_058_zaloha;
DROP TABLE IF EXISTS kategorie_sjednocenych_tagu_bug_058_zaloha;
DROP TABLE IF EXISTS akce_sjednocene_tagy_bug_058_zaloha;
SQL
);

$this->q(<<<SQL
ALTER TABLE `slevy`
    MODIFY COLUMN `provedl`  INT NULL,
    MODIFY COLUMN `poznamka` TEXT DEFAULT NULL,
    DROP FOREIGN KEY `slevy_ibfk_1`,
    DROP FOREIGN KEY `slevy_ibfk_2`
SQL
);

$this->q(<<<SQL
ALTER TABLE `slevy`
    ADD CONSTRAINT `FK_slevy_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE CASCADE,
    ADD CONSTRAINT `FK_slevy_provedl_to_uzivatele_hodnoty` FOREIGN KEY (`provedl`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE SET NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE `stranky`
    MODIFY COLUMN `url_stranky` VARCHAR(64) NOT NULL,
    MODIFY COLUMN `obsah` LONGTEXT NOT NULL COMMENT 'markdown'
SQL
);

$this->dropForeignKeysIfExist(
    ['systemove_nastaveni_log_ibfk_1', 'systemove_nastaveni_log_ibfk_2', '', 'systemove_nastaveni_log_to_systemove_nastaveni', 'systemove_nastaveni_log_to_uzivatele_hodnoty'],
    'systemove_nastaveni_log'
);

$this->q(<<<SQL
ALTER TABLE `systemove_nastaveni_log`
    DROP KEY `systemove_nastaveni_log_to_systemove_nastaveni`,
    DROP KEY `systemove_nastaveni_log_to_uzivatele_hodnoty`
SQL
);

$this->q(<<<SQL
ALTER TABLE `systemove_nastaveni_log`
    ADD CONSTRAINT `FK_systemove_nastaveni_log_to_systemove_nastaveni` FOREIGN KEY (`id_nastaveni`) REFERENCES `systemove_nastaveni` (`id_nastaveni`) ON UPDATE CASCADE ON DELETE CASCADE,
    ADD CONSTRAINT `FK_systemove_nastaveni_log_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE SET NULL
SQL
);

$this->q(<<<SQL
DROP TABLE `tagy`
SQL
);

$this->q(<<<SQL
ALTER TABLE `ubytovani`
    MODIFY COLUMN `pokoj` VARCHAR(255) NOT NULL,
    DROP FOREIGN KEY `ubytovani_ibfk_1`
SQL
);

$this->q(<<<SQL
ALTER TABLE `ubytovani`
    ADD CONSTRAINT `FK_ubytovani_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
ALTEr TABLE `uzivatele_hodnoty`
  MODIFY COLUMN `login_uzivatele` VARCHAR(255) NOT NULL,
  MODIFY COLUMN `jmeno_uzivatele` VARCHAR(100) NOT NULL,
  MODIFY COLUMN `prijmeni_uzivatele` VARCHAR(100) NOT NULL,
  MODIFY COLUMN `ulice_a_cp_uzivatele` VARCHAR(255) NOT NULL,
  MODIFY COLUMN `mesto_uzivatele` VARCHAR(100) NOT NULL,
  MODIFY COLUMN `psc_uzivatele` VARCHAR(20) NOT NULL,
  MODIFY COLUMN `telefon_uzivatele` VARCHAR(100) NOT NULL,
  MODIFY COLUMN `heslo_md5` VARCHAR(255) CHARACTER SET ucs2 COLLATE ucs2_czech_ci NOT NULL COMMENT 'přechází se na password_hash',
  MODIFY COLUMN `email1_uzivatele` VARCHAR(255) NOT NULL,
  MODIFY COLUMN `email2_uzivatele` VARCHAR(255) NOT NULL,
  MODIFY COLUMN `jine_uzivatele` TEXT NOT NULL,
  MODIFY COLUMN `forum_razeni` VARCHAR(1) NOT NULL,
  MODIFY COLUMN `random` VARCHAR(20) NOT NULL,
  MODIFY COLUMN `pohlavi` ENUM('m','f') NOT NULL,
  MODIFY COLUMN `ubytovan_s` VARCHAR(255) DEFAULT NULL,
  MODIFY COLUMN `skola` VARCHAR(255) DEFAULT NULL,
  MODIFY COLUMN `poznamka` VARCHAR(4096) NOT NULL,
  MODIFY COLUMN `pomoc_typ` VARCHAR(64) NOT NULL,
  MODIFY COLUMN `pomoc_vice` TEXT NOT NULL,
  MODIFY COLUMN `op` VARCHAR(4096) NOT NULL COMMENT 'zašifrované číslo OP',
  MODIFY COLUMN `infopult_poznamka` VARCHAR(128) NOT NULL DEFAULT ''
SQL
);

$this->q(<<<SQL
ALTER TABLE `uzivatele_url`
    MODIFY COLUMN `url` VARCHAR(255) NOT NULL,
    DROP FOREIGN KEY `uzivatele_url_ibfk_1`
SQL
);

$this->q(<<<SQL
ALTER TABLE `uzivatele_url`
    ADD CONSTRAINT `FK_uzivatele_url_to_uzivatele_hodnoty` FOREIGN KEY (`id_uzivatele`) REFERENCES    `uzivatele_hodnoty` (`id_uzivatele`) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);
