<?php

require_once('sdilene-hlavicky.hhp');

echo '<h1>Přihlášení na letošní GC</h1>';

$o=dbQuery('
  SELECT id_uzivatele, email1_uzivatele
  FROM uzivatele_hodnoty
  JOIN prihlaska_uzivatele USING(id_uzivatele)
  WHERE rok='.ROK.'
  AND email1_uzivatele LIKE "%@%"');
$i=1;
while($r=mysql_fetch_assoc($o))
{
  echo $r['email1_uzivatele'].'; ';
  if(!($i%MAX_MAILU)) echo '<br /><br />';
  $i++;
}
  

?>
