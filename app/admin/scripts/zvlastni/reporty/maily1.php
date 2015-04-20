<?php

require_once('sdilene-hlavicky.hhp');

echo '<h1>Nepřihlášení na letošní GC</h1>';

$o=dbQuery('
  SELECT email1_uzivatele
  FROM 
  (
    SELECT email1_uzivatele
    FROM uzivatele_hodnoty
    WHERE email1_uzivatele LIKE "%@%"
    AND souhlas_maily=1
  ) as maily
  WHERE email1_uzivatele NOT IN 
  ( 
    SELECT email1_uzivatele
    FROM r_prava_zidle
    JOIN r_uzivatele_zidle USING(id_zidle)
    JOIN uzivatele_hodnoty USING(id_uzivatele)
    WHERE id_prava='.ID_PRAVO_PRIHLASEN.'
  )
  ORDER BY email1_uzivatele  
  ');
$i=1;
while($r=mysql_fetch_assoc($o))
{
  echo $r['email1_uzivatele'].'; ';
  if(!($i%MAX_MAILU)) echo '<br /><br />';
  $i++;
}
  

?>
