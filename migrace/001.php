<?php

/////////////////////////////
// Pomocné fce pro migraci //
/////////////////////////////
require_once __DIR__ . '/pomocne/HTML_To_Markdown.php';
$markdown = new HTML_To_Markdown();
$markdown->set_option('italic_style', '_');
$markdown->set_option('bold_style', '__');

////////////////////////////////
// Odstranění absolutních url //
////////////////////////////////
$this->q("UPDATE stranky SET obsah = REPLACE(obsah, 'href=\"/', 'href=\"')");
$this->q("UPDATE stranky SET obsah = REPLACE(obsah, 'src=\"/',  'src=\"' )");
$this->q("UPDATE stranky SET obsah = REPLACE(obsah, '](/',      ']('     )");

////////////////////////////////////////
// Příprava zobecnění textů a novinek //
////////////////////////////////////////
$this->q('DROP TABLE IF EXISTS novinky'); // kvůli klíčům dříve

$this->q('DROP TABLE IF EXISTS texty');
$this->q('
  CREATE TABLE texty (
    id INT NOT NULL PRIMARY KEY COMMENT "hash",
    text MEDIUMTEXT NOT NULL
  ) ENGINE=InnoDB COLLATE "utf8_czech_ci";
');
$this->q("INSERT INTO `texty` (`id`, `text`) VALUES ('0', '');");

// TODO nadpis/název, url
$this->q('
  CREATE TABLE `novinky` (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    typ TINYINT(1) NOT NULL DEFAULT 1 COMMENT "1-novinka 2-blog",
    vydat DATETIME NULL,
    url VARCHAR(100) NOT NULL UNIQUE,
    nazev VARCHAR(200) NOT NULL,
    autor VARCHAR(100) NULL,
    text INT NOT NULL
  ) ENGINE=InnoDB COLLATE "utf8_czech_ci";
');
$this->q('
  ALTER TABLE `novinky`
  ADD FOREIGN KEY (`text`) REFERENCES `texty` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
');

///////////////////
// Migrace blogu //
///////////////////
$blog = $this->q('SELECT obsah FROM stranky WHERE url_stranky = "blog"')->fetch_row()[0] ?? null;
if ($blog) {
    preg_match_all('@<!-+ NADPIS\+POPISEK -+>(.+?)<a name="([^"]+)">.+?<h2[^>]*>([^<]+)</h2>.+?<p class="podpis">([^,]+), ?([^<]+)</p>(</h4>)?(.+?)<!-+ LIKE BUTTON -+>@s', $blog, $m, PREG_SET_ORDER);
    foreach ($m as $c) {
        unset($c[0]);
        $c[7] = trim($c[7]);
        $c[5] = preg_replace('@(\d+)\.(\d+)\.(\d+)@', '$3-$2-$1', $c[5]);
        //var_dump($c);
        // filtrace obsahu
        $obsah = $c[7];
        $obsah = strtr($obsah, [
            'files/obsah/blog/' => 'soubory/obsah/blog/',
        ]);
        $obsah = preg_replace('@(<a href[^>]+>)?<img src="([^"]+)"[^>]*>(</a>)?@', '<img src="$2">', $obsah);
        $obsah = trim($markdown->convert($obsah));
        $obsah = preg_replace('@<div id="[^"]+" style="display: none">(.*)</div>@s', '<!-- vice -->' . "\n" . '$1', $obsah);
        $hash = scrc32($obsah);
    }
}
$this->q('DELETE FROM stranky WHERE url_stranky = "blog"');

/////////////////////
// Migrace novinek //
/////////////////////
$o = $this->q('
  SELECT *
  FROM novinky_obsah n
  JOIN uzivatele_hodnoty u ON (u.id_uzivatele = n.autor)
  WHERE stav = "Y"');
while ($r = mysqli_fetch_assoc($o)) {
    $t = preg_split('@<h2>(.+?)</h2>\s*<h3>(.+?)</h3>@', $r['obsah'], 2, PREG_SPLIT_DELIM_CAPTURE);
    $text = trim($markdown->convert($t[3]));
    $hash = scrc32($text);
}

///////////////////////////////////
// převod textů aktivit na klíče //
///////////////////////////////////
$this->q('ALTER TABLE `akce_seznam` ENGINE=InnoDB');
$o = $this->q('SELECT * FROM akce_seznam WHERE popis IS NOT NULL AND popis != "" AND popis NOT RLIKE "^-?[0-9]+$"');
while ($r = mysqli_fetch_assoc($o)) {
    $h = sprintf('%d', scrc32($r['popis']));
}
// rozšíření ID více instancí
$this->q('
  UPDATE akce_seznam a
  LEFT JOIN akce_seznam b ON(a.patri_pod = b.patri_pod AND b.popis)
  SET a.popis = b.popis
  WHERE a.patri_pod;
');
$this->q('ALTER TABLE akce_seznam MODIFY COLUMN popis INT NOT NULL');
$this->q('ALTER TABLE `akce_seznam` ADD FOREIGN KEY (`popis`) REFERENCES `texty` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT');

//////////
// Tagy //
//////////

$this->q('CREATE TABLE tagy (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nazev VARCHAR(64) UNIQUE NOT NULL
  ) ENGINE="MyISAM" COLLATE "utf8_czech_ci";
');

$this->q('CREATE TABLE akce_tagy (
  id_akce INT NOT NULL,
  id_tagu INT NOT NULL,
  PRIMARY KEY (id_akce, id_tagu),
  KEY (id_tagu)
) ENGINE="MyISAM" COLLATE "utf8_czech_ci";');

///////////////////////////////////
// Převod textů stránek na klíče //
///////////////////////////////////
// Necháme markdown bez cacheování

///////////////////////////////
// Odstranění bordel-tabulek //
///////////////////////////////
$this->q('DROP TABLE IF EXISTS chyby');

$this->q('DROP TABLE IF EXISTS drd_druziny');
$this->q('DROP TABLE IF EXISTS drd_pj');
$this->q('DROP TABLE IF EXISTS drd_postava');
$this->q('DROP TABLE IF EXISTS drd_prihlasky');
$this->q('DROP TABLE IF EXISTS drd_uzivatele_druziny');

$this->q('DROP TABLE IF EXISTS forum_clanky');
$this->q('DROP TABLE IF EXISTS forum_cteno');
$this->q('DROP TABLE IF EXISTS forum_podsekce');
$this->q('DROP TABLE IF EXISTS forum_sekce');

$this->q('DROP TABLE IF EXISTS postavy_poznamka');
$this->q('DROP TABLE IF EXISTS postavy_schopnosti');
$this->q('DROP TABLE IF EXISTS postavy_vybaveni');
$this->q('DROP TABLE IF EXISTS postavy_zbrane_f2f');
$this->q('DROP TABLE IF EXISTS postavy_zbrane_str');

// bordel související se starým webem
$this->q('DROP TABLE IF EXISTS novinky_obsah');
$this->q('DROP TABLE IF EXISTS minihra');
$this->q('DROP TABLE IF EXISTS menu');
$this->q('DROP TABLE IF EXISTS maillist');
$this->q('DROP TABLE IF EXISTS stazeni');

// comment na tabulku stránky kvůli dbformu
$this->q("
ALTER TABLE `stranky`
CHANGE `obsah` `obsah` LONGTEXT COLLATE 'utf8_czech_ci' NOT NULL COMMENT 'markdown' AFTER `url_stranky`;
");

// možná dlouhá poznámka v adminu
$this->q("
ALTER TABLE `uzivatele_hodnoty`
CHANGE `poznamka` `poznamka` VARCHAR(4096) COLLATE 'utf8_czech_ci' NOT NULL AFTER `guru`;
");

/////////////////////////////////
// Fixnutí plateb vůči fio api //
/////////////////////////////////

$this->q("
ALTER TABLE `platby`
CHANGE `id_platby` `id` INT(11) NOT NULL COMMENT 'kvůli indexu a vícenásobným platbám' AUTO_INCREMENT FIRST,
CHANGE `id_uzivatele` `id_uzivatele` INT(11) NOT NULL AFTER `id`,
ADD `fio_id` BIGINT NULL AFTER `id_uzivatele`,
CHANGE `castka` `castka` DECIMAL(6,2) NOT NULL AFTER `fio_id`,
CHANGE `rok` `rok` SMALLINT(6) NOT NULL AFTER `castka`,
CHANGE `provedeno` `provedeno` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `rok`,
CHANGE `provedl` `provedl` INT(11) NOT NULL AFTER `provedeno`,
CHANGE `poznamka` `poznamka` TEXT COLLATE 'utf8_czech_ci' NULL AFTER `provedl`;
");

$this->q("
ALTER TABLE `platby`
ADD PRIMARY KEY `id` (`id`),
ADD INDEX `id_uzivatele_rok` (`id_uzivatele`, `rok`),
DROP INDEX `PRIMARY`,
DROP INDEX `id_platby`;
");

// přidání id k poslední napárované fio platbě
$this->q("
UPDATE `platby` SET
`fio_id` = '5596467723'
WHERE `id` = '3717';
");

////////////////////////////
// Čísla a názvy týmů atd //
////////////////////////////

$this->q(" ALTER TABLE akce_seznam ADD COLUMN zamcel_cas DATETIME NULL COMMENT 'případně kdy zamčel aktivitu' AFTER zamcel; ");
$this->q(" ALTER TABLE akce_seznam ADD COLUMN team_nazev VARCHAR(255) NULL AFTER team_max; ");
