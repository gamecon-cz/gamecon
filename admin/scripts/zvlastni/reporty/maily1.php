<?php

require_once('sdilene-hlavicky.hhp');

echo '<h1>Nepřihlášení na letošní GC</h1>';

$o=dbQuery('
  SELECT id_uzivatele, email1_uzivatele, MAX(rok) rok
  FROM uzivatele_hodnoty
  LEFT JOIN prihlaska_uzivatele USING(id_uzivatele)
  GROUP BY id_uzivatele
  HAVING (rok!='.ROK.' OR ISNULL(rok))
  AND email1_uzivatele LIKE "%@%"');
$i=1;
while($r=mysql_fetch_assoc($o))
{
  echo $r['email1_uzivatele'].'; ';
  if(!($i%MAX_MAILU)) echo '<br /><br />';
  $i++;
}
  

?>
