ALTER TABLE akce_organizatori
    DROP FOREIGN KEY IF EXISTS FK_akce_organizatori_to_akce_seznam;
ALTER TABLE akce_organizatori
    DROP FOREIGN KEY IF EXISTS FK_akce_organizatori_to_uzivatele_hodnoty;

ALTER TABLE akce_seznam
    DROP FOREIGN KEY IF EXISTS FK_akce_seznam_akce_lokace;
ALTER TABLE akce_seznam
    DROP FOREIGN KEY IF EXISTS FK_akce_seznam_to_popis;
ALTER TABLE akce_seznam
    DROP FOREIGN KEY IF EXISTS FK_akce_seznam_to_akce_instance;
ALTER TABLE akce_seznam
    DROP FOREIGN KEY IF EXISTS FK_akce_seznam_zamcel_to_uzivatele_hodnoty;
ALTER TABLE akce_seznam
    DROP FOREIGN KEY IF EXISTS FK_akce_seznam_to_akce_stav;

ALTER TABLE ubytovani
    DROP FOREIGN KEY IF EXISTS FK_ubytovani_to_uzivatele_hodnoty;

ALTER TABLE akce_instance
    DROP FOREIGN KEY IF EXISTS akce_instance_ibfk_1;

ALTER TABLE akce_import
    DROP FOREIGN KEY IF EXISTS akce_import_ibfk_1;

ALTER TABLE akce_prihlaseni
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_to_akce_prihlaseni_stavy;
ALTER TABLE akce_prihlaseni
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_to_akce_seznam;
ALTER TABLE akce_prihlaseni
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_to_uzivatele_hodnoty;

ALTER TABLE akce_prihlaseni_log
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_log_to_akce_seznam;
ALTER TABLE akce_prihlaseni_log
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_log_to_uzivatele_hodnoty;

ALTER TABLE akce_prihlaseni_spec
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_spec_to_akce_prihlaseni_stavy;
ALTER TABLE akce_prihlaseni_spec
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_spec_to_akce_seznam;
ALTER TABLE akce_prihlaseni_spec
    DROP FOREIGN KEY IF EXISTS FK_akce_prihlaseni_spec_to_uzivatele_hodnoty;

ALTER TABLE akce_sjednocene_tagy
    DROP FOREIGN KEY IF EXISTS FK_akce_sjednocene_tagy_to_sjednocene_tagy;

ALTER TABLE hromadne_akce_log
    DROP FOREIGN KEY IF EXISTS FK_hromadne_akce_log_to_uzivatele_hodnoty;

ALTER TABLE akce_typy
    DROP FOREIGN KEY IF EXISTS FK_akce_typy_to_stranka_o;

ALTER TABLE kategorie_sjednocenych_tagu
    DROP FOREIGN KEY IF EXISTS FK_kategorie_sjednocenych_tagu_to_kategorie_sjednocenych_tagu;

ALTER TABLE akce_stavy_log
    DROP FOREIGN KEY IF EXISTS FK_akce_stavy_log_to_akce_seznam;

ALTER TABLE akce_seznam
    CHANGE id_akce id_akce BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE patri_pod patri_pod BIGINT UNSIGNED DEFAULT NULL,
    CHANGE lokace lokace BIGINT UNSIGNED DEFAULT NULL,
    CHANGE stav stav INT UNSIGNED NOT NULL,
    CHANGE zamcel zamcel BIGINT UNSIGNED DEFAULT NULL COMMENT 'případně kdo zamčel aktivitu pro svůj team',
    CHANGE popis popis BIGINT UNSIGNED NOT NULL,
    CHANGE typ typ BIGINT UNSIGNED NOT NULL,
    CHANGE vybaveni vybaveni LONGTEXT NOT NULL;

ALTER TABLE ubytovani
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE den den SMALLINT NOT NULL;

ALTER TABLE ubytovani RENAME INDEX id_uzivatele TO IDX_F483CEC3D84E9520;

DROP INDEX id_akce_import ON akce_import;
ALTER TABLE akce_import
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE cas cas DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    ADD PRIMARY KEY (id_akce_import);
ALTER TABLE akce_import
    ADD CONSTRAINT FK_D72EE2CDD84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE akce_import RENAME INDEX fk_akce_import_to_uzivatele_hodnoty TO IDX_D72EE2CDD84E9520;
ALTER TABLE akce_import RENAME INDEX google_sheet_id TO IDX_google_sheet_id;

ALTER TABLE akce_instance
    CHANGE id_instance id_instance BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_hlavni_akce id_hlavni_akce BIGINT UNSIGNED NOT NULL;
ALTER TABLE akce_instance
    ADD CONSTRAINT FK_F1D05242895FCA4C FOREIGN KEY (id_hlavni_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE;
ALTER TABLE akce_instance RENAME INDEX fk_akce_instance_to_akce_seznam TO IDX_F1D05242895FCA4C;

ALTER TABLE akce_organizatori
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL COMMENT 'organizátor';
ALTER TABLE akce_organizatori
    ADD CONSTRAINT FK_F44FC74E1E74DA0A FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE;
ALTER TABLE akce_organizatori
    ADD CONSTRAINT FK_F44FC74ED84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE akce_organizatori RENAME INDEX id_uzivatele TO IDX_F44FC74ED84E9520;

ALTER TABLE akce_prihlaseni
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_stavu_prihlaseni id_stavu_prihlaseni SMALLINT UNSIGNED NOT NULL;
ALTER TABLE akce_prihlaseni
    ADD CONSTRAINT FK_7B7E722B1E74DA0A FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE;
ALTER TABLE akce_prihlaseni
    ADD CONSTRAINT FK_7B7E722BD84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE akce_prihlaseni
    ADD CONSTRAINT FK_7B7E722B55D06BC9 FOREIGN KEY (id_stavu_prihlaseni) REFERENCES akce_prihlaseni_stavy (id_stavu_prihlaseni) ON DELETE CASCADE;
ALTER TABLE akce_prihlaseni RENAME INDEX id_uzivatele TO IDX_7B7E722BD84E9520;
ALTER TABLE akce_prihlaseni RENAME INDEX id_stavu_prihlaseni TO IDX_7B7E722B55D06BC9;

ALTER TABLE akce_prihlaseni_log
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE typ typ VARCHAR(64) DEFAULT NULL,
    CHANGE id_zmenil id_zmenil BIGINT UNSIGNED DEFAULT NULL;
ALTER TABLE akce_prihlaseni_log
    ADD CONSTRAINT FK_947919F21E74DA0A FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE;
ALTER TABLE akce_prihlaseni_log
    ADD CONSTRAINT FK_947919F2D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE akce_prihlaseni_log
    ADD CONSTRAINT FK_947919F2E2649593 FOREIGN KEY (id_zmenil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL;
ALTER TABLE akce_prihlaseni_log RENAME INDEX fk_akce_prihlaseni_log_to_akce_seznam TO IDX_947919F21E74DA0A;
ALTER TABLE akce_prihlaseni_log RENAME INDEX fk_akce_prihlaseni_log_to_uzivatele_hodnoty TO IDX_947919F2D84E9520;
ALTER TABLE akce_prihlaseni_log RENAME INDEX id_zmenil TO IDX_947919F2E2649593;
ALTER TABLE akce_prihlaseni_log RENAME INDEX typ TO IDX_typ;
ALTER TABLE akce_prihlaseni_log RENAME INDEX zdroj_zmeny TO IDX_zdroj_zmeny;

ALTER TABLE akce_prihlaseni_spec
    ADD id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_stavu_prihlaseni id_stavu_prihlaseni SMALLINT UNSIGNED NOT NULL,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id);
ALTER TABLE akce_prihlaseni_spec
    ADD CONSTRAINT FK_78A8F4401E74DA0A FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE;
ALTER TABLE akce_prihlaseni_spec
    ADD CONSTRAINT FK_78A8F440D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE akce_prihlaseni_spec
    ADD CONSTRAINT FK_78A8F44055D06BC9 FOREIGN KEY (id_stavu_prihlaseni) REFERENCES akce_prihlaseni_stavy (id_stavu_prihlaseni) ON DELETE CASCADE;
CREATE UNIQUE INDEX UNIQ_id_akce_id_uzivatele ON akce_prihlaseni_spec (id_akce, id_uzivatele);
ALTER TABLE akce_prihlaseni_spec RENAME INDEX id_uzivatele TO IDX_78A8F440D84E9520;
ALTER TABLE akce_prihlaseni_spec RENAME INDEX id_stavu_prihlaseni TO IDX_78A8F44055D06BC9;
ALTER TABLE akce_prihlaseni_stavy
    CHANGE id_stavu_prihlaseni id_stavu_prihlaseni SMALLINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE platba_procent platba_procent SMALLINT DEFAULT 100 NOT NULL;
ALTER TABLE akce_stav
    MODIFY id_stav INT NOT NULL;
DROP INDEX id_stav ON akce_stav;
DROP INDEX `primary` ON akce_stav;
ALTER TABLE akce_stav
    CHANGE id_stav id_stav INT UNSIGNED AUTO_INCREMENT NOT NULL;
CREATE UNIQUE INDEX UNIQ_nazev ON akce_stav (nazev);
ALTER TABLE akce_stav
    ADD PRIMARY KEY (id_stav);

ALTER TABLE akce_sjednocene_tagy
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE id_tagu id_tagu BIGINT UNSIGNED NOT NULL;
ALTER TABLE akce_sjednocene_tagy
    ADD CONSTRAINT FK_714E29671E74DA0A FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE;
ALTER TABLE akce_sjednocene_tagy
    ADD CONSTRAINT FK_714E2967DFF2D11 FOREIGN KEY (id_tagu) REFERENCES sjednocene_tagy (id) ON DELETE CASCADE;
CREATE INDEX IDX_714E29671E74DA0A ON akce_sjednocene_tagy (id_akce);
ALTER TABLE akce_sjednocene_tagy RENAME INDEX fk_akce_sjednocene_tagy_to_sjednocene_tagy TO IDX_714E2967DFF2D11;

ALTER TABLE akce_typy
    CHANGE id_typu id_typu BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE stranka_o stranka_o INT UNSIGNED NOT NULL;
ALTER TABLE akce_typy
    ADD CONSTRAINT FK_C12F7955DC7C4C42 FOREIGN KEY (stranka_o) REFERENCES stranky (id_stranky) ON DELETE RESTRICT;
ALTER TABLE akce_typy RENAME INDEX fk_akce_typy_to_stranka_o TO IDX_C12F7955DC7C4C42;

DROP INDEX id_logu ON hromadne_akce_log;
ALTER TABLE hromadne_akce_log
    CHANGE provedl provedl BIGINT UNSIGNED DEFAULT NULL,
    ADD PRIMARY KEY (id_logu);
ALTER TABLE hromadne_akce_log
    ADD CONSTRAINT FK_E0A93D8A69513658 FOREIGN KEY (provedl) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL;
ALTER TABLE hromadne_akce_log RENAME INDEX fk_hromadne_akce_log_to_uzivatele_hodnoty TO IDX_E0A93D8A69513658;
ALTER TABLE hromadne_akce_log RENAME INDEX akce TO IDX_akce;
ALTER TABLE kategorie_sjednocenych_tagu
    MODIFY id INT UNSIGNED NOT NULL;

DROP INDEX id ON kategorie_sjednocenych_tagu;
DROP INDEX `primary` ON kategorie_sjednocenych_tagu;
ALTER TABLE kategorie_sjednocenych_tagu
    CHANGE id_hlavni_kategorie id_hlavni_kategorie BIGINT UNSIGNED DEFAULT NULL,
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL;
ALTER TABLE kategorie_sjednocenych_tagu
    ADD CONSTRAINT FK_A82F4189FF2287A1 FOREIGN KEY (id_hlavni_kategorie) REFERENCES kategorie_sjednocenych_tagu (id) ON DELETE SET NULL;
CREATE INDEX IDX_nazev ON kategorie_sjednocenych_tagu (nazev);
ALTER TABLE kategorie_sjednocenych_tagu
    ADD PRIMARY KEY (id);
ALTER TABLE kategorie_sjednocenych_tagu RENAME INDEX fk_kategorie_sjednocenych_tagu_to_kategorie_sjednocenych_tagu TO IDX_A82F4189FF2287A1;
ALTER TABLE slevy
    DROP FOREIGN KEY IF EXISTS FK_slevy_provedl_to_uzivatele_hodnoty;
ALTER TABLE slevy
    DROP FOREIGN KEY IF EXISTS FK_slevy_to_uzivatele_hodnoty;
ALTER TABLE slevy
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE provedl provedl BIGINT UNSIGNED DEFAULT NULL,
    CHANGE poznamka poznamka LONGTEXT DEFAULT NULL;
ALTER TABLE slevy
    ADD CONSTRAINT FK_17003B9AD84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE slevy
    ADD CONSTRAINT FK_17003B9A69513658 FOREIGN KEY (provedl) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL;
ALTER TABLE slevy RENAME INDEX id_uzivatele TO IDX_17003B9AD84E9520;
ALTER TABLE slevy RENAME INDEX provedl TO IDX_17003B9A69513658;
ALTER TABLE log_udalosti
    DROP FOREIGN KEY IF EXISTS FK_log_udalosti_to_uzivatele_hodnoty;
DROP INDEX id_udalosti ON log_udalosti;
ALTER TABLE log_udalosti
    CHANGE id_logujiciho id_logujiciho BIGINT UNSIGNED NOT NULL,
    ADD PRIMARY KEY (id_udalosti);
ALTER TABLE log_udalosti
    ADD CONSTRAINT FK_459DF155498E1820 FOREIGN KEY (id_logujiciho) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE log_udalosti RENAME INDEX fk_log_udalosti_to_uzivatele_hodnoty TO IDX_459DF155498E1820;
ALTER TABLE log_udalosti RENAME INDEX metadata TO IDX_metadata;
ALTER TABLE google_api_user_tokens
    MODIFY id INT UNSIGNED NOT NULL;
ALTER TABLE google_api_user_tokens
    DROP FOREIGN KEY IF EXISTS FK_google_api_user_tokens_to_uzivatele_hodnoty;
DROP INDEX id ON google_api_user_tokens;
DROP INDEX `primary` ON google_api_user_tokens;
ALTER TABLE google_api_user_tokens
    CHANGE user_id user_id BIGINT UNSIGNED NOT NULL,
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE tokens tokens LONGTEXT NOT NULL;
ALTER TABLE google_api_user_tokens
    ADD CONSTRAINT FK_9A526EB4A76ED395 FOREIGN KEY (user_id) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
CREATE UNIQUE INDEX UNIQ_user_id_google_client_id ON google_api_user_tokens (user_id, google_client_id);
ALTER TABLE google_api_user_tokens
    ADD PRIMARY KEY (id);
ALTER TABLE google_drive_dirs
    MODIFY id INT UNSIGNED NOT NULL;
ALTER TABLE google_drive_dirs
    DROP FOREIGN KEY IF EXISTS FK_google_drive_dirs_to_uzivatele_hodnoty;
DROP INDEX id ON google_drive_dirs;
DROP INDEX `primary` ON google_drive_dirs;
ALTER TABLE google_drive_dirs
    CHANGE user_id user_id BIGINT UNSIGNED NOT NULL,
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL;
ALTER TABLE google_drive_dirs
    ADD CONSTRAINT FK_9E13BEAFA76ED395 FOREIGN KEY (user_id) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
CREATE UNIQUE INDEX UNIQ_dir_id ON google_drive_dirs (dir_id);
ALTER TABLE google_drive_dirs
    ADD PRIMARY KEY (id);
ALTER TABLE google_drive_dirs RENAME INDEX tag TO IDX_tag;
ALTER TABLE google_drive_dirs RENAME INDEX user_and_name TO UNIQ_user_and_name;
ALTER TABLE akce_lokace
    CHANGE id_lokace id_lokace BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE poznamka poznamka LONGTEXT NOT NULL;
ALTER TABLE akce_lokace RENAME INDEX nazev_rok TO UNIQ_nazev_rok;
ALTER TABLE novinky
    DROP FOREIGN KEY IF EXISTS FK_novinky_to_texty;
DROP INDEX FK_novinky_to_texty ON novinky;
ALTER TABLE novinky
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE typ typ SMALLINT DEFAULT 1 NOT NULL COMMENT '1-novinka 2-blog';
ALTER TABLE novinky RENAME INDEX url TO UNIQ_url;
ALTER TABLE newsletter_prihlaseni
    CHANGE id_newsletter_prihlaseni id_newsletter_prihlaseni BIGINT UNSIGNED AUTO_INCREMENT NOT NULL;
ALTER TABLE newsletter_prihlaseni RENAME INDEX email TO UNIQ_email;
ALTER TABLE newsletter_prihlaseni_log
    CHANGE id_newsletter_prihlaseni_log id_newsletter_prihlaseni_log BIGINT UNSIGNED AUTO_INCREMENT NOT NULL;
ALTER TABLE newsletter_prihlaseni_log RENAME INDEX email TO IDX_email;
ALTER TABLE stranky
    CHANGE id_stranky id_stranky INT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE poradi poradi INT UNSIGNED DEFAULT 0 NOT NULL;
ALTER TABLE stranky RENAME INDEX url_stranky TO UNIQ_3D4EE408803DB254;
ALTER TABLE platby
    DROP FOREIGN KEY IF EXISTS FK_platby_id_uzivatele_to_uzivatele_hodnoty;
ALTER TABLE platby
    DROP FOREIGN KEY IF EXISTS FK_platby_provedl_to_uzivatele_hodnoty;
ALTER TABLE platby
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED DEFAULT NULL,
    CHANGE provedl provedl BIGINT UNSIGNED NOT NULL,
    CHANGE provedeno provedeno DATETIME NOT NULL,
    CHANGE poznamka poznamka LONGTEXT DEFAULT NULL,
    CHANGE skryta_poznamka skryta_poznamka LONGTEXT DEFAULT NULL;
ALTER TABLE platby
    ADD CONSTRAINT FK_4852A679D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL;
ALTER TABLE platby
    ADD CONSTRAINT FK_4852A67969513658 FOREIGN KEY (provedl) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE RESTRICT;
ALTER TABLE platby RENAME INDEX fk_platby_provedl_to_uzivatele_hodnoty TO IDX_4852A67969513658;
ALTER TABLE platby RENAME INDEX id_uzivatele_rok TO IDX_id_uzivatele_rok;
ALTER TABLE platby RENAME INDEX fio_id TO UNIQ_fio_id;
ALTER TABLE r_prava_soupis
    CHANGE id_prava id_prava BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE popis_prava popis_prava LONGTEXT NOT NULL;
ALTER TABLE reporty_quick
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE dotaz dotaz LONGTEXT NOT NULL,
    CHANGE format_xlsx format_xlsx TINYINT(1) DEFAULT 1 NOT NULL,
    CHANGE format_html format_html TINYINT(1) DEFAULT 1 NOT NULL;
ALTER TABLE reporty
    MODIFY id INT UNSIGNED NOT NULL;
DROP INDEX id ON reporty;
DROP INDEX `primary` ON reporty;
ALTER TABLE reporty
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL;
CREATE UNIQUE INDEX UNIQ_skript ON reporty (skript);
ALTER TABLE reporty
    ADD PRIMARY KEY (id);
ALTER TABLE reporty_log_pouziti
    DROP FOREIGN KEY IF EXISTS FK_reporty_log_pouziti_to_reporty;
ALTER TABLE reporty_log_pouziti
    DROP FOREIGN KEY IF EXISTS FK_reporty_log_pouziti_to_uzivatele_hodnoty;
DROP INDEX id ON reporty_log_pouziti;
ALTER TABLE reporty_log_pouziti
    CHANGE id_reportu id_reportu BIGINT UNSIGNED NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    ADD PRIMARY KEY (id);
ALTER TABLE reporty_log_pouziti
    ADD CONSTRAINT FK_FEAC86E4C6E1AB00 FOREIGN KEY (id_reportu) REFERENCES reporty (id) ON DELETE CASCADE;
ALTER TABLE reporty_log_pouziti
    ADD CONSTRAINT FK_FEAC86E4D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE reporty_log_pouziti RENAME INDEX id_uzivatele TO IDX_FEAC86E4D84E9520;
ALTER TABLE reporty_log_pouziti RENAME INDEX report_uzivatel TO IDX_id_reportu_id_uzivatele;
ALTER TABLE role_seznam
    CHANGE id_role id_role BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE popis_role popis_role LONGTEXT NOT NULL,
    CHANGE kategorie_role kategorie_role SMALLINT UNSIGNED DEFAULT 0 NOT NULL;
ALTER TABLE role_seznam RENAME INDEX typ_zidle TO IDX_typ_role;
ALTER TABLE role_seznam RENAME INDEX vyznam TO IDX_vyznam_role;
ALTER TABLE role_seznam RENAME INDEX kod_role TO UNIQ_kod_role;
ALTER TABLE role_seznam RENAME INDEX nazev_role TO UNIQ_nazev_role;
ALTER TABLE prava_role
    DROP FOREIGN KEY IF EXISTS FK_prava_role_to_r_prava_soupis;
ALTER TABLE prava_role
    DROP FOREIGN KEY IF EXISTS FK_prava_role_to_role_seznam;
ALTER TABLE prava_role
    CHANGE id_role id_role BIGINT UNSIGNED NOT NULL,
    CHANGE id_prava id_prava BIGINT UNSIGNED NOT NULL;
ALTER TABLE prava_role
    ADD CONSTRAINT FK_57A9921ADC499668 FOREIGN KEY (id_role) REFERENCES role_seznam (id_role) ON DELETE CASCADE;
ALTER TABLE prava_role
    ADD CONSTRAINT FK_57A9921A1A86105C FOREIGN KEY (id_prava) REFERENCES r_prava_soupis (id_prava) ON DELETE CASCADE;
ALTER TABLE prava_role RENAME INDEX id_prava TO IDX_57A9921A1A86105C;
ALTER TABLE obchod_mrizky
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL;
ALTER TABLE obchod_bunky
    DROP FOREIGN KEY IF EXISTS FK_obchod_bunky_to_obchod_mrizky;
ALTER TABLE obchod_bunky
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE mrizka_id mrizka_id BIGINT UNSIGNED DEFAULT NULL,
    CHANGE typ typ SMALLINT NOT NULL COMMENT '0-předmět, 1-stránka, 2-zpět, 3-shrnutí';
ALTER TABLE obchod_bunky
    ADD CONSTRAINT FK_2DA00FBEE5BF0939 FOREIGN KEY (mrizka_id) REFERENCES obchod_mrizky (id) ON DELETE CASCADE;
ALTER TABLE obchod_bunky RENAME INDEX fk_obchod_bunky_to_obchod_mrizky TO IDX_2DA00FBEE5BF0939;
DROP INDEX idx_nazev ON shop_predmety;
ALTER TABLE shop_predmety
    CHANGE id_predmetu id_predmetu BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE stav stav SMALLINT NOT NULL,
    CHANGE nabizet_do nabizet_do DATETIME DEFAULT NULL,
    CHANGE typ typ SMALLINT NOT NULL COMMENT '1-předmět, 2-ubytování, 3-tričko, 4-jídlo, 5-vstupné, 6-parcon, 7-vyplaceni',
    CHANGE ubytovani_den ubytovani_den SMALLINT DEFAULT NULL;
ALTER TABLE shop_predmety RENAME INDEX kod_predmetu_model_rok TO UNIQ_kod_predmetu_model_rok;
ALTER TABLE shop_nakupy
    DROP FOREIGN KEY IF EXISTS FK_shop_nakupy_to_shop_predmety;
ALTER TABLE shop_nakupy
    DROP FOREIGN KEY IF EXISTS FK_shop_nakupy_to_uzivatele_hodnoty;
ALTER TABLE shop_nakupy
    DROP FOREIGN KEY shop_nakupy_ibfk_1;
DROP INDEX id_nakupu ON shop_nakupy;
ALTER TABLE shop_nakupy
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_objednatele id_objednatele BIGINT UNSIGNED DEFAULT NULL,
    CHANGE id_predmetu id_predmetu BIGINT UNSIGNED NOT NULL,
    ADD PRIMARY KEY (id_nakupu);
ALTER TABLE shop_nakupy
    ADD CONSTRAINT FK_1A37DD21D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE shop_nakupy
    ADD CONSTRAINT FK_1A37DD218369B810 FOREIGN KEY (id_objednatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL;
ALTER TABLE shop_nakupy
    ADD CONSTRAINT FK_1A37DD213AB9335E FOREIGN KEY (id_predmetu) REFERENCES shop_predmety (id_predmetu) ON DELETE RESTRICT;
ALTER TABLE shop_nakupy RENAME INDEX id_uzivatele TO IDX_1A37DD21D84E9520;
ALTER TABLE shop_nakupy RENAME INDEX id_objednatele TO IDX_1A37DD218369B810;
ALTER TABLE shop_nakupy RENAME INDEX id_predmetu TO IDX_1A37DD213AB9335E;
ALTER TABLE shop_nakupy RENAME INDEX rok_id_uzivatele TO IDX_rok_id_uzivatele;
ALTER TABLE shop_nakupy_zrusene
    DROP FOREIGN KEY IF EXISTS FK_zrusene_objednavky_to_shop_predmety;
ALTER TABLE shop_nakupy_zrusene
    DROP FOREIGN KEY IF EXISTS FK_zrusene_objednavky_to_uzivatele_hodnoty;
ALTER TABLE shop_nakupy_zrusene
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_predmetu id_predmetu BIGINT UNSIGNED NOT NULL,
    ADD PRIMARY KEY (id_nakupu);
ALTER TABLE shop_nakupy_zrusene
    ADD CONSTRAINT FK_EB331F2FD84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE shop_nakupy_zrusene
    ADD CONSTRAINT FK_EB331F2F3AB9335E FOREIGN KEY (id_predmetu) REFERENCES shop_predmety (id_predmetu) ON DELETE RESTRICT;
ALTER TABLE shop_nakupy_zrusene RENAME INDEX fk_zrusene_objednavky_to_uzivatele_hodnoty TO IDX_EB331F2FD84E9520;
ALTER TABLE shop_nakupy_zrusene RENAME INDEX fk_zrusene_objednavky_to_shop_predmety TO IDX_EB331F2F3AB9335E;
ALTER TABLE shop_nakupy_zrusene RENAME INDEX datum_zruseni TO IDX_datum_zruseni;
ALTER TABLE shop_nakupy_zrusene RENAME INDEX zdroj_zruseni TO IDX_zdroj_zruseni;
ALTER TABLE systemove_nastaveni
    MODIFY id_nastaveni BIGINT UNSIGNED NOT NULL;
DROP INDEX id_nastaveni ON systemove_nastaveni;
DROP INDEX `primary` ON systemove_nastaveni;
CREATE UNIQUE INDEX UNIQ_klic_rocnik_nastaveni ON systemove_nastaveni (klic, rocnik_nastaveni);
ALTER TABLE systemove_nastaveni
    ADD PRIMARY KEY (id_nastaveni);
ALTER TABLE systemove_nastaveni RENAME INDEX skupina TO IDX_skupina;
ALTER TABLE systemove_nastaveni RENAME INDEX nazev TO UNIQ_nazev_rocnik_nastaveni;
ALTER TABLE systemove_nastaveni_log
    DROP FOREIGN KEY IF EXISTS FK_systemove_nastaveni_log_to_uzivatele_hodnoty;
ALTER TABLE systemove_nastaveni_log
    DROP FOREIGN KEY IF EXISTS FK_systemove_nastaveni_log_to_systemove_nastaveni;
DROP INDEX id_nastaveni_log ON systemove_nastaveni_log;
ALTER TABLE systemove_nastaveni_log
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED DEFAULT NULL,
    ADD PRIMARY KEY (id_nastaveni_log);
ALTER TABLE systemove_nastaveni_log
    ADD CONSTRAINT FK_8F9C0959D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL;
ALTER TABLE systemove_nastaveni_log
    ADD CONSTRAINT FK_8F9C0959C8E5E058 FOREIGN KEY (id_nastaveni) REFERENCES systemove_nastaveni (id_nastaveni) ON DELETE CASCADE;
ALTER TABLE systemove_nastaveni_log RENAME INDEX fk_systemove_nastaveni_log_to_uzivatele_hodnoty TO IDX_8F9C0959D84E9520;
ALTER TABLE systemove_nastaveni_log RENAME INDEX fk_systemove_nastaveni_log_to_systemove_nastaveni TO IDX_8F9C0959C8E5E058;
ALTER TABLE sjednocene_tagy
    MODIFY id INT UNSIGNED NOT NULL;
ALTER TABLE sjednocene_tagy
    DROP FOREIGN KEY IF EXISTS FK_sjednocene_tagy_to_kategorie_sjednocenych_tagu;
DROP INDEX id ON sjednocene_tagy;
DROP INDEX `primary` ON sjednocene_tagy;
ALTER TABLE sjednocene_tagy
    CHANGE id_kategorie_tagu id_kategorie_tagu BIGINT UNSIGNED NOT NULL,
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE poznamka poznamka LONGTEXT NOT NULL;
ALTER TABLE sjednocene_tagy
    ADD CONSTRAINT FK_EEE57AA8FFC91574 FOREIGN KEY (id_kategorie_tagu) REFERENCES kategorie_sjednocenych_tagu (id);
CREATE UNIQUE INDEX UNIQ_nazev ON sjednocene_tagy (nazev);
ALTER TABLE sjednocene_tagy
    ADD PRIMARY KEY (id);
ALTER TABLE sjednocene_tagy RENAME INDEX fk_sjednocene_tagy_to_kategorie_sjednocenych_tagu TO IDX_EEE57AA8FFC91574;
ALTER TABLE texty
    CHANGE id id BIGINT UNSIGNED NOT NULL,
    CHANGE text text LONGTEXT NOT NULL;
ALTER TABLE uzivatele_hodnoty
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE heslo_md5 heslo_md5 VARCHAR(255) NOT NULL,
    CHANGE nechce_maily nechce_maily DATETIME DEFAULT NULL,
    CHANGE mrtvy_mail mrtvy_mail TINYINT(1) DEFAULT 0 NOT NULL,
    CHANGE zustatek zustatek INT DEFAULT 0 NOT NULL,
    CHANGE pohlavi pohlavi VARCHAR(255) NOT NULL,
    CHANGE pomoc_vice pomoc_vice LONGTEXT NOT NULL,
    CHANGE op op VARCHAR(4096) NOT NULL,
    CHANGE infopult_poznamka infopult_poznamka VARCHAR(128) NOT NULL,
    CHANGE typ_dokladu_totoznosti typ_dokladu_totoznosti VARCHAR(16) NOT NULL;
ALTER TABLE uzivatele_hodnoty RENAME INDEX infopult_poznamka TO IDX_infopult_poznamka;
ALTER TABLE uzivatele_hodnoty RENAME INDEX login_uzivatele TO UNIQ_login_uzivatele;
ALTER TABLE uzivatele_hodnoty RENAME INDEX email1_uzivatele TO UNIQ_email1_uzivatele;
ALTER TABLE medailonky
    DROP FOREIGN KEY IF EXISTS FK_medailonky_to_uzivatele_hodnoty;
ALTER TABLE medailonky
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL COMMENT 'ON UPDATE CASCADE',
    CHANGE o_sobe o_sobe LONGTEXT NOT NULL COMMENT 'markdown',
    CHANGE drd drd LONGTEXT NOT NULL COMMENT 'markdown -- profil pro DrD';
ALTER TABLE medailonky
    ADD CONSTRAINT FK_55E27418D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE uzivatele_slucovani_log
    CHANGE id_smazaneho_uzivatele id_smazaneho_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_noveho_uzivatele id_noveho_uzivatele BIGINT UNSIGNED NOT NULL;
ALTER TABLE uzivatele_role
    MODIFY id BIGINT UNSIGNED NOT NULL;
ALTER TABLE uzivatele_role
    DROP FOREIGN KEY IF EXISTS FK_uzivatele_role_role_seznam;
ALTER TABLE uzivatele_role
    DROP FOREIGN KEY IF EXISTS FK_uzivatele_role_uzivatele_hodnoty;
DROP INDEX id ON uzivatele_role;
DROP INDEX `primary` ON uzivatele_role;
ALTER TABLE uzivatele_role
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_role id_role BIGINT UNSIGNED NOT NULL,
    CHANGE posadil posadil BIGINT UNSIGNED DEFAULT NULL,
    CHANGE id id BIGINT UNSIGNED NOT NULL;
ALTER TABLE uzivatele_role
    ADD CONSTRAINT FK_4F909638D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE uzivatele_role
    ADD CONSTRAINT FK_4F909638DC499668 FOREIGN KEY (id_role) REFERENCES role_seznam (id_role) ON DELETE CASCADE;
ALTER TABLE uzivatele_role
    ADD CONSTRAINT FK_4F909638AAB26C2 FOREIGN KEY (posadil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL;
CREATE UNIQUE INDEX UNIQ_id_uzivatele_id_role ON uzivatele_role (id_uzivatele, id_role);
ALTER TABLE uzivatele_role
    ADD PRIMARY KEY (id);
ALTER TABLE uzivatele_role RENAME INDEX fk_uzivatele_role_role_seznam TO IDX_4F909638DC499668;
ALTER TABLE uzivatele_role RENAME INDEX posadil TO IDX_4F909638AAB26C2;
ALTER TABLE uzivatele_role_podle_rocniku
    DROP FOREIGN KEY IF EXISTS FK_uzivatele_role_podle_rocniku_to_uzivatele_hodnoty;
ALTER TABLE uzivatele_role_podle_rocniku
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_role id_role BIGINT UNSIGNED NOT NULL;
ALTER TABLE uzivatele_role_podle_rocniku
    ADD CONSTRAINT FK_9204F263D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE uzivatele_role_podle_rocniku
    ADD CONSTRAINT FK_9204F263DC499668 FOREIGN KEY (id_role) REFERENCES role_seznam (id_role) ON DELETE CASCADE;
CREATE INDEX IDX_9204F263DC499668 ON uzivatele_role_podle_rocniku (id_role);
ALTER TABLE uzivatele_role_podle_rocniku RENAME INDEX fk_uzivatele_role_podle_rocniku_to_uzivatele_hodnoty TO IDX_9204F263D84E9520;
ALTER TABLE uzivatele_role_log
    DROP FOREIGN KEY IF EXISTS FK_uzivatele_role_log_to_role_seznam;
ALTER TABLE uzivatele_role_log
    DROP FOREIGN KEY IF EXISTS FK_uzivatele_role_log_to_uzivatele_hodnoty;
DROP INDEX id ON uzivatele_role_log;
ALTER TABLE uzivatele_role_log
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_role id_role BIGINT UNSIGNED NOT NULL,
    CHANGE id_zmenil id_zmenil BIGINT UNSIGNED DEFAULT NULL,
    ADD PRIMARY KEY (id);
ALTER TABLE uzivatele_role_log
    ADD CONSTRAINT FK_9977B328D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE uzivatele_role_log
    ADD CONSTRAINT FK_9977B328DC499668 FOREIGN KEY (id_role) REFERENCES role_seznam (id_role) ON DELETE CASCADE;
ALTER TABLE uzivatele_role_log
    ADD CONSTRAINT FK_9977B328E2649593 FOREIGN KEY (id_zmenil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL;
ALTER TABLE uzivatele_role_log RENAME INDEX id_uzivatele TO IDX_9977B328D84E9520;
ALTER TABLE uzivatele_role_log RENAME INDEX id_zidle TO IDX_9977B328DC499668;
ALTER TABLE uzivatele_role_log RENAME INDEX id_zmenil TO IDX_9977B328E2649593;
ALTER TABLE role_texty_podle_uzivatele
    DROP FOREIGN KEY IF EXISTS FK_role_texty_podle_uzivatele_to_uzivatele_hodnoty;
ALTER TABLE role_texty_podle_uzivatele
    ADD id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE popis_role popis_role LONGTEXT DEFAULT NULL,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id);
ALTER TABLE role_texty_podle_uzivatele
    ADD CONSTRAINT FK_D4CCA4DFD84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
CREATE UNIQUE INDEX UNIQ_id_uzivatele_vyznam_role ON role_texty_podle_uzivatele (id_uzivatele, vyznam_role);
ALTER TABLE role_texty_podle_uzivatele RENAME INDEX fk_role_texty_podle_uzivatele_to_uzivatele_hodnoty TO IDX_D4CCA4DFD84E9520;
ALTER TABLE uzivatele_url
    MODIFY id_url_uzivatele BIGINT UNSIGNED NOT NULL;
ALTER TABLE uzivatele_url
    DROP INDEX id_uzivatele,
    ADD UNIQUE INDEX UNIQ_BA2D6079D84E9520 (id_uzivatele);
ALTER TABLE uzivatele_url
    DROP FOREIGN KEY IF EXISTS FK_uzivatele_url_to_uzivatele_hodnoty;
DROP INDEX id_url_uzivatele ON uzivatele_url;
DROP INDEX `primary` ON uzivatele_url;
ALTER TABLE uzivatele_url
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL;
ALTER TABLE uzivatele_url
    ADD CONSTRAINT FK_BA2D6079D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
CREATE UNIQUE INDEX UNIQ_BA2D6079F47645AE ON uzivatele_url (url);
ALTER TABLE uzivatele_url
    ADD PRIMARY KEY (id_url_uzivatele);

ALTER TABLE akce_seznam
    ADD CONSTRAINT FK_2EE8EBF0AF219F1D FOREIGN KEY (patri_pod) REFERENCES akce_instance (id_instance) ON DELETE SET NULL;
ALTER TABLE akce_seznam
    ADD CONSTRAINT FK_2EE8EBF03ACF2B8 FOREIGN KEY (lokace) REFERENCES akce_lokace (id_lokace) ON DELETE SET NULL;
ALTER TABLE akce_seznam
    ADD CONSTRAINT FK_2EE8EBF0241AA1D FOREIGN KEY (typ) REFERENCES akce_typy (id_typu) ON DELETE RESTRICT;
ALTER TABLE akce_seznam
    ADD CONSTRAINT FK_2EE8EBF0CEB69E0D FOREIGN KEY (stav) REFERENCES akce_stav (id_stav) ON DELETE RESTRICT;
ALTER TABLE akce_seznam
    ADD CONSTRAINT FK_2EE8EBF0607A39CF FOREIGN KEY (zamcel) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL;
ALTER TABLE akce_seznam
    ADD CONSTRAINT FK_2EE8EBF0757768BF FOREIGN KEY (popis) REFERENCES texty (id) ON DELETE RESTRICT;

ALTER TABLE ubytovani
    ADD CONSTRAINT FK_F483CEC3D84E9520 FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;


ALTER TABLE akce_seznam RENAME INDEX patri_pod TO IDX_2EE8EBF0AF219F1D;
ALTER TABLE akce_seznam RENAME INDEX lokace TO IDX_2EE8EBF03ACF2B8;
ALTER TABLE akce_seznam RENAME INDEX typ TO IDX_2EE8EBF0241AA1D;
ALTER TABLE akce_seznam RENAME INDEX stav TO IDX_2EE8EBF0CEB69E0D;
ALTER TABLE akce_seznam RENAME INDEX fk_akce_seznam_zamcel_to_uzivatele_hodnoty TO IDX_2EE8EBF0607A39CF;
ALTER TABLE akce_seznam RENAME INDEX popis TO IDX_2EE8EBF0757768BF;
ALTER TABLE akce_seznam RENAME INDEX rok TO IDX_rok;
ALTER TABLE akce_seznam RENAME INDEX url_akce_rok_typ TO UNIQ_url_akce_rok_typ;
