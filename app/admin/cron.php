<?php

/**
 * Skript který je hostingem automaticky spouštěn jednou za hodinu. Standardní
 * limit vykonání je 90 sekund jako jinde na webu. 
 */

require '../zavadec.php';

$key='15v21ptc';
if(VETEV != VYVOJOVA && @$_GET['key'] != $key)
  die('špatný klíč');

error_reporting(E_ALL ^ E_STRICT);
ini_set('html_errors',0); // chyby zobrazovat způsobem do logu

echo '<pre>'; // je do html
ob_start();

/// Výstup do logu
function logs($s)
{ echo date('Y-m-d H:i:s '), $s, "\n"; }

logs("začátek provádění cron scriptu");


// zpracování dat z FIO
logs("zpracování dat z Fio");

$platby = Platby::nactiNove();
foreach($platby as $p) logs('platba ' . $p->id() . ' (' . $p->castka() . 'Kč, VS: ' . $p->vs() . ($p->zprava() ? ', zpráva: ' . $p->zprava() : '') . ')');
if(!$platby) logs('žádné zaúčtovatelné platby');


// odemčení zamčených aktivit
logs("odemykání aktivit");
$i = Aktivita::odemciHromadne();
logs("odemčeno $i");


logs("cron dokončen\n");

$vystup = ob_get_contents();
if(!is_dir(SPEC.'/logs')) mkdir(SPEC.'/logs');
$zapsano = file_put_contents(SPEC.'/logs/cron-'.date('Y-m'), $vystup, FILE_APPEND);
if($zapsano === false) {
  echo "Zápis selhal. Výsledek CRONu je následující:\n\n";
  echo $zapsano;
}
