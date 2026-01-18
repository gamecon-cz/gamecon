<?php
/** @var \Godric\DbMigrations\Migration $this */

/**
 * Inicializace databáze do prázdného stavu, 2026-01-18
 */

$this->q(<<<SQL
CREATE TABLE akce_lokace
(
    id_lokace bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    nazev     varchar(255)        NOT NULL,
    dvere     varchar(255)        NOT NULL,
    poznamka  longtext            NOT NULL,
    poradi    int(11)             NOT NULL,
    rok       int(11) DEFAULT 0,
    PRIMARY KEY (id_lokace),
    UNIQUE KEY (nazev, rok)
);

CREATE TABLE akce_prihlaseni_stavy
(
    id_stavu_prihlaseni smallint(5) unsigned NOT NULL,
    nazev               varchar(255)         NOT NULL,
    platba_procent      smallint(6)          NOT NULL DEFAULT 100,
    PRIMARY KEY (id_stavu_prihlaseni)
);
INSERT INTO akce_prihlaseni_stavy (id_stavu_prihlaseni, nazev, platba_procent)
VALUES (0, 'přihlášen', 100),
       (1, 'dorazil', 100),
       (2, 'dorazil (náhradník)', 100),
       (3, 'nedorazil', 100),
       (4, 'pozdě zrušil', 50),
       (5, 'náhradník (watchlist)', 0);

CREATE TABLE akce_stav
(
    id_stav int(10) unsigned NOT NULL AUTO_INCREMENT,
    nazev   varchar(128)     NOT NULL,
    PRIMARY KEY (id_stav),
    UNIQUE KEY (nazev)
);
INSERT INTO akce_stav
VALUES (2, 'aktivovaná'),
       (1, 'nová'),
       (6, 'připravená'),
       (5, 'publikovaná'),
       (4, 'systémová'),
       (3, 'uzavřená'),
       (7, 'zamčená');

CREATE TABLE kategorie_sjednocenych_tagu
(
    id                  bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    nazev               varchar(128)        NOT NULL,
    id_hlavni_kategorie bigint(20) unsigned DEFAULT NULL,
    poradi              int(10) unsigned    NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (nazev),
    KEY (id_hlavni_kategorie),
    FOREIGN KEY (id_hlavni_kategorie) REFERENCES kategorie_sjednocenych_tagu (id) ON DELETE CASCADE
);
INSERT INTO kategorie_sjednocenych_tagu
VALUES (1, 'Primary', NULL, 1),
       (2, 'typ', NULL, 10),
       (3, 'žánr', NULL, 20),
       (4, 'prostředí', NULL, 30),
       (5, 'styl', NULL, 40),
       (6, 'systém', NULL, 50),
       (7, 'různé', NULL, 80),
       (8, 'omezení', NULL, 90),
       (9, 'typ RPG', 2, 11),
       (10, 'typ LKD', 2, 12),
       (11, 'typ mDrD', 2, 13),
       (12, 'typ Larp', 2, 14),
       (13, 'typ Akční', 2, 15),
       (14, 'typ Deskovky', 2, 16),
       (15, 'typ WG', 2, 17),
       (16, 'typ Přednášky', 2, 18),
       (17, 'žánr RPG', 3, 21),
       (18, 'žánr LKD', 3, 22),
       (19, 'žánr mDrD', 3, 23),
       (20, 'žánr Larp', 3, 24),
       (21, 'žánr Akční', 3, 25),
       (22, 'žánr Deskovky', 3, 26),
       (23, 'žánr WG', 3, 27),
       (24, 'žánr Přednášky', 3, 28),
       (25, 'prostředí RPG', 4, 31),
       (26, 'prostředí LKD', 4, 32),
       (27, 'prostředí mDrD', 4, 33),
       (28, 'prostředí Larp', 4, 34),
       (29, 'prostředí Akční', 4, 35),
       (30, 'prostředí Deskovky', 4, 36),
       (31, 'prostředí WG', 4, 37),
       (32, 'prostředí Přednášky', 4, 38),
       (33, 'styl RPG', 5, 41),
       (34, 'styl LKD', 5, 42),
       (35, 'styl mDrD', 5, 43),
       (36, 'styl Larp', 5, 44),
       (37, 'styl Akční', 5, 45),
       (38, 'styl Deskovky', 5, 46),
       (39, 'styl WG', 5, 47),
       (40, 'styl Přednášky', 5, 48),
       (41, 'systém RPG', 6, 51),
       (42, 'systém LKD', 6, 52),
       (43, 'systém mDrD', 6, 53),
       (44, 'systém Larp', 6, 54),
       (45, 'systém Akční', 6, 55),
       (46, 'systém Deskovky', 6, 56),
       (47, 'systém WG', 6, 57),
       (48, 'systém Přednášky', 6, 58),
       (49, 'různé Partner', 7, 81),
       (50, 'omezení Věk', 8, 95);

CREATE TABLE migrations
(
    migration_id   bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    migration_code varchar(128)        NOT NULL,
    applied_at     datetime DEFAULT NULL,
    PRIMARY KEY (migration_code),
    UNIQUE KEY (migration_id)
);
INSERT INTO migrations (migration_code, applied_at)
VALUES
    ('000', null),
    ('2025-10-08-083850-delete-temporary-table.sql', null),
    ('2025-10-08-083854-drop-foreign-keys.sql', null),
    ('2025-10-08-083855-drop-indexes.sql', null),
    ('2025-10-08-083856-core-doctrine.sql', null),
    ('2025-10-10-084430-clean-data-for-indexes.sql', null),
    ('2025-10-10-084438-add-indexes.sql', null),
    ('2025-11-06-140204-clear-indexes-on-users-per-year.sql', null),
    ('2026-01-14_01-schovani-drd.sql', null),
    ('2026-01-18_01-celohry-na-celohra.sql', null);

CREATE TABLE uzivatele_hodnoty
(
    id_uzivatele                        bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    login_uzivatele                     varchar(255)        NOT NULL,
    jmeno_uzivatele                     varchar(100)        NOT NULL,
    prijmeni_uzivatele                  varchar(100)        NOT NULL,
    ulice_a_cp_uzivatele                varchar(255)        NOT NULL,
    mesto_uzivatele                     varchar(100)        NOT NULL,
    stat_uzivatele                      int(11)             NOT NULL,
    psc_uzivatele                       varchar(20)         NOT NULL,
    telefon_uzivatele                   varchar(100)        NOT NULL,
    datum_narozeni                      date                NOT NULL,
    heslo_md5                           varchar(255)        NOT NULL,
    email1_uzivatele                    varchar(255)        NOT NULL,
    nechce_maily                        datetime                     DEFAULT NULL,
    mrtvy_mail                          tinyint(1)          NOT NULL DEFAULT 0,
    forum_razeni                        varchar(1)          NOT NULL,
    random                              varchar(20)         NOT NULL,
    zustatek                            int(11)             NOT NULL DEFAULT 0,
    pohlavi                             varchar(255)        NOT NULL,
    registrovan                         datetime            NOT NULL,
    ubytovan_s                          varchar(255)                 DEFAULT NULL,
    poznamka                            varchar(4096)       NOT NULL,
    pomoc_typ                           varchar(64)         NOT NULL,
    pomoc_vice                          longtext            NOT NULL,
    op                                  varchar(4096)       NOT NULL,
    potvrzeni_zakonneho_zastupce        date                         DEFAULT NULL,
    potvrzeni_proti_covid19_pridano_kdy datetime                     DEFAULT NULL,
    potvrzeni_proti_covid19_overeno_kdy datetime                     DEFAULT NULL,
    infopult_poznamka                   varchar(128)        NOT NULL,
    typ_dokladu_totoznosti              varchar(16)         NOT NULL,
    statni_obcanstvi                    varchar(64)                  DEFAULT NULL,
    z_rychloregistrace                  tinyint(1)                   DEFAULT 0,
    potvrzeni_zakonneho_zastupce_soubor datetime                     DEFAULT NULL,
    PRIMARY KEY (id_uzivatele),
    UNIQUE KEY (login_uzivatele),
    UNIQUE KEY (email1_uzivatele),
    KEY (infopult_poznamka)
);
INSERT INTO uzivatele_hodnoty
VALUES (1, 'SYSTEM', 'SYSTEM', 'SYSTEM', 'SYSTEM', 'SYSTEM', 1, 'SYSTEM', 'SYSTEM', '2022-07-14', '',
        'system@gamecon.cz', '2022-07-14 00:00:00', 1, '', 'e5997db4b68f9b8dff98', -450, 'm', '2022-07-14 00:00:00', '',
        '', '', '', '', NULL, NULL, NULL, '', '', 'ČR', 0, NULL);

CREATE TABLE novinky
(
    id    bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    typ   smallint(6)         NOT NULL DEFAULT 1 COMMENT '1-novinka 2-blog',
    vydat datetime                     DEFAULT NULL,
    url   varchar(100)        NOT NULL,
    nazev varchar(200)        NOT NULL,
    autor varchar(100)                 DEFAULT NULL,
    text  longtext            NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (url)
);

CREATE TABLE obchod_mrizky
(
    id   bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    text varchar(255) DEFAULT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE obchod_bunky
(
    id         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    typ        smallint(6)         NOT NULL COMMENT '0-předmět, 1-stránka, 2-zpět, 3-shrnutí',
    text       varchar(255)        DEFAULT NULL,
    barva      varchar(255)        DEFAULT NULL,
    barva_text varchar(255)        DEFAULT NULL,
    cil_id     int(11)             DEFAULT NULL COMMENT 'Id cílove mřížky nebo předmětu.',
    mrizka_id  bigint(20) unsigned DEFAULT NULL,
    PRIMARY KEY (id),
    KEY (mrizka_id),
    FOREIGN KEY (mrizka_id) REFERENCES obchod_mrizky (id) ON DELETE CASCADE
);

CREATE TABLE ubytovani
(
    id_uzivatele bigint(20) unsigned NOT NULL,
    den          smallint(6)         NOT NULL,
    pokoj        varchar(255)        NOT NULL,
    rok          smallint(6)         NOT NULL,
    PRIMARY KEY (rok, id_uzivatele, den),
    KEY (id_uzivatele),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE slevy
(
    id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_uzivatele bigint(20) unsigned NOT NULL,
    castka       decimal(10, 2)      NOT NULL,
    rok          int(11)             NOT NULL,
    provedeno    timestamp           NOT NULL DEFAULT current_timestamp(),
    provedl      bigint(20) unsigned          DEFAULT NULL,
    poznamka     longtext                     DEFAULT NULL,
    PRIMARY KEY (id),
    KEY (id_uzivatele),
    KEY (provedl),
    FOREIGN KEY (provedl) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL,
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE stranky
(
    id_stranky  int(10) unsigned NOT NULL AUTO_INCREMENT,
    url_stranky varchar(64)      NOT NULL,
    obsah       longtext         NOT NULL COMMENT 'markdown',
    poradi      int(10) unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (id_stranky),
    UNIQUE KEY (url_stranky)
);
INSERT INTO stranky (id_stranky, url_stranky, obsah, poradi) VALUES (1, 'demo', 'DEMO', 0);

CREATE TABLE platby
(
    id                     bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_uzivatele           bigint(20) unsigned      DEFAULT NULL,
    fio_id                 bigint(20)               DEFAULT NULL,
    vs                     varchar(255)             DEFAULT NULL,
    nazev_protiuctu        varchar(255)             DEFAULT NULL,
    cislo_protiuctu        varchar(255)             DEFAULT NULL,
    kod_banky_protiuctu    varchar(127)             DEFAULT NULL,
    nazev_banky_protiuctu  varchar(255)             DEFAULT NULL,
    castka                 decimal(10, 2)      NOT NULL,
    rok                    smallint(6)         NOT NULL,
    provedeno              datetime            NOT NULL,
    provedl                bigint(20) unsigned NOT NULL,
    poznamka               longtext                 DEFAULT NULL,
    skryta_poznamka        longtext                 DEFAULT NULL,
    pripsano_na_ucet_banky timestamp           NULL DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (fio_id),
    KEY (id_uzivatele),
    KEY (provedl),
    KEY (id_uzivatele, rok),
    FOREIGN KEY (provedl) REFERENCES uzivatele_hodnoty (id_uzivatele),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL
);

CREATE TABLE log_udalosti
(
    id_udalosti   bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_logujiciho bigint(20) unsigned NOT NULL,
    zprava        varchar(255) DEFAULT NULL,
    metadata      varchar(255) DEFAULT NULL,
    rok           int(10) unsigned    NOT NULL,
    PRIMARY KEY (id_udalosti),
    KEY (id_logujiciho),
    KEY (metadata),
    FOREIGN KEY (id_logujiciho) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE hromadne_akce_log
(
    id_logu  bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    skupina  varchar(128)                 DEFAULT NULL,
    akce     varchar(255)                 DEFAULT NULL,
    vysledek varchar(255)                 DEFAULT NULL,
    provedl  bigint(20) unsigned          DEFAULT NULL,
    kdy      datetime            NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id_logu),
    KEY (provedl),
    KEY (akce),
    FOREIGN KEY (provedl) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL
);

CREATE TABLE reporty
(
    id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    skript      varchar(100)        NOT NULL,
    nazev       varchar(200) DEFAULT NULL,
    format_xlsx tinyint(1)   DEFAULT 1,
    format_html tinyint(1)   DEFAULT 1,
    viditelny   tinyint(1)   DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY (skript)
);
INSERT INTO reporty
VALUES (1, 'aktivity', 'Historie přihlášení na aktivity', 1, 1, 1),
       (2, 'pocty-her', 'Účastníci a počty jejich aktivit', 1, 0, 1),
       (3, 'pocty-her-graf', 'Graf rozložení rozmanitosti her', 0, 1, 1),
       (4, 'rozesilani-ankety', 'Rozesílání ankety s tokenem', 0, 1, 0),
       (5, 'parovani-ankety', 'Párování ankety a údajů uživatelů', 0, 1, 0),
       (6, 'grafy-ankety', 'Grafy k anketě', 0, 1, 0),
       (7, 'update-zustatku', 'UPDATE příkaz zůstatků pro letošní GC', 0, 1, 1),
       (8, 'neprihlaseni-vypraveci', 'Nepřihlášení a neubytovaní vypravěči + další', 1, 1, 1),
       (9, 'duplicity', 'Duplicitní uživatelé', 1, 1, 1),
       (10, 'stravenky', 'Stravenky uživatelů', 0, 1, 1),
       (11, 'stravenky-bianco', 'Stravenky (bianco)', 0, 1, 1),
       (12, 'maily-prihlaseni', 'Maily – přihlášení na letošní GC', 1, 1, 1),
       (14, 'maily-vypraveci', 'Maily – letošní vypravěči', 1, 1, 1),
       (17, 'finance-lide-v-databazi-a-zustatky', 'Finance: Zůstatky všech účastníků', 1, 1, 1),
       (18, 'finance-aktivity-bez-slev', 'Finance: Aktivity bez slev', 1, 1, 1),
       (19, 'finance-prijmy-a-vydaje-infopultaka', 'Finance: Příjmy a výdaje infopulťáka', 1, 1, 1),
       (20, 'zazemi-a-program-drd-historie-ucasti', 'Zázemí & Program: DrD: Historie účasti', 1, 1, 1),
       (21, 'zazemi-a-program-drd-seznam-prihlasenych-pro-aktualni-rok',
        'Zázemí & Program: DrD: Seznam přihlášených pro aktuální rok', 1, 1, 1),
       (22, 'zazemi-a-program-zarizeni-mistnosti', 'Zázemí & Program: Zařízení místností', 1, 1, 1),
       (23, 'zazemi-a-program-pocet-sledujicich-pro-aktualni-rok',
        'Zázemí & Program: Počet sledujících pro aktuální rok', 1, 1, 1),
       (24, 'zazemi-a-program-emaily-na-vypravece-dle-linii', 'Zázemí & Program: Emaily na vypravěče dle linií', 1, 1,
        1),
       (25, 'zazemi-a-program-emaily-na-ucastniky-dle-linii', 'Zázemí & Program: Emaily na účastníky dle linií', 1, 1,
        1),
       (26, 'zazemi-a-program-aktivity-pro-dotaznik-dle-linii', 'Zázemí & Program: Aktivity pro dotazník dle linií', 1,
        1, 1),
       (27, 'zazemi-a-program-potvrzeni-pro-navstevniky-mladsi-patnacti-let',
        'Zázemí & Program: Potvrzení pro návštěvníky mladší patnácti let', 1, 1, 1),
       (28, 'zazemi-a-program-casy-a-umisteni-aktivit', 'Zázemí & Program: Časy a umístění aktivit', 1, 1, 1),
       (29, 'zazemi-a-program-prehled-mistnosti', 'Zázemí & Program: Přehled místností', 1, 1, 1),
       (30, 'zazemi-a-program-seznam-ucastniku-a-tricek', 'Zázemí & Program: Seznam účastníků a triček', 1, 1, 0),
       (31, 'zazemi-a-program-seznam-ucastniku-a-tricek-grouped',
        'Zázemí & Program: Seznam účastníků a triček (grouped)', 1, 1, 1),
       (32, 'bfgr-report',
        '<span id=\"bfgr\" class=\"hinted\">BFGR (celkový report) {ROK}<span class=\"hint\"><em>Big f**king Gandalf report</em> určený pro Gandalfovu Excelentní magii</span></span>',
        1, 1, 0),
       (34, 'finance-report-ubytovani', 'Ubytování', 1, 1, 0),
       (49, 'report-infopult-ucastnici-balicky', 'Infopult: Balíčky účastníků', 0, 1, 1),
       (50, 'finance-report-neplaticu', 'Finance: Neplatiči k odhlášení', 1, 1, 1),
       (53, 'finance-report-eshop', 'Finance: E-shop', 1, 1, 1),
       (70, 'infopult-report-nezkontrolovane-potvrzeni-rodicu', 'Infopult: Nezkontrolované potvrzení rodičů', 1, 1, 1),
       (74, 'role-podle-rocniku', 'Počty rolí platných v ročnících', 1, 1, 1),
       (84, 'finance-report-sirien', 'Sirienův rozpočtový report', 1, 1, 1),
       (88, 'slevy', 'Udělené slevy', 1, 1, 1),
       (94, 'newsletter-prihlaseni', 'Přihlášení k odběru newsletterů', 1, 1, 1),
       (95, 'bfsr-report', 'Finance: Rozpočtový report', 1, 1, 1);

CREATE TABLE google_api_user_tokens
(
    id               bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id          bigint(20) unsigned NOT NULL,
    google_client_id varchar(128)        NOT NULL,
    tokens           longtext            NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (user_id, google_client_id),
    KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE google_drive_dirs
(
    id            bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id       bigint(20) unsigned NOT NULL,
    dir_id        varchar(128)        NOT NULL,
    original_name varchar(64)         NOT NULL,
    tag           varchar(128)        NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY (dir_id),
    UNIQUE KEY (user_id, original_name),
    KEY (user_id),
    KEY (tag),
    FOREIGN KEY (user_id) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE medailonky
(
    id_uzivatele bigint(20) unsigned NOT NULL COMMENT 'ON UPDATE CASCADE',
    o_sobe       longtext            NOT NULL COMMENT 'markdown',
    drd          longtext            NOT NULL COMMENT 'markdown -- profil pro DrD',
    PRIMARY KEY (id_uzivatele),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE mutex
(
    id_mutex int(10) unsigned NOT NULL AUTO_INCREMENT,
    akce     varchar(128)     NOT NULL,
    klic     varchar(128)     NOT NULL,
    zamknul  bigint(20) unsigned DEFAULT NULL,
    od       datetime         NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    do       datetime            DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id_mutex),
    UNIQUE KEY (akce),
    UNIQUE KEY (klic),
    KEY (zamknul),
    FOREIGN KEY (zamknul) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL
);

CREATE TABLE role_seznam
(
    id_role        bigint(20)           NOT NULL,
    kod_role       varchar(36)          NOT NULL,
    nazev_role     varchar(255)         NOT NULL,
    popis_role     longtext             NOT NULL,
    rocnik_role    int(11)              NOT NULL,
    typ_role       varchar(24)          NOT NULL,
    vyznam_role    varchar(48)          NOT NULL,
    skryta         tinyint(1)                    DEFAULT 0,
    kategorie_role smallint(5) unsigned NOT NULL DEFAULT 0,
    PRIMARY KEY (id_role),
    UNIQUE KEY (kod_role),
    UNIQUE KEY (nazev_role),
    KEY (typ_role),
    KEY (vyznam_role)
);
INSERT INTO role_seznam
VALUES (-202500029, 'GC2025_ZKONTROLOVANE_UDAJE', 'Zkontrolované údaje', 'Zkontrolované údaje', 2025, 'rocnikova',
        'ZKONTROLOVANE_UDAJE', 1, 0),
       (-202500028, 'GC2025_SOBOTNI_NOC_ZDARMA', 'Sobotní noc zdarma', '', 2025, 'rocnikova', 'SOBOTNI_NOC_ZDARMA', 0,
        1),
       (-202500025, 'GC2025_BRIGADNIK', 'Brigádník', 'Zase práce?', 2025, 'rocnikova', 'BRIGADNIK', 0, 0),
       (-202500024, 'GC2025_HERMAN', 'Herman', 'Živoucí návod deskových her sloužící ve jménu Gameconu', 2025,
        'rocnikova', 'HERMAN', 0, 1),
       (-202500023, 'GC2025_NEODHLASOVAT', 'Neodhlašovat',
        'Může zaplatit až na místě. Je chráněn před odhlašováním neplatičů a nezaplacených objednávek.', 2025,
        'rocnikova', 'NEODHLASOVAT', 0, 1),
       (-202500019, 'GC2025_NEDELNI_NOC_ZDARMA', 'Nedělní noc zdarma', '', 2025, 'rocnikova', 'NEDELNI_NOC_ZDARMA', 0,
        1),
       (-202500018, 'GC2025_STREDECNI_NOC_ZDARMA', 'Středeční noc zdarma', '', 2025, 'rocnikova',
        'STREDECNI_NOC_ZDARMA', 0, 1),
       (-202500013, 'GC2025_PARTNER', 'Partner', 'Vystavovatelé, lidé od deskovek, atp.', 2025, 'rocnikova', 'PARTNER',
        0, 1),
       (-202500008, 'GC2025_INFOPULT', 'Infopult', 'Operátor infopultu', 2025, 'rocnikova', 'INFOPULT', 0, 1),
       (-202500007, 'GC2025_ZAZEMI', 'Zázemí', 'Členové zázemí GC (kuchařky, …)', 2025, 'rocnikova', 'ZAZEMI', 0, 0),
       (-202500006, 'GC2025_VYPRAVEC', 'Vypravěč', 'Organizátor aktivit na GC', 2025, 'rocnikova', 'VYPRAVEC', 0, 1),
       (-2503, 'GC2025_ODJEL', 'GC2025 odjel', 'GC2025 odjel', 2025, 'ucast', 'ODJEL', 0, 0),
       (-2502, 'GC2025_PRITOMEN', 'GC2025 přítomen', 'GC2025 přítomen', 2025, 'ucast', 'PRITOMEN', 0, 0),
       (-2501, 'GC2025_PRIHLASEN', 'GC2025 přihlášen', 'GC2025 přihlášen', 2025, 'ucast', 'PRIHLASEN', 0, 0),
       (2, 'ORGANIZATOR_ZDARMA', 'Organizátor (zdarma)', 'Člen organizačního týmu GC', -1, 'trvala',
        'ORGANIZATOR_ZDARMA', 0, 0),
       (9, 'VYPRAVECSKA_SKUPINA', 'Vypravěčská skupina', 'Organizátorská skupina pořádající na GC (dodavatelé, …)', -1,
        'trvala', 'VYPRAVECSKA_SKUPINA', 1, 0),
       (15, 'CESTNY_ORGANIZATOR', 'Čestný organizátor', 'Bývalý organizátor GC', -1, 'trvala', 'CESTNY_ORGANIZATOR', 0,
        0),
       (16, 'ADMIN', 'Prezenční admin', 'Pro změnu účastníků v uzavřených aktivitách. NEBEZPEČNÉ, NEPOUŽÍVAT!', -1,
        'trvala', 'ADMIN', 0, 0),
       (20, 'CFO', 'CFO', 'Organizátor, který může nakládat s financemi GC', -1, 'trvala', 'CFO', 0, 0),
       (21, 'PUL_ORG_UBYTKO', 'Půl-org s ubytkem', 'Krom jiného ubytování zdarma', -1, 'trvala', 'PUL_ORG_UBYTKO', 0,
        0),
       (22, 'PUL_ORG_TRICKO', 'Půl-org s tričkem', 'Krom jiného trička zdarma', -1, 'trvala', 'PUL_ORG_TRICKO', 0, 0),
       (23, 'CLEN_RADY', 'Člen rady', 'Členové rady mají zvláštní zodpovědnost a pravomoce', -1, 'trvala', 'CLEN_RADY',
        0, 0),
       (24, 'SEF_INFOPULTU', 'Šéf infopultu', 'S pravomocemi dělat větší zásahy u přhlášených', -1, 'trvala',
        'SEF_INFOPULTU', 0, 0),
       (25, 'SEF_PROGRAMU', 'Šéf programu',
        'Všeobecné \"vedení\" programu - obecná dramaturgie, rozvoj sekcí, finance programu', -1, 'trvala',
        'SEF_PROGRAMU', 0, 0),
       (26, 'MINI_ORG', 'Mini-org', 'Výpomoc při organizaci GC', -1, 'trvala', 'MINI_ORG', 0, 0),
       (27, 'KOREKTOR', 'Korektor', 'Kontrola a opravy textu', -1, 'trvala', 'KOREKTOR', 0, 0),
       (28, 'SPRAVCE_PARTNERU', 'Správce partnerů', 'Správa partnerů a sponzorů na webu', -1, 'trvala',
        'SPRAVCE_PARTNERU', 0, 1);

CREATE TABLE role_texty_podle_uzivatele
(
    vyznam_role  varchar(48)         NOT NULL,
    id_uzivatele bigint(20) unsigned NOT NULL,
    popis_role   longtext DEFAULT NULL,
    id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id),
    UNIQUE KEY (id_uzivatele, vyznam_role),
    KEY (id_uzivatele),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE r_prava_soupis
(
    id_prava    bigint(20)   NOT NULL,
    jmeno_prava varchar(255) NOT NULL,
    popis_prava longtext     NOT NULL,
    PRIMARY KEY (id_prava)
);
INSERT INTO r_prava_soupis
VALUES (4, 'Pořádání aktivit',
        'Uživatel může pořádat aktivity (je v nabídce pořadatelů aktivit a má v administraci nabídku „moje aktivity“)'),
       (5, 'Překrývání aktivit', 'Smí mít zaregistrovaných víc aktivit v jeden čas'),
       (8, 'Změna historie aktivit', 'může přihlašovat a odhlašovat lidi z aktivit, které už proběhly'),
       (9, 'Přhlašování na dosud neotevřené aktivity',
        'Může přihlašovat a odhlašovat lidi z aktivit, které ještě nejsou Připravené (jsou teprve Publikované)'),
       (100, 'Administrace - panel Infopult', ''),
       (101, 'Administrace - panel Uživatel', ''),
       (102, 'Administrace - panel Aktivity', ''),
       (103, 'Administrace - panel Prezence', ''),
       (104, 'Administrace - panel Reporty', ''),
       (105, 'Administrace - panel Web', ''),
       (106, 'Administrace - panel Práva', ''),
       (107, 'Administrace - panel Statistiky', ''),
       (108, 'Administrace - panel Finance', ''),
       (109, 'Administrace - panel Moje aktivity', ''),
       (110, 'Administrace - panel Nastavení', 'Systémové hodnoty pro Gamecon'),
       (111, 'Administrace - panel Peníze', 'Koutek pro šéfa financí GC'),
       (112, 'Administrace - panel Web Loga', 'Správa log sponzorů a partnerů na webu'),
       (1002, 'Letošní placka zdarma',
        'Jednu placku \"letošní model\" si může objednat za 0.- (\"letošní\" je ta, kterou jsme v předchozích ročnících neprodali ani jednu, je nejnověší model_rok, je nejdražší a byla zadána do systému jako poslední)'),
       (1003, 'Letošní kostka zdarma',
        'Jednu kostku \"letošní model\" si může objednat za 0.- (\"letošní\" je ta, kterou jsme v předchozích ročnících neprodali ani jednu, je nejnověší model_rok, je nejdražší a byla zadána do systému jako poslední)'),
       (1004, 'Jídlo se slevou', 'Může si objednávat jídlo se slevou'),
       (1005, 'Jídlo zdarma', 'Může si objednávat jídlo zdarma'),
       (1008, 'Ubytování zdarma', 'Má zdarma ubytování po celou dobu'),
       (1012, 'Modré tričko za dosaženou slevu %MODRE_TRICKO_ZDARMA_OD%', ''),
       (1015, 'Středeční noc zdarma', ''),
       (1016, 'Nerušit automaticky objednávky',
        'Uživateli se při nezaplacení včas nebudou automaticky rušit objednávky'),
       (1018, 'Nedělní noc zdarma', ''),
       (1019, 'Sleva na aktivity', 'Sleva 40% na aktivity'),
       (1020, 'Dvě jakákoli trička zdarma', ''),
       (1021, 'Právo na modré tričko', 'Může si objednávat modrá trička'),
       (1022, 'Právo na červené tričko', 'Může si objednávat červená trička'),
       (1023, 'Plná sleva na aktivity', 'Sleva 100% na aktivity'),
       (1024, 'Statistiky - tabulka účasti',
        'V adminu v sekci statistiky v tabulce vlevo nahoře se tato role vypisuje'),
       (1025, 'Reporty - zahrnout do reportu \"Nepřihlášení a neubytovaní\"',
        'V reportu Nepřihlášení a neubytovaní vypravěči se lidé na této židli vypisují'),
       (1026, 'Titul „organizátor“', 'V různých výpisech se označuje jako organizátor'),
       (1027, 'Unikátní židle', 'Uživatel může mít jen jednu židli s tímto právem'),
       (1028, 'Bez bonusu za vedení aktivit',
        'Nedostává bonus za vedení aktivit ani za účast na technických aktivitách'),
       (1029, 'Čtvrteční noc zdarma', ''),
       (1030, 'Páteční noc zdarma', ''),
       (1031, 'Sobotní noc zdarma', ''),
       (1032, 'Hromadná aktivace aktivit', 'Může použít \"Aktivovat hromadně\" v aktivitách'),
       (1033, 'Změna práv', 'Může měnit práva rolím a měnit role uživatelům'),
       (1034, 'Provádí korekce', 'Může nastavit checkbox u aktivity o provedení korekce.'),
       (1035, 'Jedno jakékoliv tričko zdarma', '(právo na dvě trička má přednost)'),
       (1036, 'Nedělní ubytování', '(není potřeba pokud má neděli zdarma)'),
       (1037, 'Ubytování může objednat jednu noc', '');

CREATE TABLE reporty_log_pouziti
(
    id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_reportu   bigint(20) unsigned NOT NULL,
    id_uzivatele bigint(20) unsigned NOT NULL,
    format       varchar(10)         NOT NULL,
    cas_pouziti  datetime     DEFAULT NULL,
    casova_zona  varchar(100) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY (id_reportu),
    KEY (id_uzivatele),
    KEY (id_reportu, id_uzivatele),
    FOREIGN KEY (id_reportu) REFERENCES reporty (id) ON DELETE CASCADE,
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE reporty_quick
(
    id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    nazev       varchar(100)        NOT NULL,
    dotaz       longtext            NOT NULL,
    format_xlsx tinyint(1)          NOT NULL DEFAULT 1,
    format_html tinyint(1)          NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
);

CREATE TABLE akce_import
(
    id_akce_import  bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_uzivatele    bigint(20) unsigned NOT NULL,
    google_sheet_id varchar(128)        NOT NULL,
    cas             datetime            NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id_akce_import),
    KEY (id_uzivatele),
    KEY (google_sheet_id),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE uzivatele_slucovani_log
(
    id                         bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_smazaneho_uzivatele     bigint(20) unsigned NOT NULL,
    id_noveho_uzivatele        bigint(20) unsigned NOT NULL,
    zustatek_smazaneho_puvodne int(11)             NOT NULL,
    zustatek_noveho_puvodne    int(11)             NOT NULL,
    email_smazaneho            varchar(255)        NOT NULL,
    email_noveho_puvodne       varchar(255)        NOT NULL,
    zustatek_noveho_aktualne   int(11)             NOT NULL,
    email_noveho_aktualne      varchar(255)        NOT NULL,
    kdy                        timestamp           NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY (id_smazaneho_uzivatele),
    KEY (id_noveho_uzivatele),
    KEY (kdy)
);

CREATE TABLE uzivatele_url
(
    id_url_uzivatele bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_uzivatele     bigint(20) unsigned NOT NULL,
    url              varchar(255)        NOT NULL,
    PRIMARY KEY (id_url_uzivatele),
    UNIQUE KEY (url),
    KEY (id_uzivatele),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE shop_predmety
(
    id_predmetu       bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    nazev             varchar(255)        NOT NULL,
    kod_predmetu      varchar(255)        NOT NULL,
    model_rok         smallint(6)         NOT NULL,
    cena_aktualni     decimal(6, 2)       NOT NULL,
    stav              smallint(6)         NOT NULL,
    nabizet_do        datetime                     DEFAULT NULL,
    kusu_vyrobeno     smallint(6)                  DEFAULT NULL,
    typ               smallint(6)         NOT NULL COMMENT '1-předmět, 2-ubytování, 3-tričko, 4-jídlo, 5-vstupné, 6-parcon, 7-vyplaceni',
    je_letosni_hlavni tinyint(1)          NOT NULL DEFAULT 0,
    ubytovani_den     smallint(6)                  DEFAULT NULL,
    popis             varchar(2000)       NOT NULL,
    PRIMARY KEY (id_predmetu),
    UNIQUE KEY (nazev, model_rok),
    UNIQUE KEY (kod_predmetu, model_rok)
);

CREATE TABLE shop_nakupy
(
    id_nakupu      bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_uzivatele   bigint(20) unsigned NOT NULL,
    id_objednatele bigint(20) unsigned          DEFAULT NULL,
    id_predmetu    bigint(20) unsigned NOT NULL,
    rok            smallint(6)         NOT NULL,
    cena_nakupni   decimal(6, 2)       NOT NULL COMMENT 'aktuální cena v okamžiku nákupu (bez slev)',
    datum          timestamp           NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id_nakupu),
    KEY (id_uzivatele),
    KEY (id_objednatele),
    KEY (id_predmetu),
    KEY (rok, id_uzivatele),
    FOREIGN KEY (id_predmetu) REFERENCES shop_predmety (id_predmetu),
    FOREIGN KEY (id_objednatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL,
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE shop_nakupy_zrusene
(
    id_nakupu     bigint(20) unsigned NOT NULL,
    id_uzivatele  bigint(20) unsigned NOT NULL,
    id_predmetu   bigint(20) unsigned NOT NULL,
    rocnik        smallint(6)         NOT NULL,
    cena_nakupni  decimal(6, 2)       NOT NULL COMMENT 'aktuální cena v okamžiku nákupu (bez slev)',
    datum_nakupu  timestamp           NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    datum_zruseni timestamp           NOT NULL DEFAULT current_timestamp(),
    zdroj_zruseni varchar(255)                 DEFAULT NULL,
    PRIMARY KEY (id_nakupu),
    KEY (id_uzivatele),
    KEY (id_predmetu),
    KEY (datum_zruseni),
    KEY (zdroj_zruseni),
    FOREIGN KEY (id_predmetu) REFERENCES shop_predmety (id_predmetu),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
);

CREATE TABLE prava_role
(
    id_role  bigint(20) NOT NULL,
    id_prava bigint(20) NOT NULL,
    PRIMARY KEY (id_role, id_prava),
    KEY (id_role),
    KEY (id_prava),
    FOREIGN KEY (id_prava) REFERENCES r_prava_soupis (id_prava) ON DELETE CASCADE,
    FOREIGN KEY (id_role) REFERENCES role_seznam (id_role) ON DELETE CASCADE
);
INSERT INTO prava_role
VALUES (-202500028, 1031),
       (-202500025, 1004),
       (-202500025, 1008),
       (-202500025, 1016),
       (-202500025, 1025),
       (-202500025, 1028),
       (-202500025, 1037),
       (-202500024, 4),
       (-202500024, 1004),
       (-202500024, 1012),
       (-202500024, 1016),
       (-202500024, 1021),
       (-202500024, 1025),
       (-202500024, 1037),
       (-202500023, 1016),
       (-202500019, 1018),
       (-202500018, 1015),
       (-202500013, 4),
       (-202500013, 109),
       (-202500013, 1016),
       (-202500013, 1025),
       (-202500013, 1027),
       (-202500013, 1028),
       (-202500013, 1037),
       (-202500008, 100),
       (-202500008, 101),
       (-202500008, 1016),
       (-202500008, 1025),
       (-202500008, 1037),
       (-202500007, 1008),
       (-202500007, 1016),
       (-202500006, 4),
       (-202500006, 109),
       (-202500006, 1012),
       (-202500006, 1016),
       (-202500006, 1021),
       (-202500006, 1025),
       (-202500006, 1027),
       (-202500006, 1037),
       (2, 4),
       (2, 100),
       (2, 101),
       (2, 102),
       (2, 103),
       (2, 104),
       (2, 105),
       (2, 106),
       (2, 107),
       (2, 109),
       (2, 1002),
       (2, 1003),
       (2, 1005),
       (2, 1008),
       (2, 1016),
       (2, 1020),
       (2, 1021),
       (2, 1022),
       (2, 1023),
       (2, 1024),
       (2, 1025),
       (2, 1026),
       (2, 1027),
       (2, 1028),
       (2, 1037),
       (9, 4),
       (9, 5),
       (9, 1028),
       (15, 1002),
       (15, 1003),
       (15, 1016),
       (15, 1037),
       (16, 8),
       (16, 103),
       (20, 108),
       (20, 110),
       (20, 111),
       (21, 4),
       (21, 100),
       (21, 101),
       (21, 102),
       (21, 103),
       (21, 104),
       (21, 105),
       (21, 106),
       (21, 107),
       (21, 109),
       (21, 1002),
       (21, 1003),
       (21, 1004),
       (21, 1008),
       (21, 1012),
       (21, 1015),
       (21, 1016),
       (21, 1018),
       (21, 1021),
       (21, 1022),
       (21, 1024),
       (21, 1025),
       (21, 1026),
       (21, 1027),
       (21, 1037),
       (22, 4),
       (22, 100),
       (22, 101),
       (22, 102),
       (22, 103),
       (22, 104),
       (22, 105),
       (22, 106),
       (22, 107),
       (22, 109),
       (22, 1002),
       (22, 1003),
       (22, 1004),
       (22, 1012),
       (22, 1015),
       (22, 1016),
       (22, 1018),
       (22, 1020),
       (22, 1021),
       (22, 1022),
       (22, 1024),
       (22, 1025),
       (22, 1026),
       (22, 1027),
       (22, 1037),
       (23, 1032),
       (23, 1033),
       (24, 111),
       (25, 9),
       (25, 1034),
       (26, 4),
       (26, 103),
       (26, 1004),
       (26, 1012),
       (26, 1016),
       (26, 1021),
       (26, 1025),
       (26, 1026),
       (26, 1035),
       (26, 1036),
       (26, 1037),
       (27, 102),
       (27, 1034),
       (28, 112);

CREATE TABLE uzivatele_role
(
    id_uzivatele bigint(20) unsigned NOT NULL,
    id_role      bigint(20)          NOT NULL,
    posazen      timestamp           NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    posadil      bigint(20) unsigned          DEFAULT NULL,
    id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id),
    UNIQUE KEY (id_uzivatele, id_role),
    KEY (id_uzivatele),
    KEY (id_role),
    KEY (posadil),
    FOREIGN KEY (posadil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL,
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE,
    FOREIGN KEY (id_role) REFERENCES role_seznam (id_role) ON DELETE CASCADE
);

CREATE TABLE uzivatele_role_log
(
    id_uzivatele bigint(20) unsigned NOT NULL,
    id_role      bigint(20)          NOT NULL,
    id_zmenil    bigint(20) unsigned          DEFAULT NULL,
    zmena        varchar(128)        NOT NULL,
    kdy          timestamp           NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (id),
    KEY (id_uzivatele),
    KEY (id_role),
    KEY (id_zmenil),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE,
    FOREIGN KEY (id_role) REFERENCES role_seznam (id_role) ON DELETE CASCADE,
    FOREIGN KEY (id_zmenil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL
);

CREATE TABLE systemove_nastaveni
(
    id_nastaveni     bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    klic             varchar(128)        NOT NULL,
    hodnota          varchar(255)        NOT NULL DEFAULT '',
    vlastni          tinyint(1)                   DEFAULT 0,
    datovy_typ       varchar(24)         NOT NULL DEFAULT 'string',
    nazev            varchar(255)        NOT NULL,
    popis            varchar(1028)       NOT NULL DEFAULT '',
    zmena_kdy        timestamp           NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    skupina          varchar(128)                 DEFAULT NULL,
    poradi           int(10) unsigned             DEFAULT NULL,
    pouze_pro_cteni  tinyint(1)                   DEFAULT 0,
    rocnik_nastaveni int(11)             NOT NULL DEFAULT -1,
    PRIMARY KEY (id_nastaveni),
    UNIQUE KEY (klic, rocnik_nastaveni),
    UNIQUE KEY (nazev, rocnik_nastaveni),
    KEY (skupina)
);
INSERT INTO systemove_nastaveni
VALUES (1, 'KURZ_EURO', '23.8', 1, 'number', 'Kurz Eura', 'Kolik kč je pro nás letos jedno €', '2025-07-05 14:25:08',
        'Finance', 1, 0, -1),
       (4, 'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU', '440', 1, 'integer', 'Bonus za vedení 3-5h aktivity',
        'Kolik dostane vypravěč standardní aktivity, která trvala tři až pět hodin', '2025-05-11 18:57:36', 'Finance',
        4, 0, -1),
       (9, 'GC_BEZI_OD', '2024-07-18 12:00:00', 0, 'datetime', 'Začátek Gameconu', 'Datum a čas, kdy začíná Gamecon',
        '2024-12-03 11:09:20', 'Časy', 5, 0, -1),
       (10, 'GC_BEZI_DO', '2022-07-01 00:00:00', 0, 'datetime', 'Konec Gameconu', 'Datum a čas, kdy končí Gamecon',
        '2023-05-11 18:28:12', 'Časy', 6, 0, -1),
       (11, 'REG_GC_OD', '2025-05-12 21:25:00', 1, 'datetime', 'Začátek registrací účastníků',
        'Od kdy se mohou začít účastníci registrovat na Gamecon', '2025-05-12 19:07:26', 'Časy', 7, 0, -1),
       (12, 'PRVNI_VLNA_KDY', '2025-05-15 20:25:00', 1, 'datetime', 'Začátek první vlny aktivit',
        'Kdy se poprvé hromadně změní aktivity Připravené k aktivaci na Aktivované', '2024-12-29 11:54:11', 'Časy', 20,
        0, -1),
       (15, 'NEPLATIC_CASTKA_VELKY_DLUH', '251', 1, 'number', 'Ještě příliš velký dluh neplatiče',
        'Kolik kč je pro nás stále tak velký dluh, že mu hrozí odhlášení jako neplatiči', '2024-07-14 12:33:05',
        'Neplatič', NULL, 0, -1),
       (16, 'NEPLATIC_CASTKA_POSLAL_DOST', '1000', 1, 'number', 'Už dost velká částka proti odhlášení',
        'Kolik kč musí letos účastník poslat, abychom ho nezařadili do neplatičů', '2023-05-11 18:28:12', 'Neplatič',
        NULL, 0, -1),
       (17, 'NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN', '7', 1, 'integer',
        'Počet dní od registrace před hromadným odhlašováním kdy je chráněn',
        'Kolik nejvýše dní od registrace do odhlašovací vlny neplatičů je nový účastník ještě chráněn, aby nebyl brán jako neplatič',
        '2023-05-11 18:28:12', 'Neplatič', NULL, 0, -1),
       (18, 'AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM', '20', 1, 'int',
        'Kolik minut před začátkem lze už aktivitu editovat',
        'Kolik minut před začátkem aktivity už může vypravěč editovat přihlášené', '2023-05-11 18:28:12', 'Aktivita',
        NULL, 0, -1),
       (19, 'UCASTNIKY_LZE_PRIDAVAT_X_MINUT_PO_KONCI_AKTIVITY', '60', 1, 'int',
        'Kolik minut po konci aktivity lze potvrzovat účastníky',
        'Kolik minut může ještě vypravěč zpětně přidávat účastníky a potvrzovat jejich účast od okamžiku jejího skončení. Neplatí pro odebírání účastníků.',
        '2023-05-11 18:28:12', 'Aktivita', NULL, 0, -1),
       (20, 'PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY', '10', 1, 'int',
        'Kolik minut před začátkem aktivity je \"na poslední chvíli\"',
        'Nejvíce před kolika minutami před začátkem aktivity se účastník přihlásí, aby Moje aktivity ukázaly varování, že je nejspíš na cestě a ať na něj počkají',
        '2023-05-11 18:28:12', 'Aktivita', NULL, 0, -1),
       (21, 'AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU', '45', 1, 'int',
        'Po kolika minutách se aktivita sama zamkne',
        'Po jaké době běžící aktivitu uzamkne automat, pokud to někdo neudělá ručně - může to být se zpožděním, automat se pouští jen jednou za hodinu',
        '2023-05-11 18:28:12', 'Aktivita', NULL, 0, -1),
       (22, 'UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI', '60', 1, 'int',
        'Kdy vypravěče upozorníme že nezavřel',
        'Po jaké době od konce aktivity odešleme vypravěčům mail, že aktivitu neuzavřeli - může to být se zpožděním, automat se pouští jen jednou za hodinu',
        '2023-05-11 18:28:12', 'Aktivita', NULL, 0, -1),
       (24, 'TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY', 'vraceni zustatku GC ID:', 0, 'text',
        'Text pro rozpoznání odchozí GC platby',
        'Přesné znění textu v \"Poznámka\", za kterým následuje ID účastníka GC (jemuž odesíláme z banky peníze) abychom podle tohoto textu spárovali odchozí platbu (poradí si s různou velikostí písmen i chybějící diakritikou)',
        '2024-06-21 18:27:47', 'Finance', 12, 0, -1),
       (25, 'UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2025-07-13', 1, 'date', 'Ukončení prodeje bytování na konci dne',
        'Datum, do kdy ještě (včetně) lze v přihlášce měnit ubytování, než se zamkne', '2025-01-25 13:05:07', 'Časy',
        13, 0, -1),
       (26, 'JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2025-07-13', 1, 'date', 'Ukončení prodeje jídla na konci dne',
        'Datum, do kdy ještě (včetně) lze v přihlášce měnit jídlo, než se zamkne', '2025-01-25 13:05:09', 'Časy', 14, 0,
        -1),
       (27, 'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2025-07-06', 1, 'date',
        'Ukončení prodeje předmětů (vyjma oblečení) na konci dne',
        'Datum, do kdy ještě (včetně) lze v přihlášce měnit předměty, než se zamknou', '2025-01-25 13:03:33', 'Časy',
        15, 0, -1),
       (28, 'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2025-06-10', 1, 'date',
        'Ukončení prodeje potištěných triček a tílek na konci dne',
        'Datum, do kdy ještě (včetně) lze v přihlášce měnit trička a tílka, než se zamknou', '2025-06-08 11:02:35',
        'Časy', 16, 0, -1),
       (29, 'REG_GC_DO', '', 0, 'datetime', 'Ukončení registrací přes web',
        'Do kdy se lze registrovat na Gamecon přes přihlášlu na webu', '2023-05-11 18:28:12', 'Časy', 17, 0, -1),
       (31, 'UCASTNIKY_LZE_PRIDAVAT_X_DNI_PO_GC_U_NEUZAVRENE_PREZENCE', '30', 1, 'integer',
        'Do kolika dní po GC lze přidat účastníka',
        'Kolik dní po konci GC lze ještě přidávat účastníky na Neuzavřenou aktivitu', '2023-05-11 18:28:12', 'Časy', 18,
        0, -1),
       (32, 'ROCNIK', '2025', 1, 'integer', 'Ročník', 'Který ročník GC je aktivní', '2024-12-03 11:03:07', 'Časy', 19,
        1, -1),
       (34, 'DRUHA_VLNA_KDY', '2025-06-05 20:25:00', 1, 'datetime', 'Začátek druhé vlny aktivit',
        'Kdy se podruhé hromadně změní aktivity Připravené k aktivaci na Aktivované', '2024-12-29 11:53:29', 'Časy', 21,
        0, -1),
       (35, 'TRETI_VLNA_KDY', '2024-07-01 20:24:00', 0, 'datetime', 'Začátek třetí vlny aktivit',
        'Kdy se potřetí hromadně změní aktivity Připravené k aktivaci na Aktivované', '2024-02-04 10:59:04', 'Časy', 22,
        0, -1),
       (36, 'PRUMERNE_LONSKE_VSTUPNE', '113.993650793', 1, 'number', 'Průměrné loňské vstupné',
        'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného', '2023-05-11 18:28:13', 'Finance', 22, 1,
        2016),
       (37, 'PRUMERNE_LONSKE_VSTUPNE', '122.379518072', 1, 'number', 'Průměrné loňské vstupné',
        'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného', '2023-05-11 18:28:13', 'Finance', 22, 1,
        2017),
       (38, 'PRUMERNE_LONSKE_VSTUPNE', '126.543026706', 1, 'number', 'Průměrné loňské vstupné',
        'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného', '2023-05-11 18:28:13', 'Finance', 22, 1,
        2018),
       (39, 'PRUMERNE_LONSKE_VSTUPNE', '114.132394366', 1, 'number', 'Průměrné loňské vstupné',
        'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného', '2023-05-11 18:28:13', 'Finance', 22, 1,
        2019),
       (40, 'PRUMERNE_LONSKE_VSTUPNE', '81.578828828', 1, 'number', 'Průměrné loňské vstupné',
        'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného', '2023-05-11 18:28:14', 'Finance', 22, 1,
        2020),
       (41, 'PRUMERNE_LONSKE_VSTUPNE', '0.0', 1, 'number', 'Průměrné loňské vstupné',
        'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného', '2023-05-11 18:28:14', 'Finance', 22, 1,
        2021),
       (42, 'PRUMERNE_LONSKE_VSTUPNE', '122.881132075', 1, 'number', 'Průměrné loňské vstupné',
        'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného', '2023-05-11 18:28:14', 'Finance', 22, 1,
        2022),
       (43, 'PRUMERNE_LONSKE_VSTUPNE', '164.42', 1, 'number', 'Průměrné loňské vstupné',
        'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného<hr><i>výchozí hodnota</i>: <i>&gt;&gt;&gt;není&lt;&lt;&lt;</i>',
        '2023-05-11 18:28:14', 'Finance', 22, 1, 2023),
       (44, 'POSILAT_MAIL_O_ODHLASENI_A_UVOLNENEM_UBYTOVANI', '1', 1, 'boolean',
        'Poslat nám e-mail o uvolněném ubytování',
        'Když se účastník odhlásí z GC a měl objednané ubytování, tak nám o tom přijde email na info@gamecon.cz',
        '2025-07-16 12:59:46', 'Notifikace', 23, 0, -1),
       (45, 'HROMADNE_ODHLASOVANI_1', '', 0, 'datetime', 'První hromadné odhlašování',
        'Kdy budou poprvé hromadně odhlášeni neplatiči', '2025-06-23 10:19:46', 'Časy', 23, 0, -1),
       (46, 'HROMADNE_ODHLASOVANI_2', '2025-07-13 23:59:59', 1, 'datetime', 'Druhé hromadné odhlašování',
        'Kdy budou podruhé hromadně odhlášeni neplatiči', '2024-12-29 11:52:08', 'Časy', 24, 0, -1),
       (47, 'HROMADNE_ODHLASOVANI_3', '2025-05-01 23:59:59', 1, 'datetime', 'Třetí hromadné odhlašování',
        'Kdy budou potřetí hromadně odhlášeni neplatiči', '2025-01-31 21:27:06', 'Časy', 25, 0, -1),
       (48, 'KOLIK_MINUT_JE_ODHLASENI_AKTIVITY_BEZ_POKUTY', '5', 1, 'integer',
        'Kolik minut je odhlášení aktivity bez pokuty',
        'Když se účastník přihlásí na aktivitu a do několika minut se zase odhlásí, tak mu nebudeme počítat storno ani pár hodin před jejím začátkem',
        '2023-06-05 14:44:08', 'Aktivita', 26, 0, -1),
       (49, 'PRUMERNE_LONSKE_VSTUPNE', '166.84', 1, 'number', 'Průměrné loňské vstupné',
        'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného<hr><i>výchozí hodnota</i>: <i>&gt;&gt;&gt;není&lt;&lt;&lt;</i><hr><i>výchozí hodnota</i>: <i>&gt;&gt;&gt;není&lt;&lt;&lt;</i>',
        '2023-05-11 18:28:14', 'Finance', 22, 1, 2024),
       (50, 'SLEVA_ORGU_NA_JIDLO_CASTKA', '25', 1, 'integer', 'Jakou slevu mají mít orgové na jídlo',
        'Jakou slevu na jídlo mají dostat všichni s rolí \"Jídlo se slevou\"', '2024-07-09 23:52:22', 'Finance', 27, 0,
        -1),
       (51, 'PRUMERNE_LONSKE_VSTUPNE', '201.25', 1, 'number', 'Průměrné loňské vstupné',
        'Abychom mohli zobrazit kostku na posuvníku dobrovolného vstupného<hr><i>výchozí hodnota</i>: <i>&gt;&gt;&gt;není&lt;&lt;&lt;</i><hr><i>výchozí hodnota</i>: <i>&gt;&gt;&gt;není&lt;&lt;&lt;</i><hr><i>výchozí hodnota</i>: <i>&gt;&gt;&gt;není&lt;&lt;&lt;</i>',
        '2023-05-11 18:28:14', 'Finance', 22, 1, 2025);

CREATE TABLE systemove_nastaveni_log
(
    id_nastaveni_log bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_uzivatele     bigint(20) unsigned          DEFAULT NULL,
    id_nastaveni     bigint(20) unsigned NOT NULL,
    hodnota          varchar(256)                 DEFAULT NULL,
    vlastni          tinyint(1)                   DEFAULT NULL,
    kdy              timestamp           NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id_nastaveni_log),
    KEY (id_uzivatele),
    KEY (id_nastaveni),
    FOREIGN KEY (id_nastaveni) REFERENCES systemove_nastaveni (id_nastaveni) ON DELETE CASCADE,
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL
);

CREATE TABLE sjednocene_tagy
(
    id                bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_kategorie_tagu bigint(20) unsigned NOT NULL,
    nazev             varchar(128)        NOT NULL,
    poznamka          longtext            NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (nazev),
    KEY (id_kategorie_tagu),
    FOREIGN KEY (id_kategorie_tagu) REFERENCES kategorie_sjednocenych_tagu (id)
);
INSERT INTO sjednocene_tagy (id_kategorie_tagu, nazev, poznamka)
VALUES (1, 'Eng Only', ''),
       (1, 'Pro pokročilé', ''),
       (2, 'Workshop', ''),
       (2, 'Psychologická', ''),
       (3, 'Komedie', ''),
       (3, 'Akční', ''),
       (4, 'Současnost', ''),
       (4, 'Forgotten Realms', ''),
       (5, 'Vyjednávací', ''),
       (5, 'Vyprávěcí', ''),
       (12, 'Žánrovka', ''),
       (12, 'Vztahová', ''),
       (13, 'Venkovní', ''),
       (13, 'Taneční', ''),
       (14, 'Semi-kooperativní', ''),
       (14, 'Ameritrash', ''),
       (16, 'Dračí doupě', ''),
       (16, 'Debata', ''),
       (17, 'Anime', ''),
       (17, 'JRPG', ''),
       (25, 'Končina', ''),
       (25, 'Taria', ''),
       (26, 'Singapur', ''),
       (26, 'Itálie', ''),
       (36, 'Argumentační', ''),
       (36, 'Kostýmová', ''),
       (37, 'Přemýšlecí', ''),
       (37, 'Odpočinková', ''),
       (38, 'Strategická', ''),
       (41, 'Fate', '(nejasné edice)'),
       (41, 'DrD+', ''),
       (47, 'WH 40k (WG)', ''),
       (47, 'WH 40k: Rogue Trader', ''),
       (50, 'Věk: 14+', ''),
       (50, 'Věk: 15+', ''),
       (13, 'Únikovka', '!!! používá se i pro Rozpočet, neměnit název, nemazat!'),
       (15, 'Malování', '!!! používá se i pro Rozpočet, neměnit název, nemazat!');

CREATE TABLE _table_data_versions
(
    table_name varchar(255) NOT NULL,
    version    int(11)      NOT NULL,
    updated_at timestamp    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (table_name)
);
INSERT INTO _table_data_versions
VALUES ('akce_import', 28, '2025-07-18 12:12:25'),
       ('akce_instance', 35, '2025-07-13 10:34:44'),
       ('akce_lokace', 3, '2025-07-13 15:54:18'),
       ('akce_organizatori', 2459, '2025-09-18 16:52:46'),
       ('akce_prihlaseni', 9343, '2025-09-18 17:53:47'),
       ('akce_prihlaseni_log', 88445, '2025-10-16 12:34:44'),
       ('akce_prihlaseni_spec', 1787, '2025-09-18 17:53:47'),
       ('akce_prihlaseni_stavy', 0, '2025-05-30 07:11:20'),
       ('akce_seznam', 11579, '2025-11-22 12:15:42'),
       ('akce_sjednocene_tagy', 18336, '2025-10-16 12:34:48'),
       ('akce_stav', 0, '2025-05-30 07:11:20'),
       ('akce_stavy_log', 1654, '2025-11-22 12:15:42'),
       ('akce_typy', 0, '2025-05-30 07:11:21'),
       ('google_api_user_tokens', 72, '2025-11-18 23:16:14'),
       ('google_drive_dirs', 2, '2025-05-30 15:29:15'),
       ('hromadne_akce_log', 18, '2025-07-13 22:00:43'),
       ('kategorie_sjednocenych_tagu', 0, '2025-05-30 07:11:21'),
       ('log_udalosti', 277, '2025-07-19 20:33:29'),
       ('medailonky', 4, '2025-06-10 19:57:37'),
       ('migrations', 26, '2025-11-18 17:24:34'),
       ('mutex', 56, '2025-07-18 12:12:25'),
       ('newsletter_prihlaseni', 0, '2025-09-27 17:43:36'),
       ('newsletter_prihlaseni_log', 0, '2025-09-27 17:43:37'),
       ('novinky', 646, '2025-11-18 14:51:56'),
       ('obchod_bunky', 1904, '2025-07-14 19:39:30'),
       ('obchod_mrizky', 119, '2025-07-14 19:39:30'),
       ('platby', 3699, '2026-01-02 08:45:49'),
       ('platne_role', 7, '2025-09-16 13:13:02'),
       ('platne_role_uzivatelu', 7, '2025-09-16 13:13:02'),
       ('prava_role', 7, '2025-11-03 14:29:54'),
       ('reporty', 14, '2025-11-18 17:24:30'),
       ('reporty_log_pouziti', 608, '2026-01-01 18:07:21'),
       ('reporty_quick', 106, '2025-12-10 19:06:39'),
       ('role_seznam', 1, '2025-09-16 13:13:02'),
       ('role_texty_podle_uzivatele', 0, '2025-05-30 07:11:21'),
       ('r_prava_soupis', 1, '2025-09-16 13:13:02'),
       ('shop_nakupy', 3486, '2025-09-18 17:53:47'),
       ('shop_nakupy_zrusene', 311, '2025-09-18 17:46:51'),
       ('shop_predmety', 101, '2025-07-14 10:17:23'),
       ('sjednocene_tagy', 18, '2025-06-26 12:54:45'),
       ('slevy', 2, '2025-07-19 11:02:39'),
       ('stranky', 63, '2025-11-06 10:49:03'),
       ('systemove_nastaveni', 6, '2025-07-16 12:59:46'),
       ('systemove_nastaveni_log', 6, '2025-07-16 12:59:46'),
       ('texty', 735, '2025-11-09 15:02:47'),
       ('ubytovani', 2085, '2025-09-18 17:46:22'),
       ('uzivatele_hodnoty', 33311, '2026-01-03 00:35:47'),
       ('uzivatele_role', 17955, '2026-01-02 11:53:02'),
       ('uzivatele_role_log', 18291, '2025-10-16 12:34:48'),
       ('uzivatele_role_podle_rocniku', 5297254, '2026-01-02 23:00:32'),
       ('uzivatele_slucovani_log', 0, '2025-09-22 17:30:23'),
       ('uzivatele_url', 1928, '2025-12-07 23:17:40'),
       ('_vars', 0, '2025-05-30 07:11:20');

CREATE TABLE _vars
(
    name  varchar(64) NOT NULL,
    value varchar(4096) DEFAULT NULL,
    PRIMARY KEY (name)
);
INSERT INTO _vars
VALUES ('flee_default_hash', '2d6a1cc28b3f32e7e7b9eaece414f8c1'),
       ('flee_default_timestamp', '1531514060'),
       ('flee_default_version', '41');

CREATE TABLE _tables_used_in_view_data_versions
(
    view_name          varchar(255) NOT NULL,
    table_used_in_view varchar(255) NOT NULL,
    PRIMARY KEY (view_name, table_used_in_view),
    KEY (table_used_in_view),
    FOREIGN KEY (view_name) REFERENCES _table_data_versions (table_name) ON DELETE CASCADE,
    FOREIGN KEY (table_used_in_view) REFERENCES _table_data_versions (table_name) ON DELETE CASCADE
);
INSERT INTO _tables_used_in_view_data_versions
VALUES ('platne_role', 'role_seznam'),
       ('platne_role', 'systemove_nastaveni'),
       ('platne_role_uzivatelu', 'role_seznam'),
       ('platne_role_uzivatelu', 'systemove_nastaveni');

CREATE TABLE akce_typy
(
    id_typu         int(10) unsigned NOT NULL,
    typ_1p          varchar(32)      NOT NULL,
    typ_1pmn        varchar(32)      NOT NULL,
    url_typu_mn     varchar(32)      NOT NULL,
    stranka_o       int(10) unsigned NOT NULL,
    poradi          int(11)          NOT NULL,
    mail_neucast    tinyint(1)       NOT NULL DEFAULT 0 COMMENT 'poslat mail účastníkovi, pokud nedorazí',
    popis_kratky    varchar(255)     NOT NULL,
    aktivni         tinyint(1)                DEFAULT 1,
    zobrazit_v_menu tinyint(1)                DEFAULT 1,
    kod_typu        varchar(20)               DEFAULT NULL COMMENT 'kód pro identifikaci například v rozpočtovém reportu',
    PRIMARY KEY (id_typu),
    KEY (stranka_o),
    FOREIGN KEY (stranka_o) REFERENCES stranky (id_stranky)
);
INSERT INTO akce_typy
VALUES (0, '(bez typu – organizační)', '(bez typu – organizační)', 'organizacni', 1, -1, 0, '', 1, 0, NULL),
       (1, 'turnaj v deskovkách', 'turnaje v deskovkách', 'turnaje', 1, 2, 0,
        'V oblíbených nebo nových deskovkách! Jako v každém správném turnaji, můžeš i tady vyhrát nějaké ceny! Třeba právě tu deskovku!',
        1, 1, 'Turn'),
       (2, 'larp', 'larpy', 'larpy', 1, 5, 1,
        'Staň se postavou dramatického nebo komediálního příběhu a prožij naplno každý okamžik, jako by svět okolo neexistoval.',
        1, 1, 'Larp'),
       (3, 'přednáška', 'přednášky', 'prednasky', 1, 10, 0,
        'Hraní je fajn, to jo, ale v poznání je moc! Přijď si poslechnout zajímavé speakery všeho druhu.', 1, 1,
        'Prednasky'),
       (4, 'RPG', 'RPG', 'rpg', 1, 6, 1,
        'K tomu, aby ses přenesl/a do jiného světa a zažil/a napínavé dobrodružství ti budou stačit jen kostky a vlastní představivost.',
        1, 1, 'RPG'),
       (5, 'workshop', 'workshopy', 'workshopy', 1, -2, 0, '', 0, 0, NULL),
       (6, 'wargaming', 'wargaming', 'wargaming', 1, 4, 1,
        'Armády figurek na bitevním poli! Přijď si zahrát pořádnou řežbu zasazenou do tvého oblíbeného světa!', 1, 1,
        'WG'),
       (7, 'bonus', 'akční a bonusové aktivity', 'bonusy', 1, 9, 0,
        'Nebaví tě pořád sedět u stolu? Tak pro tebe tu máme tyhle pohybovky a další zábavný program.', 1, 1, 'AH'),
       (8, 'legendy klubu dobrodruhů', 'legendy klubu dobrodruhů', 'legendy', 1, 8, 1,
        'Pára, magie a víly. O tom je svět Příběhů Impéria. Poskládejte společně s dalšími družinami dohromady příběh, o kterém napíšou v The Times!',
        1, 1, 'LKD'),
       (9, 'mistrovství v DrD', 'mistrovství v DrD', 'drd', 1, 7, 0,
        'Dračák už nejspíš znáš. Ale znáš taky Mistrovství v Dračáku? Dej dohromady družinu a vyhraj tenhle šampionát ve třech soutěžních kolech.',
        1, 1, 'DrD'),
       (10, 'technická', 'organizační výpomoc', 'organizacni-vypomoc', 1, -1, 0, '', 1, 0, NULL),
       (11, 'epic', 'epické deskovky', 'epic', 1, 3, 1,
        'Chceš si zahrát nějakou velkou strategickou nebo atmosférickou hru? Tady si určitě vybereš!', 1, 1, 'Epic'),
       (12, 'doprovodný program', 'doprovodný program', 'doprovodny-program', 1, 11, 0,
        'Přijde ti to všechno málo? Nevadí, kromě všeho ostatního tu máme i koncert a nějaké ty večírky. ', 1, 1, NULL),
       (13, 'deskoherna', 'deskoherna', 'deskoherna', 1, 1, 0,
        'Deskoherna je zcela zdarma a otevřená téměř celý den. Navíc tu najdeš organizátory a vydavatele, kteří ti hry vysvětlí.',
        1, 1, NULL),
       (102, 'brigádnická', 'brigádnické', 'brigadnicke', 1, -3, 0, 'Placená výpomoc Gameconu', 1, 0, NULL);

CREATE TABLE uzivatele_role_podle_rocniku
(
    id           bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_uzivatele bigint(20) unsigned NOT NULL,
    id_role      bigint(20)          NOT NULL,
    od_kdy       datetime            NOT NULL,
    rocnik       int(11)             NOT NULL,
    PRIMARY KEY (id),
    KEY (rocnik),
    KEY (id_uzivatele),
    KEY (id_role),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE,
    FOREIGN KEY (id_role) REFERENCES role_seznam (id_role) ON DELETE CASCADE
);



CREATE TABLE akce_instance
(
    id_instance    bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_hlavni_akce bigint(20) unsigned NOT NULL,
    PRIMARY KEY (id_instance),
    KEY (id_hlavni_akce)
);

CREATE TABLE akce_seznam
(
    id_akce          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    patri_pod        bigint(20) unsigned          DEFAULT NULL,
    nazev_akce       varchar(255)        NOT NULL,
    url_akce         varchar(64)                  DEFAULT NULL,
    zacatek          datetime                     DEFAULT NULL,
    konec            datetime                     DEFAULT NULL,
    lokace           bigint(20) unsigned          DEFAULT NULL,
    kapacita         int(11)             NOT NULL,
    kapacita_f       int(11)             NOT NULL,
    kapacita_m       int(11)             NOT NULL,
    cena             int(11)             NOT NULL,
    bez_slevy        tinyint(1)          NOT NULL COMMENT 'na aktivitu se neuplatňují slevy',
    nedava_bonus     tinyint(1)          NOT NULL COMMENT 'aktivita negeneruje organizátorovi bonus za vedení aktivity',
    typ              int(10) unsigned    NOT NULL,
    dite             varchar(64)                  DEFAULT NULL COMMENT 'potomci oddělení čárkou',
    rok              int(11)             NOT NULL,
    stav             int(10) unsigned    NOT NULL DEFAULT 1,
    teamova          tinyint(1)          NOT NULL,
    team_min         int(11)                      DEFAULT NULL COMMENT 'minimální velikost teamu',
    team_max         int(11)                      DEFAULT NULL COMMENT 'maximální velikost teamu',
    team_kapacita    int(11)                      DEFAULT NULL COMMENT 'max. počet týmů, pokud jde o další kolo týmové aktivity',
    team_nazev       varchar(255)                 DEFAULT NULL,
    zamcel           bigint(20) unsigned          DEFAULT NULL COMMENT 'případně kdo zamčel aktivitu pro svůj team',
    zamcel_cas       datetime                     DEFAULT NULL COMMENT 'případně kdy zamčel aktivitu',
    popis            longtext            NOT NULL COMMENT 'markdown',
    popis_kratky     varchar(255)        NOT NULL,
    vybaveni         longtext            NOT NULL,
    team_limit       int(11)                      DEFAULT NULL COMMENT 'uživatelem (vedoucím týmu) nastavený limit kapacity menší roven team_max, ale větší roven team_min. Prostřednictvím on update triggeru kontrolována tato vlastnost a je-li non-null, tak je tato kapacita nastavena do sloupce kapacita',
    probehla_korekce tinyint(1)          NOT NULL DEFAULT 0,
    PRIMARY KEY (id_akce),
    UNIQUE KEY (url_akce, rok, typ),
    KEY (patri_pod),
    KEY (lokace),
    KEY (typ),
    KEY (stav),
    KEY (zamcel),
    KEY (rok),
    FOREIGN KEY (typ) REFERENCES akce_typy (id_typu),
    FOREIGN KEY (lokace) REFERENCES akce_lokace (id_lokace) ON DELETE SET NULL,
    FOREIGN KEY (zamcel) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL,
    FOREIGN KEY (patri_pod) REFERENCES akce_instance (id_instance) ON DELETE SET NULL,
    FOREIGN KEY (stav) REFERENCES akce_stav (id_stav)
);

ALTER TABLE akce_instance ADD CONSTRAINT FOREIGN KEY (id_hlavni_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE;

CREATE TABLE akce_prihlaseni
(
    id                  bigint(20) unsigned  NOT NULL AUTO_INCREMENT,
    id_akce             bigint(20) unsigned  NOT NULL,
    id_uzivatele        bigint(20) unsigned  NOT NULL,
    id_stavu_prihlaseni smallint(5) unsigned NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (id_akce, id_uzivatele),
    KEY (id_akce),
    KEY (id_uzivatele),
    KEY (id_stavu_prihlaseni),
    FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce),
    FOREIGN KEY (id_stavu_prihlaseni) REFERENCES akce_prihlaseni_stavy (id_stavu_prihlaseni),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele)
);

CREATE TABLE akce_prihlaseni_log
(
    id_log       bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_akce      bigint(20) unsigned NOT NULL,
    id_uzivatele bigint(20) unsigned NOT NULL,
    kdy          timestamp           NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    typ          varchar(64)                  DEFAULT NULL,
    id_zmenil    bigint(20) unsigned          DEFAULT NULL,
    zdroj_zmeny  varchar(128)                 DEFAULT NULL,
    rocnik       int(10) unsigned             DEFAULT NULL,
    PRIMARY KEY (id_log),
    KEY (id_akce),
    KEY (id_uzivatele),
    KEY (id_zmenil),
    KEY (typ),
    KEY (zdroj_zmeny),
    FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE,
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE,
    FOREIGN KEY (id_zmenil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL
);

CREATE TABLE akce_prihlaseni_spec
(
    id                  bigint(20) unsigned  NOT NULL AUTO_INCREMENT,
    id_akce             bigint(20) unsigned  NOT NULL,
    id_uzivatele        bigint(20) unsigned  NOT NULL,
    id_stavu_prihlaseni smallint(5) unsigned NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY (id_akce, id_uzivatele),
    KEY (id_akce),
    KEY (id_uzivatele),
    KEY (id_stavu_prihlaseni),
    FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce),
    FOREIGN KEY (id_stavu_prihlaseni) REFERENCES akce_prihlaseni_stavy (id_stavu_prihlaseni),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele)
);

CREATE TABLE akce_sjednocene_tagy
(
    id_akce bigint(20) unsigned NOT NULL,
    id_tagu bigint(20) unsigned NOT NULL,
    PRIMARY KEY (id_akce, id_tagu),
    KEY (id_akce),
    KEY (id_tagu),
    FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE,
    FOREIGN KEY (id_tagu) REFERENCES sjednocene_tagy (id) ON DELETE CASCADE
);

CREATE TABLE akce_stavy_log
(
    akce_stavy_log_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    id_akce           bigint(20) unsigned NOT NULL,
    id_stav           int(10) unsigned    NOT NULL,
    kdy               datetime            NOT NULL DEFAULT current_timestamp() COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (akce_stavy_log_id),
    KEY (id_akce),
    KEY (id_stav),
    FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE,
    FOREIGN KEY (id_stav) REFERENCES akce_stav (id_stav) ON DELETE CASCADE
);

CREATE TABLE akce_organizatori
    (
        id_akce      bigint(20) unsigned NOT NULL,
        id_uzivatele bigint(20) unsigned NOT NULL COMMENT 'organizátor',
        PRIMARY KEY (id_akce, id_uzivatele),
        KEY (id_akce),
        KEY (id_uzivatele),
        FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE,
        FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
    );


CREATE VIEW platne_role AS
select role_seznam.id_role        AS id_role,
       role_seznam.kod_role       AS kod_role,
       role_seznam.nazev_role     AS nazev_role,
       role_seznam.popis_role     AS popis_role,
       role_seznam.rocnik_role    AS rocnik_role,
       role_seznam.typ_role       AS typ_role,
       role_seznam.vyznam_role    AS vyznam_role,
       role_seznam.skryta         AS skryta,
       role_seznam.kategorie_role AS kategorie_role
from role_seznam
where role_seznam.rocnik_role in
      ((select systemove_nastaveni.hodnota from systemove_nastaveni where systemove_nastaveni.klic = 'ROCNIK' limit 1),
       -1)
   or role_seznam.typ_role = 'ucast';

CREATE VIEW platne_role_uzivatelu AS
select uzivatele_role.id_uzivatele AS id_uzivatele,
       uzivatele_role.id_role      AS id_role,
       uzivatele_role.posazen      AS posazen,
       uzivatele_role.posadil      AS posadil
from (uzivatele_role join platne_role on (uzivatele_role.id_role = platne_role.id_role));
SQL);
