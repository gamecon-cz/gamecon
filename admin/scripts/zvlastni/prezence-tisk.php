<?php

$xtpl=new XTemplate('prezence-tisk.xtpl');

$ids=null;
if(get('ids') && preg_match('@(\d+,)*\d+@',get('ids')))
  $ids=explode(',',get('ids'));
if(!$ids)
  throw new Exception('nezadána id aktivit');
//uzamčení aktivit pro přihlašování  
dbQuery('UPDATE akce_seznam SET stav=2 WHERE id_akce='.implode(' OR id_akce=',$ids));
//tisk aktivit
$aktivity=VypisAktivit::zPoleId($ids);
$aktivity->tiskXtpl($xtpl,'aktivity.aktivita');
$xtpl->parse('aktivity');
$xtpl->out('aktivity');

?>
