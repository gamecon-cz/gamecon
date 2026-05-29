-- ============================================================================
-- dbgacon — reconstructed schema for the 2011 gamecon.cz site (_ver3 codebase)
-- ============================================================================
--
-- The original `dbgacon` database (old sql.gamecon.cz server, admin/gcDB) is
-- LOST — it was never captured in any backup we can reach (GDrive, Wedos FTP,
-- the host 2011 tree, the host MariaDB). The current prod d16779_gcostra holds
-- rok=2011 rows but in the *modern* schema, incompatible with this flat-PHP era.
--
-- This schema is reverse-engineered from every SQL statement in
--   /srv/ftp/gamecon.cz/www/gamecon.cz/2011/_ver3/**.php
-- (the live 2011 version). Column names are exact (taken from the queries).
-- Types/lengths are best-effort: inferred from usage (md5 -> CHAR(32),
-- counters -> INT, free text -> TEXT, short labels -> VARCHAR). No FK
-- constraints existed in this era (the code joins by hand), so none are added,
-- but the logical relationships are noted in comments.
--
-- charset utf8 (the code issues `SET NAMES utf8` on connect).
-- Engine: MyISAM is required for the FULLTEXT index on stranky_fulltext
-- (this is a 2011 MySQL 5.x app; InnoDB FULLTEXT didn't exist yet). The rest
-- could be InnoDB but MyISAM keeps the whole DB period-authentic.
-- ============================================================================

SET NAMES utf8;
SET sql_mode = '';

-- ---------------------------------------------------------------------------
-- Users. id_uzivatele is auto PK (INSERT passes NULL for it). funkce_uzivatele
-- is the role/active flag: 0 = unconfirmed, 1 = confirmed/active (used as the
-- $_SESSION['prihlasen'] level). avatar = 0 means "no avatar" (silueta.gif),
-- otherwise the integer points at avatary/<n>.jpg. random is a token used in
-- the password-reset / confirm-email links. forum_razeni: 's'/'n' sort flag.
-- gc_id mirrors the upload counter (pocitadla id=2) captured at registration.
-- ---------------------------------------------------------------------------
CREATE TABLE uzivatele_hodnoty (
  id_uzivatele             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  gc_id                    VARCHAR(20)  DEFAULT NULL,
  login_uzivatele          VARCHAR(64)  NOT NULL DEFAULT '',
  jmeno_uzivatele          VARCHAR(128) DEFAULT NULL,
  prijmeni_uzivatele       VARCHAR(128) DEFAULT NULL,
  ulice_a_cp_uzivatele     VARCHAR(255) DEFAULT NULL,
  mesto_uzivatele          VARCHAR(128) DEFAULT NULL,
  stat_uzivatele           VARCHAR(64)  DEFAULT NULL,
  psc_uzivatele            VARCHAR(16)  DEFAULT NULL,
  telefon_uzivatele        VARCHAR(32)  DEFAULT NULL,
  datum_narozeni_uzivatele DATE         DEFAULT NULL,
  heslo_md5                CHAR(32)     DEFAULT NULL,
  funkce_uzivatele         TINYINT      NOT NULL DEFAULT 0,
  email1_uzivatele         VARCHAR(255) DEFAULT NULL,
  email2_uzivatele         VARCHAR(255) DEFAULT NULL,
  souhlas_maily            TINYINT      NOT NULL DEFAULT 0,
  jine_uzivatele           TEXT,
  random                   VARCHAR(64)  DEFAULT NULL,
  forum_razeni             CHAR(1)      NOT NULL DEFAULT 's',
  avatar                   INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id_uzivatele),
  KEY login_uzivatele (login_uzivatele),
  KEY email1_uzivatele (email1_uzivatele),
  KEY random (random)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Site navigation tree. id_polozky is the page id (matches stranky_seznam.
-- id_stranky / stranky_meta.id_stranky / stranky_obsah.id_stranky — one shared
-- id space). nadrazeny_prvek = parent page id (0 = top level). A row whose id
-- appears as someone else's nadrazeny_prvek is a "branch" (opens submenu) and
-- has no own content; a leaf has content in stranky_obsah. poradi_polozky =
-- sort order within parent. skryta = hidden flag. nazev_polozky_zkr = the URL
-- slug segment.
-- ---------------------------------------------------------------------------
CREATE TABLE menu_seznam (
  id_polozky        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nazev_polozky     VARCHAR(255) NOT NULL DEFAULT '',
  nazev_polozky_zkr VARCHAR(255) NOT NULL DEFAULT '',
  nadrazeny_prvek   INT          NOT NULL DEFAULT 0,
  poradi_polozky    INT          NOT NULL DEFAULT 0,
  skryta            TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id_polozky),
  KEY nadrazeny_prvek (nadrazeny_prvek)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- url_stranky = full path (e.g. "o-gameconu", "novinky/novinky") -> id_stranky.
-- ---------------------------------------------------------------------------
CREATE TABLE stranky_seznam (
  id_stranky  INT UNSIGNED NOT NULL,
  url_stranky VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id_stranky),
  KEY url_stranky (url_stranky)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Page body. obsah_stranky: if it starts with '#' the rest is a PHP include
-- name (e.g. "#forum" -> include forum.php); otherwise it's raw HTML echoed
-- into <div class="main">.
-- ---------------------------------------------------------------------------
CREATE TABLE stranky_obsah (
  id_stranky    INT UNSIGNED NOT NULL,
  obsah_stranky MEDIUMTEXT,
  PRIMARY KEY (id_stranky)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Per-page <title>/<meta> tags.
-- ---------------------------------------------------------------------------
CREATE TABLE stranky_meta (
  id_stranky          INT UNSIGNED NOT NULL,
  title_stranky       VARCHAR(255) DEFAULT NULL,
  keywords_stranky    VARCHAR(255) DEFAULT NULL,
  description_stranky  VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id_stranky)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Fulltext search index (rebuilt from page content). MyISAM FULLTEXT on
-- (nadpis, obsah) — the search uses MATCH() AGAINST() IN BOOLEAN MODE plus a
-- relevance-weighted ORDER BY. id_stranky links back to the page.
-- ---------------------------------------------------------------------------
CREATE TABLE stranky_fulltext (
  id_stranky INT UNSIGNED NOT NULL,
  nadpis     VARCHAR(255) NOT NULL DEFAULT '',
  obsah      MEDIUMTEXT,
  PRIMARY KEY (id_stranky),
  FULLTEXT KEY ft_nadpis (nadpis),
  FULLTEXT KEY ft_obsah (obsah)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Forum: top-level sections. jmeno_sekce_mini = URL slug. poradi = sort order.
-- ---------------------------------------------------------------------------
CREATE TABLE forum_sekce (
  id_sekce        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jmeno_sekce     VARCHAR(255) NOT NULL DEFAULT '',
  jmeno_sekce_mini VARCHAR(255) NOT NULL DEFAULT '',
  popis_sekce     TEXT,
  poradi          INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id_sekce),
  KEY jmeno_sekce_mini (jmeno_sekce_mini)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Forum: threads (subsections). patri = parent forum_sekce.id_sekce.
-- posledni_zmena = unix timestamp of last post (used to sort + "max" lookup).
-- precteno here is a per-thread *view/post counter* (incremented), distinct
-- from the per-user read marker in forum_cteno.
-- ---------------------------------------------------------------------------
CREATE TABLE forum_podsekce (
  id_podsekce        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  jmeno_podsekce     VARCHAR(255) NOT NULL DEFAULT '',
  jmeno_podsekce_mini VARCHAR(255) NOT NULL DEFAULT '',
  patri              INT          NOT NULL DEFAULT 0,
  posledni_zmena     INT          NOT NULL DEFAULT 0,
  precteno           INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id_podsekce),
  KEY patri (patri)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Forum: posts. patri = parent forum_podsekce.id_podsekce. uzivatel =
-- uzivatele_hodnoty.id_uzivatele (NULL/0 for guests, who supply
-- jmeno_neregistrovany instead). datum = unix timestamp.
-- ---------------------------------------------------------------------------
CREATE TABLE forum_clanky (
  id_clanku          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  uzivatel           INT          DEFAULT NULL,
  patri              INT          NOT NULL DEFAULT 0,
  obsah              TEXT,
  datum              INT          NOT NULL DEFAULT 0,
  jmeno_neregistrovany VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id_clanku),
  KEY patri (patri)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Forum: per-user "last read" marker per thread. precteno = unix timestamp.
-- ---------------------------------------------------------------------------
CREATE TABLE forum_cteno (
  id_cteni     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_podsekce  INT          NOT NULL DEFAULT 0,
  id_uzivatele INT          NOT NULL DEFAULT 0,
  precteno     INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id_cteni),
  KEY id_podsekce (id_podsekce),
  KEY id_uzivatele (id_uzivatele)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Program: events/sessions. typ = category (used to group by activity type).
-- patri_pod = the page id (menu_seznam.id_polozky) the event is shown under.
-- zacatek/konec = start/end datetime. kapacita = seat capacity.
-- ---------------------------------------------------------------------------
CREATE TABLE akce_seznam (
  id_akce    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nazev_akce VARCHAR(255) NOT NULL DEFAULT '',
  zacatek    DATETIME     DEFAULT NULL,
  konec      DATETIME     DEFAULT NULL,
  kapacita   INT          NOT NULL DEFAULT 0,
  typ        INT          NOT NULL DEFAULT 0,
  patri_pod  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id_akce),
  KEY patri_pod (patri_pod),
  KEY typ (typ)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Program: registrations (user <-> event). id_akce/id_uzivatele are the FK
-- pair the code inserts/deletes/counts against.
-- ---------------------------------------------------------------------------
CREATE TABLE akce_prihlaseni (
  id_prihlaseni INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_akce       INT          NOT NULL DEFAULT 0,
  id_uzivatele  INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id_prihlaseni),
  KEY id_akce (id_akce),
  KEY id_uzivatele (id_uzivatele)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Two named counters. id_pocitadla 1 = (page-hit/visitor counter, funkce.php
-- line ~41) ; id_pocitadla 2 = avatar/gc_id sequence (incremented on each
-- avatar upload / registration). Code reads/updates only ids 1 and 2, so both
-- MUST exist or mysql_result() on an empty result warns.
-- ---------------------------------------------------------------------------
CREATE TABLE pocitadla (
  id_pocitadla     INT UNSIGNED NOT NULL,
  hodnota_pocitadla BIGINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (id_pocitadla)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Sidebar promo boxes, grouped by `sekce` (1=deskovky 2=larp 3=prednaska
-- 4=rpg, per side.php). One is picked at random per section (ORDER BY RAND()).
-- odkaz = link URL, nazev_obr = image filename, nazev = label/alt.
-- ---------------------------------------------------------------------------
CREATE TABLE side_seznam (
  id_side   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  odkaz     VARCHAR(255) DEFAULT NULL,
  nazev_obr VARCHAR(255) DEFAULT NULL,
  nazev     VARCHAR(255) DEFAULT NULL,
  sekce     INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id_side),
  KEY sekce (sekce)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- "Notify me when registration opens" email collector.
-- ---------------------------------------------------------------------------
CREATE TABLE maily_zahajeni (
  id_mailu INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mail     VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (id_mailu),
  KEY mail (mail)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Gallery photos. patri = parent article/gallery id. vystavit = published
-- flag. sirka/vyska = width/height px. Referenced by DELETE/INSERT in the
-- gallery admin; sparse usage in _ver3 (the gallery itself lives on the
-- filesystem under /galerie + /_gaconfoto).
-- ---------------------------------------------------------------------------
CREATE TABLE fotky (
  id_obr   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  patri    INT          NOT NULL DEFAULT 0,
  komentar TEXT,
  vystavit TINYINT      NOT NULL DEFAULT 0,
  sirka    INT          NOT NULL DEFAULT 0,
  vyska    INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (id_obr),
  KEY patri (patri)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------------
-- Minimum seed so the homepage renders without warnings:
--   * the two counters
--   * the default landing page id 47 ("o-gameconu") referenced as the
--     hardcoded $_SESSION['posledni_stranka_id'] in index.php
-- ---------------------------------------------------------------------------
INSERT INTO pocitadla (id_pocitadla, hodnota_pocitadla) VALUES (1, 0), (2, 0);

INSERT INTO menu_seznam (id_polozky, nazev_polozky, nazev_polozky_zkr, nadrazeny_prvek, poradi_polozky, skryta)
  VALUES (47, 'O GameConu', 'o-gameconu', 0, 1, 0);
INSERT INTO stranky_seznam (id_stranky, url_stranky) VALUES (47, 'o-gameconu');
INSERT INTO stranky_meta (id_stranky, title_stranky, keywords_stranky, description_stranky)
  VALUES (47, 'GameCon 2011', 'gamecon, rpg, larp, deskovky', 'Archiv stránek GameConu 2011.');
INSERT INTO stranky_obsah (id_stranky, obsah_stranky)
  VALUES (47, '<h1>GameCon 2011</h1><p>Archivní kopie webu GameCon 2011.</p>');
