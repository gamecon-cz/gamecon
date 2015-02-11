<?php

/////////////////////////////
// Pomocné fce pro migraci //
/////////////////////////////
require(__DIR__.'/pomocne/HTML_To_Markdown.php');
$markdown = new HTML_To_Markdown();
$markdown->set_option('italic_style', '_');
$markdown->set_option('bold_style', '__');

function urlcz($t) {
  $t = preg_replace('@[^0-9a-z-]|(-)-+@', '$1', strtr(strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $t)), ' ', '-'));
  return $t;
}


////////////////////////////////
// Odstranění absolutních url //
////////////////////////////////
dbQueryS('UPDATE stranky SET obsah = REPLACE(obsah, $1, $2)', array('href="/', 'href="'));
dbQueryS('UPDATE stranky SET obsah = REPLACE(obsah, $1, $2)', array('src="/', 'src="'));
dbQueryS('UPDATE stranky SET obsah = REPLACE(obsah, $1, $2)', array('](/', ']('));


////////////////////////////////////////
// Příprava zobecnění textů a novinek //
////////////////////////////////////////
$this->q('DROP TABLE IF EXISTS novinky'); // kvůli klíčům dříve

$this->q('DROP TABLE IF EXISTS texty;');
$this->q('
  CREATE TABLE texty (
    id int NOT NULL PRIMARY KEY COMMENT "hash",
    text mediumtext NOT NULL
  ) ENGINE=InnoDB COLLATE "utf8_czech_ci";
');
dbQuery("INSERT INTO `texty` (`id`, `text`) VALUES ('0', '');");

// TODO nadpis/název, url
$this->q('
  CREATE TABLE `novinky` (
    id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
    typ tinyint(1) NOT NULL DEFAULT 1 COMMENT "1-novinka 2-blog",
    vydat datetime NULL,
    url varchar(100) NOT NULL UNIQUE,
    nazev varchar(200) NOT NULL,
    autor varchar(100) NULL,
    text int NOT NULL
  ) ENGINE=InnoDB COLLATE "utf8_czech_ci";
');
$this->q('
  ALTER TABLE `novinky`
  ADD FOREIGN KEY (`text`) REFERENCES `texty` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
');


///////////////////
// Migrace blogu //
///////////////////
$blog = dbOneCol('SELECT obsah FROM stranky WHERE url_stranky = "blog"');
if($blog) {
  preg_match_all('@<!-+ NADPIS\+POPISEK -+>(.+?)<a name="([^"]+)">.+?<h2[^>]*>([^<]+)</h2>.+?<p class="podpis">([^,]+), ?([^<]+)</p>(</h4>)?(.+?)<!-+ LIKE BUTTON -+>@s', $blog, $m, PREG_SET_ORDER);
  foreach($m as $c) {
    unset($c[0]);
    $c[7] = trim($c[7]);
    $c[5] = preg_replace('@(\d+)\.(\d+)\.(\d+)@', '$3-$2-$1', $c[5]);
    //var_dump($c);
    // filtrace obsahu
    $obsah = $c[7];
    $obsah = strtr($obsah, array(
      'files/obsah/blog/' => 'soubory/obsah/blog/',
    ));
    $obsah = preg_replace('@(<a href[^>]+>)?<img src="([^"]+)"[^>]*>(</a>)?@', '<img src="$2">', $obsah);
    $obsah = trim($markdown->convert($obsah));
    $obsah = preg_replace('@<div id="[^"]+" style="display: none">(.*)</div>@s', '<!-- vice -->'."\n".'$1', $obsah);
    $hash = scrc32($obsah);
    dbInsert('texty', array('id' => $hash, 'text' => $obsah));
    dbInsert('novinky', array(
      'vydat' => $c[5],
      'url'   => $c[2],
      'nazev' => $c[3],
      'autor' => $c[4],
      'text'  => $hash,
      'typ'   => 2
    ));
  }
}
dbQuery('DELETE FROM stranky WHERE url_stranky = "blog"');


/////////////////////
// Migrace novinek //
/////////////////////
$o = dbQuery('
  SELECT *
  FROM novinky_obsah n
  JOIN uzivatele_hodnoty u on (u.id_uzivatele = n.autor)
  WHERE stav = "Y"');
while($r = mysql_fetch_assoc($o)) {
  $t = preg_split('@<h2>(.+?)</h2>\s*<h3>(.+?)</h3>@', $r['obsah'], 2, PREG_SPLIT_DELIM_CAPTURE);
  $text = trim($markdown->convert($t[3]));
  $hash = scrc32($text);
  dbInsert('texty', array('id' => $hash, 'text' => $text));
  dbInsert('novinky', array(
    'vydat' => $r['publikovano'],
    'url'   => urlcz($t[2]),
    'nazev' => $t[2],
    'autor' => Uzivatel::jmenoNickZjisti($r),
    'text'  => $hash,
    'typ'   => 1,
  ));
}


///////////////////////////////////
// převod textů aktivit na klíče //
///////////////////////////////////
$this->q('ALTER TABLE `akce_seznam` ENGINE=InnoDB');
$o = dbQuery('SELECT * FROM akce_seznam WHERE popis IS NOT NULL AND popis != "" AND popis NOT RLIKE "^-?[0-9]+$"');
while($r = mysql_fetch_assoc($o)) {
  $h = sprintf('%d', scrc32($r['popis']));
  try {
    dbInsert('texty', array(
      'id'    =>  $h,
      'text'  =>  $r['popis'],
    ));
  } catch(DbException $e) {
    echo "Aktivita $r[nazev_akce] $r[rok]: ";
    echo $e->getMessage().'<br>';
  }
  dbUpdate('akce_seznam', array('popis'=>$h), array('id_akce'=>$r['id_akce']));
}
// rozšíření ID více instancí
dbQuery('
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

$this->q('DROP TABLE IF EXISTS tagy');
$this->q('CREATE TABLE tagy (
    id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nazev varchar(64) UNIQUE NOT NULL
  ) ENGINE="MyISAM" COLLATE "utf8_czech_ci";
');
$this->q('DROP TABLE IF EXISTS akce_tagy');
$this->q('CREATE TABLE akce_tagy (
  id_akce int not null,
  id_tagu int not null,
  PRIMARY KEY (id_akce, id_tagu),
  KEY (id_tagu)
) ENGINE="MyISAM" COLLATE "utf8_czech_ci";');
$o = dbQuery("select * from akce_seznam where nazev_akce like '%(%)' and typ = 4 and zacatek > '2011-01'");
while($r = mysql_fetch_assoc($o)) {
  $tag = preg_replace('@.*\((.*)\)@', '$1', $r['nazev_akce']);
  try {
    dbQueryS('INSERT INTO tagy(nazev) VALUES ($1)', array($tag));
    $tagId = mysql_insert_id();
  } catch(Exception $e) {
    $tagId = dbOneCol('SELECT id FROM tagy WHERE nazev = $1', array($tag));
  }
  dbInsertUpdate('akce_tagy', array('id_akce' => $r['id_akce'], 'id_tagu' => $tagId));
  $nazev = preg_replace('@\s?\(.*\)@', '', $r['nazev_akce']);
  dbUpdate('akce_seznam', array('nazev_akce' => $nazev), array('id_akce' => $r['id_akce']));
}


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
CHANGE `obsah` `obsah` longtext COLLATE 'utf8_czech_ci' NOT NULL COMMENT 'markdown' AFTER `url_stranky`;
");
