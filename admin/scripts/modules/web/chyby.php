<?php

/**
 * nazev: Chyby
 * pravo: 105
 */

$db = new EPDO('sqlite:'.SPEC.'/chyby.sqlite');

if(post('vyresit')) {
  $db->query('DELETE FROM chyby WHERE rowid IN('.dbQa(explode(',', post('vyresit'))).')');
  back();
}

$o = $db->query('
  SELECT
    *,
    COUNT(1) as vyskytu,
    COUNT(DISTINCT uzivatel) as uzivatelu,
    MAX(vznikla) as posledni,
    GROUP_CONCAT(rowid) as ids,
    GROUP_CONCAT(uzivatel, "<br>") as uzivatele
  FROM chyby
  GROUP BY zprava, soubor, radek, url
  ORDER BY posledni DESC
');

$t = new XTemplate('chyby.xtpl');

$o = $o->fetchAll(PDO::FETCH_ASSOC); // aby se spojení uzavřelo a necyklily se nové výjimky

foreach($o as $r) {
  //(new Tracy\BlueScreen)->render(unserialize(base64_decode($r['vyjimka'])));
  // počet uživatelů česky
  if($r['uzivatelu'] == 1) $r['uzivatelu'] .= ' uživatel';
  elseif($r['uzivatelu'] && $r['uzivatelu'] < 5) $r['uzivatelu'] .= ' uživatelé';
  else $r['uzivatelu'] .= ' uživatelů';
  // čas
  $r['posledni'] = (new DateTimeCz('@'.$r['posledni']))->relativni();
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
