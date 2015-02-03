<?php

/**
 * Vhackovaný code snippet na zobrazení vybírátka času, klidně časem vyhodit a
 * zrefaktorovat slušně do prezence i tisku
 */

function _casy(&$zacatekDt, $pre = false) {

  $t = new XTemplate(__DIR__ . '/_casy.xtpl');

  //zpracování data a času
  //$ted=new DateTime(DEN_PRVNI_DATE.' 14:34:40');
  $ted=new DateTime();
  $t->assign('datum',$ted->format('j.n.'));
  $t->assign('casAktualni',$ted->format('H:i:s'));
  $gcZacatek=new DateTimeCz(DEN_PRVNI_DATE);
  $delta=$ted->getTimestamp()-$gcZacatek->getTimestamp(); //rozdíl sekund od začátku GC
  if(get('cas'))
  {
    $cas=explode('-',get('cas'));
    $den=$cas[0];
    $zacatek=$cas[1];
    $t->parse('casy.casMan');
  }
  elseif($delta>0 and $delta<60*60*24*4)
  { //gc zatím asi probíhá, generujeme nejaktuálnější data
    $den=(int)$ted->format('j')-(int)$gcZacatek->format('j')+1;
    $zacatek=(int)$ted->format('G') + ($pre ? 1 : 0);
    $t->parse('casy.casAuto');
  }
  else
  {
    $den=0;
    $zacatek=0;
  }

  //roletková tabulka s manuálním výběrem data a času
  $i=0;
  foreach($GLOBALS['PROGRAM_DNY'] as $d)
  {
    for($j=PROGRAM_ZACATEK;$j<PROGRAM_KONEC;$j++)
    {
      $t->assign('cas',$d.' '.$j.':00');
      $t->assign('val',($i+1).'-'.$j);
      if($j==$zacatek && $i+1==$den)
        $t->assign('sel','selected="selected"');
      else
        $t->assign('sel','');
      $t->parse('casy.cas');
    }
    $i++;
  }

  if($zacatek) {
    $zacatekDt = clone $gcZacatek;
    $zacatekDt->add(new DateInterval('P'.max(0, $den-1).'DT'.$zacatek.'H'));
  } else {
    $zacatekDt = null;
  }

  $t->parse('casy');
  return $t->text('casy');

}
