<?php

/**
 * Skript který je hostingem automaticky spouštěn jednou za hodinu. Standardní
 * limit vykonání je 90 sekund jako jinde na webu. 
 */

require_once('../sdilene/vse.hhp');

$key='15v21ptc';
if(@$_GET['key']!=$key)
  die('špatný klíč');

error_reporting(E_ALL ^ E_STRICT);
ini_set('html_errors',0); // chyby zobrazovat způsobem do logu

echo '<pre>'; // je do html
ob_start();

/// Výstup do logu
function logs($s)
{ echo date('Y-m-d H:i:s ').$s; }

logs("Začátek provádění cron scriptu…\n");

// zpracování dat z FIO
logs("Zpracování dat z Fio: ");
$token='DkxQEFrYDX9qZwvIIcRufP87rYwuZfNJlKUkvQZiYaO6K6xg39GdGPpU40E72cNC';
if(extension_loaded('openssl'))
{
  $od=new DateTime();
  $od->sub(new DateInterval('P300D'));
  $od=$od->format('Y-m-d');
  $do=new DateTime();
  $do=$do->format('Y-m-d');
  
  // resetovat poslední zpracovanou platbu (odkomentovat v případě potřeby)
  //file_get_contents("https://www.fio.cz/ib_api/rest/set-last-date/$token/2012-07-16/"); die('resetováno, ukončeno.');
  
  $url="https://www.fio.cz/ib_api/rest/last/$token/transactions.json";
  $platby=json_decode(file_get_contents($url))->accountStatement->transactionList;
  $platby=$platby?$platby->transaction:array();
  if($platby)
  { // na účtu se objevily nové platby od poslední kontroly
    echo count($platby)." nových plateb ";
    $maxId=dbOneLine('SELECT MAX(id_uzivatele) as max FROM uzivatele_hodnoty');
    $maxId=$maxId['max'];
    $q='';
    for($i=count($platby)-1;$i>=0;$i--)
    {
      $r=array();
      $r['jmeno']=(@$platby[$i]->column7->value);
      $r['zprava']=(@$platby[$i]->column16->value).'';
      $r['vs']=(int)(@$platby[$i]->column5->value);
      $r['castka']=($platby[$i]->column1->value)*1.0;
      $r['datum']=(new DateTime($platby[$i]->column0->value));
      $r['datum']=$r['datum']->format('Y-m-d'); //datum nemá časovou složku
      if($r['castka']>0 && $r['vs']>0 && $r['vs']<=$maxId ) //jen příjmy + jen od uživatelů
      {
        //var_dump($r);
        $poznamka=$r['zprava'];
        $poznamka=strlen($poznamka)>4?"'".addslashes($poznamka)."'":'null';
        $q.="\n($r[vs],$r[castka],$poznamka,".ROK.",1),";
      }
    }
    if($q) // relevantní nové platby
      dbQuery("\nINSERT INTO platby(id_uzivatele,castka,poznamka,rok,provedl) VALUES ".substr($q,0,-1));
    else
      echo(", žádné zaúčtovatelné ");
  }
  else
    echo "Žádné nové platby ";
  echo "[OK]\n";
}
else
  logs("Není načteno rozšíření OpenSSL, platby nepojedou. [FAIL]\n");

// odemčení zamčených aktivit
if(date('G')==4) {
  logs("Odemykání aktivit…\n");
  $i = Aktivita::odemciHromadne();
  logs("Odemčeno $i\n");
}


logs("cron dokončen.\n");
$vystup = ob_get_clean();
$zapsano = file_put_contents('./files/logs/cron', $vystup, FILE_APPEND);
if($zapsano === false) {
  echo "Zápis selhal. Výsledek CRONu je následující:\n\n";
  echo $zapsano;
}
