<?php

$q=get('term');
if(!$q || strlen($q)<2)
  exit();
//todo regulární výrazy na rozlišení, kdo se hledá
$qs=addslashes($q);
$a=dbQuery('
  SELECT jmeno_uzivatele, prijmeni_uzivatele, login_uzivatele, u.id_uzivatele, mesto_uzivatele, telefon_uzivatele, z.id_zidle as pritomen
  FROM uzivatele_hodnoty u
  LEFT JOIN r_uzivatele_zidle z ON(u.id_uzivatele=z.id_uzivatele AND z.id_zidle='.Z_PRITOMEN.')
  WHERE u.id_uzivatele="'.$qs.'"
  OR login_uzivatele LIKE "%'.$qs.'%"
  OR jmeno_uzivatele LIKE "%'.$qs.'%"
  OR prijmeni_uzivatele LIKE "%'.$qs.'%"
  OR CONCAT(jmeno_uzivatele," ",prijmeni_uzivatele) LIKE "%'.$qs.'%"
  LIMIT 20');

$out=array();
while($r=mysql_fetch_assoc($a))
{
  $out[] = array(
    'label' => $r['id_uzivatele'].' – '.$r['login_uzivatele'].' – '.$r['jmeno_uzivatele'].' '.$r['prijmeni_uzivatele']  ,
    'value' => $r['id_uzivatele']
  );
}

echo json_encode($out);
