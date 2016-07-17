<?php

require_once('sdilene-hlavicky.hhp');

echo '<h1>Aktuální vypravěči</h1>';

$o=dbQuery('
  SELECT id_uzivatele, email1_uzivatele
  FROM uzivatele_hodnoty u
  LEFT JOIN r_uzivatele_zidle z USING(id_uzivatele)
  WHERE z.id_zidle='.Z_ORG_AKCI.'
  AND email1_uzivatele LIKE "%@%"');
$i=1;
while($r=mysqli_fetch_assoc($o))
{
  echo $r['email1_uzivatele'].'; ';
  if(!($i%MAX_MAILU)) echo '<br /><br />';
  $i++;
}
  

?>
