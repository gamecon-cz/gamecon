<?php

$VLASTNI_VYSTUP=true; //předáváno volajícímu scriptu

$to = @$_GET['to'];
$from = @$_SERVER['HTTP_REFERER'];
//var_dump($_SERVER, $from);
dbInsert('stazeni', array(
  'link' => $to, 
  'ip' => ip2long($_SERVER['REMOTE_ADDR']), 
  'id_uzivatele' => ($u?$u->id():null),
  'zdroj' => $from
));
header('Location: '.$to);

?>