<?php

$vsechnyNovinky=isset($vsechnyNovinky)?$vsechnyNovinky:null;

$xtpl=new XTemplate($ROOT_DIR.'/templates/novinky.xtpl');
// today's hate: novinka je uložena v databázi jako html kondenzát celého bloku
// @fixme
$o=dbQuery('SELECT obsah FROM novinky_obsah 
  WHERE stav="Y" 
  ORDER BY publikovano DESC '.
  ($vsechnyNovinky?'':'LIMIT 10')); //pro inkludování z novinky-archiv
while($r=mysql_fetch_assoc($o))
  $xtpl->insert_loop('novinky.novinka',$r);
$xtpl->parse('novinky');
$xtpl->out('novinky');

?>
