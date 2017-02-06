<?php

require_once('sdilene-hlavicky.hhp');

echo '<h1>Přihlášení na letošní GC</h1>';

$o=dbQuery('
  SELECT email1_uzivatele
  FROM r_prava_zidle
  JOIN r_uzivatele_zidle USING(id_zidle)
  JOIN uzivatele_hodnoty USING(id_uzivatele)
  WHERE id_prava='.ID_PRAVO_PRIHLASEN.'
  AND email1_uzivatele LIKE "%@%"');
$i=1;
while($r=mysqli_fetch_assoc($o))
{
  echo $r['email1_uzivatele'].'; ';
  if(!($i%MAX_MAILU)) echo '<br /><br />';
  $i++;
}
  

?>
