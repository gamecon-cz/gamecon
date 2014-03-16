<?php

/** 
 * nazev: Chyby
 * pravo: 105
 */

if(post('vyresit')) {
  dbQueryS('UPDATE chyby SET vyresena = NOW() WHERE zprava = $1', array(post('vyresit')));
  back();
}

$o = dbQuery('
  SELECT *, count(1) as vyskytu, count(distinct uzivatel) as uzivatelu, MAX(vznikla) as posledni
  FROM chyby
  WHERE vyresena IS NULL
  GROUP BY zprava
  ORDER BY MAX(vznikla) DESC
');

$t = new XTemplate('chyby.xtpl');

while($r = mysql_fetch_assoc($o)) {
  // počet uživatelů česky
  if($r['uzivatelu'] == 1) $r['uzivatelu'] .= ' uživatel';
  elseif($r['uzivatelu'] && $r['uzivatelu'] < 5) $r['uzivatelu'] .= ' uživatelé';
  else $r['uzivatelu'] .= ' uživatelů';
  // čas
  $r['posledni'] = (new DateTimeCz($r['posledni']))->relativni();
  // zvýraznění url
  $r['soubor'] = strtr($r['soubor'], '\\', '/');
  $r['soubor'] = strrafter($r['soubor'], '/');
  $r['zdroj'] = $r['zdroj'] ? '&emsp;«&emsp;<a href="'.$r['zdroj'].'">'.$r['zdroj'].'</a>' : '';
  // výstup
  $t->assign($r);
  $t->parse('chyby.chyba');
}

$t->parse('chyby');
$t->out('chyby');
