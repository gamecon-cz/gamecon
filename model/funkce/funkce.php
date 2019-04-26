<?php

$GLOBALS['SKRIPT_ZACATEK'] = microtime(true); // profiling

/**
 * Vrátí míru diverzifikace aktivit v poli udávajícím počty aktivit od jedno-
 * tlivých typů. Délka pole ovlivňuje výsledek (je potřeba aby obsahovalo i 0)
 */
function aktivityDiverzifikace($poleTypu)
{
  $typu=count($poleTypu);
  $pocet=array_sum($poleTypu);
  if($pocet == 0) return 0.0;
  $pocty=$poleTypu;
  rsort($pocty,SORT_NUMERIC);
  $max=($pocet-$pocty[0])/($pocet*($typu-1));
  $nPocty=[];
  for($i=1;$i<$typu;$i++)
  { //první počet přeskočit
    if($pocty[$i]/$pocet>$max)
      $nPocty[]=$max;
    else
      $nPocty[]=$pocty[$i]/$pocet;
  }
  return array_sum($nPocty)*$typu/($typu-1); //výsledná míra diverzifikace 0.0 - 1.0
}


/**
 * 1 okno
 * 2 okna
 * 5 oken
 * @todo 22 okna (volitelně)
 * @todo záporná čísla
 * @todo nepovinné přepisování ('%d' => 'přihlásil se 1 uživatel', případně slovy apod)
 */
function cislo($i, $jeden, $dva, $pet) {
  if($i == 1) return $i.$jeden;
  if(1 < $i && $i < 5) return $i.$dva;
  else return $i.$pet;
}


/** Vrací datum ve stylu "pátek 14:00-18:00" na základě řádku db */
function datum2($dbRadek)
{
  if($dbRadek['zacatek'])
    return (new DateTimeCz($dbRadek['zacatek']))->format('l G:i').'–'.(new DateTimeCz($dbRadek['konec']))->format('G:i');
  else
    return '';
}


/** Vrací datum ve stylu 1. července
 *  akceptuje vše, co žere strtotime */
function datum3($datum)
{
  $mesic=['ledna', 'února', 'března', 'dubna', 'května', 'června',
    'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];
  return date('j. ',strtotime($datum)).
    $mesic[date('n',strtotime($datum))-1];
}


/** Vrátí markdown textu daného hashe (cacheované, text musí být v DB) */
function dbMarkdown($hash) {
  if($hash == 0) return '';
  $out = kvs('markdown', $hash);
  if(!$out) {
    $text = dbOneCol('SELECT text FROM texty WHERE id = '.(int)$hash);
    if(!$text) throw new Exception('Text s daným ID se nenachází v databázi');
    $out = markdown($text);
  }
  return $out;
}


/**
 * Vrátí / nastaví text daného hashe v DB.
 * Možné použití (místo 0 funguje všude false ekvivalent):
 *  dbText(123)         - vrátí text s ID 123
 *  dbText(0)           - vrátí 0
 *  dbText(0, 'ahoj')   - vloží text a vrátí jeho ID
 *  dbText(123, 'ahoj') - odstraní text 123 a vloží místo něj nový, vrátí nové ID
 *  dbText(123, '')     - odstraní text 123 a vrátí 0
 *  dbText(0, '')       - vrátí 0
 *  TODO vše implementovat a otestovat
 *  TODO co s duplicitami
 */
function dbText($hash) {
  if(func_num_args() == 1) {
    return dbOneCol('SELECT text FROM texty WHERE id = '.(int)$hash);
  } elseif(func_num_args() == 2 and !func_get_arg(1)) {
    dbQuery('DELETE FROM texty WHERE id = '.(int)$hash);
    return 0;
  } else {
    $text = func_get_arg(1);
    $nhash = scrc32($text);
    $nrow = ['text' => $text, 'id' => $nhash];
    if($hash) dbUpdate('texty', $nrow, ['id' => $hash]);
    else dbInsert('texty', $nrow);
    return $nhash;
  }
}


/**
 * Uloží daný text do databáze a vrátí id (hash) kterým se na něj odkázat
 */
function dbTextHash($text) {
  $hash = scrc32($text);
  try {
    dbInsert('texty', ['id'=>$hash, 'text'=>$text]);
  } catch(DbException $e) {}
  return $hash;
}


/**
 * Vymaže text s daným hashem z DB pokud je to možné
 */
function dbTextClean($hash) {
  try {
    dbQuery('DELETE FROM texty WHERE id = '.(int)$hash);
  } catch(DbException $e) {}
}


/**
 * Vrací hlášku s daným názvem. Libovolný počet argumentů. Pokud je druhým
 * argumentem uživatel, podporuje symbol {a} jako proměnlivou koncovku a. Další
 * argumenty jsou dostupné jako %1, %2 atd... Čísluje se od jedné.
 *
 * Pokud není uživatel uveden, bere se druhý argument jako %1, třetí jako %2
 * atd...
 *
 * @todo fixnout málo zadaných argumentů
 * @return string hláška s případnými substitucemi
 */
function hlaska($nazev,$u=null) {
  global $HLASKY,$HLASKY_SUBST;

  if(func_num_args()==1)
    return $HLASKY[$nazev];
  elseif($u instanceof Uzivatel)
  {
    $koncA=$u->pohlavi()=='f'?'a':'';
    return strtr($HLASKY_SUBST[$nazev],[
      "\n"=>'<br />',
      '{a}'=>$koncA,
      '%1' =>func_num_args()>2?func_get_arg(2):'',
      '%2' =>func_num_args()>3?func_get_arg(3):'',
      '%3' =>func_num_args()>4?func_get_arg(4):'',
      '%4' =>func_num_args()>5?func_get_arg(5):''
    ]);
  }
  elseif(func_num_args()>1)
  {
    return strtr($HLASKY_SUBST[$nazev],[
      "\n"=>'<br />',
      '%1' =>func_num_args()>1?func_get_arg(1):'',
      '%2' =>func_num_args()>2?func_get_arg(2):'',
      '%3' =>func_num_args()>3?func_get_arg(3):'',
      '%4' =>func_num_args()>4?func_get_arg(4):''
    ]);
  }
  else
  {
    throw new Exception('missing mandatory argument');
  }
}


function hlaskaMail($nazev,$u=null) {
  $out=hlaska($nazev,
    func_num_args()>1?func_get_arg(1):'',
    func_num_args()>2?func_get_arg(2):'',
    func_num_args()>3?func_get_arg(3):'',
    func_num_args()>4?func_get_arg(4):'',
    func_num_args()>5?func_get_arg(5):'');
  return '<html><body>'.$out.'</body></html>';
}


/**
 * Přesměruje na adresu s https, pokud jde požadavek z adresy s http,
 * a následně ukončí skript.
 */
function httpsOnly() {
  if(empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    //header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $redirect);
    exit();
  }
}


/**
 * Předá chybu volajícímu skriptu, vyvolá reload
 */
function chyba($zprava) {
  Chyba::nastav($zprava, Chyba::CHYBA);
  back();
}


/** Načte / uloží hodnotu do key-value storage s daným názvem */
function kvs($nazev, $index, $hodnota = null) {
  if(!isset($GLOBALS['CACHEDB'][$nazev])) {
    $db = new SQLite3(SPEC.'/'.$nazev.'.sqlite');
    $GLOBALS['CACHEDB'][$nazev] = $db;
    $db->exec("create table if not exists kvs (k integer primary key, v text)");
  }
  $db = $GLOBALS['CACHEDB'][$nazev];
  if($hodnota === null) {
    // načítání
    $o = $db->query('select v from kvs where k = '.$index)->fetchArray(SQLITE3_NUM);
    if($o === false) return null;
    else return $o[0];
  } else {
    $db->exec('insert into kvs values('.$index.',\''.SQLite3::escapeString($hodnota).'\')');
  }
}


/**
 * Převede text na odpovídající html pomocí markdownu
 * @see Originální implementace markdownu je rychlejší jak Parsedown, ale díky
 *  cacheování je to jedno
 */
function markdown($text) {
  $hash = scrc32($text);
  $out = kvs('markdown', $hash);
  if($out === null) {
    kvs('markdown', $hash, markdownNoCache($text));
    $out = kvs('markdown', $hash);
  }
  return $out;
}


/** Převede text markdown na html (přímo on the fly) */
function markdownNoCache($text) {
  if(!$text) return '';
  $text = \Michelf\MarkdownExtra::defaultTransform($text);
  $text = Smartyp::defaultTransform($text);
  return $text;
}


/** Multibyte (utf-8) první písmeno velké */
function mb_ucfirst($string, $encoding=null)
{
  if(!$encoding) $encoding = mb_internal_encoding();
  $firstChar = mb_substr($string, 0, 1, $encoding);
  $then = mb_substr($string, 1, mb_strlen($string), $encoding);
  return mb_strtoupper($firstChar, $encoding) . $then;
}


/**
 * Vrací true, pokud je aktuální čas mezi $od a $do. Formáty jsou stejné jaké
 * akceptují php funce (např. strtotime)
 */
function mezi($od, $do) {
  return strtotime($od)<=time() && time()<=strtotime($do);
}


/**
 * Předá oznámení volajícímu skritpu, vyvolá reload
 * @param back bool má se reloadovat?
 */
function oznameni($zprava, $back = true) {
  Chyba::nastav($zprava, Chyba::OZNAMENI);
  if($back) back();
}


/**
 * Kompiluje a minifikuje soubory předané v argumentech a vrací url s časovou
 * značkou (jako url proměnnou)
 * V složce soubory/perfectcache nutno (např. htaccessem) povolit cacheování
 * navždy
 * Poslední soubor slouží jako referenční k určení cesty, kam cache uložit
 * @todo nějaký hash počtu / názvu souborů? (když se přidá nový soubor se starým
 *  timestampem, nic se nestane)
 */
function perfectcache($args) {
  $args = func_get_args();
  $lastf = end($args);
  $typ = substr($lastf, -3) == '.js' ? 'js' : 'css';
  $last = 0;
  foreach($args as $a) {
    if(!$a) continue;
    $m = filemtime($a);
    if($last < $m) $last = $m;
  }
  $mind = CACHE . '/' . $typ;
  $minf = $mind . '/' . md5(implode('', $args)) . '.' . $typ;
  $minu = URL_CACHE . '/' . $typ . '/' . md5(implode('', $args)) . '.' . $typ;
  $m = @filemtime($minf);
  // případná rekompilace
  if($m < $last) {
    pripravCache($mind);
    if(is_file($minf)) unlink($minf);
    if($typ == 'js') {
      foreach($args as $a) if($a) file_put_contents($minf, file_get_contents($a), FILE_APPEND);
    } else {
      $parser = new Less_Parser(['compress' => true]);
      foreach($args as $a) if($a) {
        if(substr($a, -4) != '.ttf') $parser->parseFile($a, URL_WEBU.'/soubory/styl/');
        else $parser->ModifyVars([ perfectcacheFontNazev($a) => 'url("'.perfectcacheFont($a).'")' ]); // prozatím u fontu stačí věřit, že modifikace odpovídá modifikaci stylu
      }
      file_put_contents($minf, $parser->getCss());
    }
  }
  return $minu.'?v='.$last;
}

function perfectcacheFont($font) {
  // font musí pocházet ze stejné url - nelze použít cache
  return URL_WEBU.'/'.$font.'?v='.filemtime($font);
}

function perfectcacheFontNazev($font) {
  return 'font'.preg_replace('@.*/([^/]+)\.ttf$@', '$1', $font);
}


function po($cas) {
  return strtotime($cas) < time();
}


function pred($cas) {
  return time() < strtotime($cas);
}


/** Tisk informace profileru. */
function profilInfo()
{
  if(!PROFILOVACI_LISTA)
    return false; //v ostré verzi se neprofiluje
  $schema = 'data:image/png;base64,';
  $iDb = $schema.base64_encode(file_get_contents(__DIR__.'/db.png'));
  $iHodiny = $schema.base64_encode(file_get_contents(__DIR__.'/hodiny.png'));
  //$iconRoot = URL_ADMIN.'/files/design/';
  $delka = microtime(true) - $GLOBALS['SKRIPT_ZACATEK'];
  // počet sekund, kdy už je skript pomalý (čas zčervená)
  $barva = $delka > 0.2 ? 'color:#f80;' : '';
  // výstup
  echo '
    <div class="profilInfo" style="
      background-color: rgba(0,192,255,0.80);
      color: #fff;
      bottom: 0;
      right: 0;
      position: fixed;
      padding: 2px 7px;
      cursor: default;
      z-index: 9999;
      border-top-left-radius: 4px;
      font: 13px Tahoma, sans-serif;
    ">
    <style>
      .profilInfo img { vertical-align: bottom; }
      @media (max-width: 480px) { .profilInfo { display: none; } }
    </style>
    <img src="'.$iHodiny.'" alt="délka skriptu včetně DB">
    <span style="'.$barva.'">'.round($delka*1000).'&thinsp;ms</span>
    &ensp; 
    <img src="'.$iDb.'" alt="délka odbavení DB/počet dotazů">
    '.round(dbExecTime()*1000).'&thinsp;ms ('.dbNumQ().' dotazů)
    </div>';
}


/**
 * Vytvoří zapisovatelnou složku, pokud taková už neexistuje
 */
function pripravCache($slozka) {
  if(is_writable($slozka)) return;
  if(is_dir($slozka)) throw new Exception("Do existující cache složky '$slozka' není možné zapisovat");
  if(!mkdir($slozka, 0777, true)) throw new Exception("Složku '$slozka' se nepodařilo vytvořit");
  chmod($slozka, CACHE_SLOZKY_PRAVA);
}


/** Znaménkové crc32 chovající se stejně na 32bit i 64bit systémech */
function scrc32($data) {
  $crc = crc32($data);
  if($crc & 0x80000000){
    $crc ^= 0xffffffff;
    $crc += 1;
    $crc = -$crc;
  }
  return $crc;
}

function potrebujePotvrzeni(DateTimeImmutable $datumNarozeni): bool {
    // cilene bez hodin, minut a sekund
    return vekNaZacatkuLetosnihoGameconu($datumNarozeni) < 15;
}

function vekNaZacatkuLetosnihoGameconu(DateTimeImmutable $datumNarozeni): int {
    // cilene bez hodin, minut a sekund
    return vek($datumNarozeni->setTime(0, 0, 0), zacatekLetosnihoGameconu()->setTime(0, 0, 0));
}

function vek(DateTimeInterface $datumNarozeni, ?DateTimeInterface $kDatu): int {
    $kDatu = $kDatu ?? new DateTimeImmutable(date('Y-m-d 00:00:00'));
    return $kDatu->diff($datumNarozeni)->y;
}

function zacatekLetosnihoGameconu(): DateTimeImmutable {
    return new DateTimeImmutable(GC_BEZI_OD);
}
