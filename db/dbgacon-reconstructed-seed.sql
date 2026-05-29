-- ============================================================================
-- dbgacon — reconstructed SEED data for the 2011 gamecon.cz site (_ver3)
-- ============================================================================
--
-- Run AFTER dbgacon-reconstructed-schema.sql (this file replaces the minimal
-- inline seed that lives at the bottom of the schema file — load the schema,
-- then TRUNCATE the seeded tables, then load this; or just load this on a
-- schema that was created with the inline seed removed).
--
-- Source of truth for the menu tree + the "O GameConu" page copy:
--   /srv/ftp/gamecon.cz/www/gamecon.cz/2011/_ver3/texty na stranky.txt
-- (the developers' own page-content document for the 2011 site).
--
-- The real dynamic data (forum posts, user accounts, per-event program
-- capacities/times, gallery metadata, hit counters) is LOST and cannot be
-- recovered — those tables are intentionally left empty. This seed rebuilds
-- the navigation skeleton + the few pages whose copy survives in the txt file,
-- so the archived site renders authentically rather than as a blank page.
--
-- ID conventions pinned by the _ver3 code (do not renumber these):
--   * id 47 = "o-gameconu" — index.php hardcodes posledni_stranka_id = 47 as
--     the default landing page.
--   * menu id 33 is open by default ($_SESSION['otevrena_menu'] = "#0#33#").
--   * side.php switches on the *parent* menu id of the current page:
--       39 = deskovky, 37 = rpg, 38 = larp, 40 = přednášky.
--     So the four program sections get exactly those ids.
-- ============================================================================

SET NAMES utf8;
SET sql_mode = '';

DELETE FROM menu_seznam;
DELETE FROM stranky_seznam;
DELETE FROM stranky_meta;
DELETE FROM stranky_obsah;
DELETE FROM stranky_fulltext;
DELETE FROM pocitadla;
DELETE FROM forum_sekce;

-- --- counters --------------------------------------------------------------
INSERT INTO pocitadla (id_pocitadla, hodnota_pocitadla) VALUES (1, 0), (2, 0);

-- ---------------------------------------------------------------------------
-- Top-level menu (nadrazeny_prvek = 0). poradi_polozky sets the order.
-- nazev_polozky_zkr is the URL slug. id 33 = GameCon (open by default).
-- ---------------------------------------------------------------------------
INSERT INTO menu_seznam (id_polozky, nazev_polozky, nazev_polozky_zkr, nadrazeny_prvek, poradi_polozky, skryta) VALUES
  (30, 'Novinky',          'novinky',          0,  1, 0),
  (33, 'GameCon',          'gamecon',          0,  2, 0),
  (34, 'Program',          'program',          0,  3, 0),
  (35, 'Přihláška',        'prihlaska',        0,  4, 0),
  (36, 'Fórum',            'forum',            0,  5, 0),
  (41, 'Galerie',          'galerie',          0,  6, 0),
  (42, 'Mistrovství v DrD','mistrovstvi-v-drd',0,  7, 0),
  (37, 'RPG',              'rpg',              0,  8, 0),
  (38, 'LARP',             'larp',             0,  9, 0),
  (39, 'Deskové hry',      'deskovky',         0, 10, 0),
  (40, 'Přednášky',        'prednasky',        0, 11, 0),
  (43, 'Dílny',            'dilny',            0, 12, 0),
  (44, 'Kontakt',          'kontakt',          0, 13, 0);

-- --- children of GameCon (33) ----------------------------------------------
-- 47 = o-gameconu is the hardcoded landing page.
INSERT INTO menu_seznam (id_polozky, nazev_polozky, nazev_polozky_zkr, nadrazeny_prvek, poradi_polozky, skryta) VALUES
  (47, 'O GameConu', 'o-gameconu', 33, 1, 0);

-- --- children of Novinky (30) ----------------------------------------------
INSERT INTO menu_seznam (id_polozky, nazev_polozky, nazev_polozky_zkr, nadrazeny_prvek, poradi_polozky, skryta) VALUES
  (48, 'Novinky',          'novinky',          30, 1, 0),
  (49, 'Bulletin',         'bulletin',         30, 2, 0),
  (50, 'Archiv bulletinů', 'archiv-bulletinu', 30, 3, 0);

-- --- children of Galerie (41) ----------------------------------------------
INSERT INTO menu_seznam (id_polozky, nazev_polozky, nazev_polozky_zkr, nadrazeny_prvek, poradi_polozky, skryta) VALUES
  (51, 'Fotografie',         'fotografie',         41, 1, 0),
  (52, 'Grafické materiály', 'graficke-materialy', 41, 2, 0),
  (53, 'Placka',            'placka',             41, 3, 0);

-- --- children of Mistrovství v DrD (42) ------------------------------------
INSERT INTO menu_seznam (id_polozky, nazev_polozky, nazev_polozky_zkr, nadrazeny_prvek, poradi_polozky, skryta) VALUES
  (54, 'O Mistrovství v DrD',  'o-mistrovstvi',      42, 1, 0),
  (55, 'Letošní dobrodružství','letosni-dobrodruzstvi',42,2,0),
  (56, 'Loňská dobrodružství', 'lonska-dobrodruzstvi',42, 3, 0),
  (57, 'Pánové Jeskyně',       'panove-jeskyne',     42, 4, 0),
  (58, 'Síň slávy',            'sin-slavy',          42, 5, 0);

-- ---------------------------------------------------------------------------
-- stranky_seznam: url -> id. Only leaves with real content get a usable page;
-- the rest map their slug so the URL resolves (empty body renders blank main).
-- ---------------------------------------------------------------------------
INSERT INTO stranky_seznam (id_stranky, url_stranky) VALUES
  (47, 'gamecon/o-gameconu'),
  (34, 'program'),
  (35, 'prihlaska'),
  (44, 'kontakt');

-- ---------------------------------------------------------------------------
-- stranky_meta (title/keywords/description).
-- ---------------------------------------------------------------------------
INSERT INTO stranky_meta (id_stranky, title_stranky, keywords_stranky, description_stranky) VALUES
  (47, 'O GameConu — GameCon 2011', 'gamecon, rpg, larp, deskové hry, dračí doupě', 'GameCon je nejstarší český herní con. Archiv stránek GameConu 2011.'),
  (34, 'Program — GameCon 2011',    'gamecon, program', 'Program GameConu 2011.'),
  (35, 'Přihláška — GameCon 2011',  'gamecon, přihláška', 'Přihláška na GameCon 2011.'),
  (44, 'Kontakt — GameCon 2011',    'gamecon, kontakt', 'Kontakt na pořadatele GameConu 2011.');

-- ---------------------------------------------------------------------------
-- stranky_obsah: page bodies. The "O GameConu" copy is the real 2011 text
-- (from texty na stranky.txt). Bodies NOT starting with '#' are echoed as HTML.
-- ---------------------------------------------------------------------------
INSERT INTO stranky_obsah (id_stranky, obsah_stranky) VALUES
(47, '<h1>O GameConu</h1>

<h2>GameCon</h2>
<p>
  GameCon je nejstarší český herní con. Poprvé byl uspořádán v roce 1995 jako reakce na stále rostoucí popularitu her na hrdiny (RPG) a stolních her s fantasy a sci-fi tématikou.<br />
  V roce 2008 se GameCon konal 10.–13. července v Pardubicích, podruhé pod křídly mezinárodního festivalu Czech Open.<br />
  Mediálním partnerem GameConu je časopis Pevnost.<br />
</p>

<h2>Zaměření akce</h2>
<p>
  GameCon není klasickým conem, jak je zvykem v ČR, ale je zaměřen výhradně na hry a hráče. Z původní akce, soustředěné především na organizaci Mistrovství České republiky v Dračím doupěti, se v průběhu posledních let stal akcí, kde se setkávají hráči her na hrdiny, hráči deskových her i příznivci LARPů. A přesně pro tyto účastníky je GameCon připravován a pořádán.
  Pokud patříte mezi příznivce výše zmíněných aktivit, pak je pro vás na GameConu nachystán bohatý program, sestávající z turnajů v deskových hrách, organizovaného hraní her na životní příběhy, tematických přednášek, praktických dílen a samozřejmě Mistrovství ČR v Dračím doupěti.
  Krom bohatého programu však budete mít hlavně možnost poznat ostatní hráče, seznámit se s novými lidmi a třeba i nalézt nové přátele.
</p>

<h2>Obsah stránek</h2>
<p>
  Na těchto stránkách naleznete všechny potřebné informace o samotné akci, ubytování, přihlášení i programu, který je pro vás připraven. Pokud by vám na stránkách cokoliv chybělo, napište nám, prosím, přes kontaktní formulář.
</p>'),
(34, '<h1>Program</h1><p>Stránka s obecným programem GameConu 2011. Po přihlášení je možné vytvořit vlastní program.</p>'),
(35, '<h1>Přihláška</h1><p>Pro přihlášení na GameCon 2011 se nejdříve přihlaste (případně zaregistrujte).</p>'),
(44, '<h1>Kontakt</h1><p>Kontaktujte pořadatele GameConu přes kontaktní formulář.</p>');

-- ---------------------------------------------------------------------------
-- Fulltext mirror of the content pages (so the search box returns hits).
-- ---------------------------------------------------------------------------
INSERT INTO stranky_fulltext (id_stranky, nadpis, obsah) VALUES
  (47, 'O GameConu', 'GameCon je nejstarší český herní con. RPG, LARP, deskové hry, Dračí doupě, Mistrovství ČR.'),
  (34, 'Program',    'Program GameConu 2011, turnaje, přednášky, dílny.'),
  (35, 'Přihláška',  'Přihláška na GameCon 2011, registrace.'),
  (44, 'Kontakt',    'Kontakt na pořadatele GameConu.');

-- ---------------------------------------------------------------------------
-- Forum sections (empty threads/posts — those are lost). jmeno_sekce_mini is
-- the URL slug; poradi the order. Lets /forum render a section list instead of
-- crashing on an empty result.
-- ---------------------------------------------------------------------------
INSERT INTO forum_sekce (id_sekce, jmeno_sekce, jmeno_sekce_mini, popis_sekce, poradi) VALUES
  (1, 'Obecné',      'obecne',   'Obecná diskuse o GameConu.', 1),
  (2, 'RPG',         'rpg',      'Hry na hrdiny.',             2),
  (3, 'LARP',        'larp',     'Live action role-play.',     3),
  (4, 'Deskové hry', 'deskovky', 'Deskové a stolní hry.',      4);
