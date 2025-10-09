ALTER TABLE stranky
    CHANGE id_stranky id_stranky INT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE poradi poradi INT UNSIGNED DEFAULT 0 NOT NULL;

ALTER TABLE reporty_quick
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE dotaz dotaz LONGTEXT NOT NULL,
    CHANGE format_xlsx format_xlsx TINYINT(1) DEFAULT 1 NOT NULL,
    CHANGE format_html format_html TINYINT(1) DEFAULT 1 NOT NULL;

ALTER TABLE akce_lokace
    CHANGE id_lokace id_lokace BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE poznamka poznamka LONGTEXT NOT NULL;

ALTER TABLE reporty_log_pouziti
    CHANGE id_reportu id_reportu BIGINT UNSIGNED NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    DROP INDEX IF EXISTS id,
    ADD PRIMARY KEY (id);

ALTER TABLE obchod_mrizky
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL;

ALTER TABLE uzivatele_role_podle_rocniku
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_role id_role BIGINT NOT NULL;

ALTER TABLE google_api_user_tokens
    CHANGE user_id user_id BIGINT UNSIGNED NOT NULL,
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE tokens tokens LONGTEXT NOT NULL,
    DROP INDEX IF EXISTS id,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id);

ALTER TABLE platby
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED DEFAULT NULL,
    CHANGE provedl provedl BIGINT UNSIGNED NOT NULL,
    CHANGE provedeno provedeno DATETIME NOT NULL,
    CHANGE poznamka poznamka LONGTEXT DEFAULT NULL,
    CHANGE skryta_poznamka skryta_poznamka LONGTEXT DEFAULT NULL;

ALTER TABLE kategorie_sjednocenych_tagu
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_hlavni_kategorie id_hlavni_kategorie BIGINT UNSIGNED DEFAULT NULL,
    DROP INDEX IF EXISTS id,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id);

ALTER TABLE akce_prihlaseni_stavy
    CHANGE id_stavu_prihlaseni id_stavu_prihlaseni SMALLINT UNSIGNED NOT NULL,
    CHANGE platba_procent platba_procent SMALLINT DEFAULT 100 NOT NULL;

ALTER TABLE log_udalosti
    CHANGE id_logujiciho id_logujiciho BIGINT UNSIGNED NOT NULL,
    DROP INDEX IF EXISTS id_udalosti,
    ADD PRIMARY KEY (id_udalosti);

ALTER TABLE uzivatele_role
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_role id_role BIGINT NOT NULL,
    CHANGE posadil posadil BIGINT UNSIGNED DEFAULT NULL,
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    DROP INDEX IF EXISTS id,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id);

ALTER TABLE akce_typy
    CHANGE id_typu id_typu INT UNSIGNED NOT NULL,
    CHANGE stranka_o stranka_o INT UNSIGNED NOT NULL;

ALTER TABLE mutex
    MODIFY id_mutex INT UNSIGNED AUTO_INCREMENT NOT NULL,
    DROP INDEX IF EXISTS id_mutex,
    DROP PRIMARY KEY,
    CHANGE zamknul zamknul BIGINT UNSIGNED DEFAULT NULL,
    CHANGE od od DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    CHANGE do do DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    ADD UNIQUE INDEX UNIQ_akce (akce),
    ADD PRIMARY KEY (id_mutex);

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

ALTER TABLE novinky
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE typ typ SMALLINT DEFAULT 1 NOT NULL COMMENT '1-novinka 2-blog',
    CHANGE `text` `text` BIGINT NOT NULL;

ALTER TABLE akce_seznam
    CHANGE id_akce id_akce BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE popis popis BIGINT NOT NULL,
    CHANGE patri_pod patri_pod BIGINT UNSIGNED DEFAULT NULL,
    CHANGE typ typ INT UNSIGNED NOT NULL,
    CHANGE stav stav INT UNSIGNED NOT NULL,
    CHANGE zamcel zamcel BIGINT UNSIGNED DEFAULT NULL COMMENT 'případně kdo zamčel aktivitu pro svůj team',
    CHANGE lokace lokace BIGINT UNSIGNED DEFAULT NULL,
    CHANGE vybaveni vybaveni LONGTEXT NOT NULL;

ALTER TABLE texty
    CHANGE id id BIGINT NOT NULL,
    CHANGE text text LONGTEXT NOT NULL;

ALTER TABLE google_drive_dirs
    CHANGE user_id user_id BIGINT UNSIGNED NOT NULL,
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    DROP INDEX IF EXISTS id,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id);

ALTER TABLE systemove_nastaveni
    CHANGE id_nastaveni id_nastaveni BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    DROP INDEX IF EXISTS id_nastaveni,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id_nastaveni);

ALTER TABLE shop_nakupy
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_objednatele id_objednatele BIGINT UNSIGNED DEFAULT NULL,
    CHANGE id_predmetu id_predmetu BIGINT UNSIGNED NOT NULL,
    DROP INDEX IF EXISTS id_nakupu,
    ADD PRIMARY KEY (id_nakupu);

ALTER TABLE akce_import
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE cas cas DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    DROP INDEX IF EXISTS id_akce_import,
    ADD PRIMARY KEY (id_akce_import);

ALTER TABLE slevy
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE provedl provedl BIGINT UNSIGNED DEFAULT NULL,
    CHANGE poznamka poznamka LONGTEXT DEFAULT NULL;

ALTER TABLE shop_predmety
    CHANGE id_predmetu id_predmetu BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE stav stav SMALLINT NOT NULL,
    CHANGE nabizet_do nabizet_do DATETIME DEFAULT NULL,
    CHANGE typ typ SMALLINT NOT NULL COMMENT '1-předmět, 2-ubytování, 3-tričko, 4-jídlo, 5-vstupné, 6-parcon, 7-vyplaceni',
    CHANGE ubytovani_den ubytovani_den SMALLINT DEFAULT NULL;

ALTER TABLE hromadne_akce_log
    CHANGE provedl provedl BIGINT UNSIGNED DEFAULT NULL,
    DROP INDEX IF EXISTS id_logu,
    ADD PRIMARY KEY (id_logu);

ALTER TABLE role_seznam
    CHANGE id_role id_role BIGINT NOT NULL,
    CHANGE popis_role popis_role LONGTEXT NOT NULL,
    CHANGE kategorie_role kategorie_role SMALLINT UNSIGNED DEFAULT 0 NOT NULL;

ALTER TABLE akce_prihlaseni_log
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE typ typ VARCHAR(64) DEFAULT NULL,
    CHANGE id_zmenil id_zmenil BIGINT UNSIGNED DEFAULT NULL;

ALTER TABLE akce_prihlaseni
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_stavu_prihlaseni id_stavu_prihlaseni SMALLINT UNSIGNED NOT NULL;

ALTER TABLE medailonky
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL COMMENT 'ON UPDATE CASCADE',
    CHANGE o_sobe o_sobe LONGTEXT NOT NULL COMMENT 'markdown',
    CHANGE drd drd LONGTEXT NOT NULL COMMENT 'markdown -- profil pro DrD';

ALTER TABLE akce_stavy_log
    CHANGE id_stav id_stav INT UNSIGNED NOT NULL,
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE kdy kdy DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    DROP INDEX IF EXISTS akce_stavy_log_id,
    ADD PRIMARY KEY (akce_stavy_log_id);

ALTER TABLE uzivatele_url
    CHANGE id_url_uzivatele id_url_uzivatele BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    DROP INDEX IF EXISTS id_uzivatele,
    DROP INDEX IF EXISTS id_url_uzivatele,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id_url_uzivatele);

ALTER TABLE systemove_nastaveni_log
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED DEFAULT NULL,
    DROP INDEX IF EXISTS id_nastaveni_log,
    ADD PRIMARY KEY (id_nastaveni_log);

ALTER TABLE reporty
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    DROP INDEX IF EXISTS id,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id);

ALTER TABLE role_texty_podle_uzivatele
    ADD id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE popis_role popis_role LONGTEXT DEFAULT NULL,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id);

ALTER TABLE shop_nakupy_zrusene
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_predmetu id_predmetu BIGINT UNSIGNED NOT NULL,
    ADD PRIMARY KEY (id_nakupu);

ALTER TABLE newsletter_prihlaseni_log
    CHANGE id_newsletter_prihlaseni_log id_newsletter_prihlaseni_log BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id_newsletter_prihlaseni_log);

ALTER TABLE akce_sjednocene_tagy
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE id_tagu id_tagu BIGINT UNSIGNED NOT NULL;

ALTER TABLE prava_role
    CHANGE id_role id_role BIGINT NOT NULL,
    CHANGE id_prava id_prava BIGINT NOT NULL;

ALTER TABLE sjednocene_tagy
    CHANGE id_kategorie_tagu id_kategorie_tagu BIGINT UNSIGNED NOT NULL,
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE poznamka poznamka LONGTEXT NOT NULL,
    DROP INDEX IF EXISTS id,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id);

ALTER TABLE newsletter_prihlaseni
    CHANGE id_newsletter_prihlaseni id_newsletter_prihlaseni BIGINT UNSIGNED AUTO_INCREMENT NOT NULL;

ALTER TABLE obchod_bunky
    CHANGE id id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE mrizka_id mrizka_id BIGINT UNSIGNED DEFAULT NULL,
    CHANGE typ typ SMALLINT NOT NULL COMMENT '0-předmět, 1-stránka, 2-zpět, 3-shrnutí';

ALTER TABLE akce_stav
    CHANGE id_stav id_stav INT UNSIGNED AUTO_INCREMENT NOT NULL,
    DROP INDEX IF EXISTS id_stav,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id_stav);

ALTER TABLE r_prava_soupis
    CHANGE id_prava id_prava BIGINT NOT NULL,
    CHANGE popis_prava popis_prava LONGTEXT NOT NULL;

ALTER TABLE akce_instance
    CHANGE id_instance id_instance BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_hlavni_akce id_hlavni_akce BIGINT UNSIGNED NOT NULL;

ALTER TABLE akce_prihlaseni_spec
    ADD id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_stavu_prihlaseni id_stavu_prihlaseni SMALLINT UNSIGNED NOT NULL,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (id);

ALTER TABLE uzivatele_slucovani_log
    CHANGE id_smazaneho_uzivatele id_smazaneho_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_noveho_uzivatele id_noveho_uzivatele BIGINT UNSIGNED NOT NULL;

ALTER TABLE uzivatele_role_log
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE id_role id_role BIGINT NOT NULL,
    CHANGE id_zmenil id_zmenil BIGINT UNSIGNED DEFAULT NULL,
    DROP INDEX IF EXISTS id,
    ADD PRIMARY KEY (id);

ALTER TABLE akce_organizatori
    CHANGE id_akce id_akce BIGINT UNSIGNED NOT NULL,
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL COMMENT 'organizátor';

ALTER TABLE ubytovani
    CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL,
    CHANGE den den SMALLINT NOT NULL;
