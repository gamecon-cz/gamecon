<?php

/** 
 * Vyklikávací tabulky s prezencí na aktivity
 *
 * nazev: Prezence
 * pravo: 103
 */

?>


Prezence nefunguje kvůli převodu na nový systém teamových aktivit a bude opravena později.
<?php return; ?>


<?php
 
require_once('prezence.hhp');

$xtpl_temp=$xtpl; //uskladnění glbálního xtpl kvůli zpřístupnění proměnné $xtpl
$xtpl=new XTemplate('prezence.xtpl');

//zpracování data a času
$ted=new DateTime(DEN_PRVNI_DATE.' 14:34:40');
$ted=new DateTime();
$xtpl->assign('datum',$ted->format('j.n.'));
$xtpl->assign('casAktualni',$ted->format('H:i:s'));
$gcZacatek=new DateTime(DEN_PRVNI_DATE);
$delta=$ted->getTimestamp()-$gcZacatek->getTimestamp(); //rozdíl sekund od začátku GC
if(get('cas'))
{
  $cas=explode('-',get('cas'));
  $den=$cas[0];
  $zacatek=$cas[1];
  $xtpl->parse('prezence.casMan');
}
elseif($delta>0 and $delta<60*60*24*4)
{ //gc zatím asi probíhá, generujeme nejaktuálnější data
  $den=(int)$ted->format('j')-(int)$gcZacatek->format('j')+1;
  $zacatek=(int)$ted->format('G');
  $xtpl->parse('prezence.casAuto');
}
else
{
  $den=0;
  $zacatek=0;
  $xtpl->parse('prezence.nevybrano');
}

//roletková tabulka s manuálním výběrem data a času
$i=0;
foreach($PROGRAM_DNY as $d)
{
  for($j=PROGRAM_ZACATEK;$j<PROGRAM_KONEC;$j++)
  {
    $xtpl->assign('cas',$d.' '.$j.':00');
    $xtpl->assign('val',($i+1).'-'.$j);
    if($j==$zacatek && $i+1==$den)
      $xtpl->assign('sel','selected="selected"');
    else
      $xtpl->assign('sel','');
    $xtpl->parse('prezence.cas');
  }
  $i++;
}

//načtení a tisk seznamů aktivit
$a=dbQuery('
  SELECT a.*, u.*, 
    (a.kapacita+a.kapacita_m+a.kapacita_f) as kapacita_celkova,
    id_stavu_prihlaseni,
    org.login_uzivatele as orgLogin, 
    org.jmeno_uzivatele as orgJmeno, 
    org.prijmeni_uzivatele as orgPrijmeni,
    z.id_zidle as pritomen
  FROM akce_seznam a
  LEFT JOIN akce_prihlaseni p USING(id_akce)
  LEFT JOIN uzivatele_hodnoty u USING(id_uzivatele)
  LEFT JOIN uzivatele_hodnoty org ON(org.id_uzivatele=a.organizator)
  LEFT JOIN r_uzivatele_zidle z ON(p.id_uzivatele=z.id_uzivatele AND z.id_zidle='.Z_PRITOMEN.')
  WHERE rok='.ROK.' AND den='.$den.' AND zacatek='.$zacatek.'
  AND '.$typySql);
$r=null;
$dr=mysql_fetch_assoc($a);
$stav=0;
$pocet=0;
while($r=$dr)
{
  $dr=mysql_fetch_assoc($a);
  $xtpl->assign($r);
  $xtpl->assign('pritomen',$r['pritomen'] ? 
    '<img src="files/design/ok-s.png" style="margin-bottom:-1px">':
    '<img src="files/design/error-s.png" style="margin-bottom:-1px">');
  if($r['id_uzivatele']) //při 0 účastnících nic
  {
    $xtpl->parse('prezence.aktivita.form.ucastnik');
    $pocet++;
  }
  if($r['id_stavu_prihlaseni']) //pokud je stav <>0 tak už někdo prezenci zřejmě vyplnil
    $stav=$r['id_stavu_prihlaseni'];
  if($r['id_akce']!=$dr['id_akce']) //další akce má jiné ID, tato je potřeba parsnout
  {
    if(!$stav)
    {
      $xtpl->assign('org', /*TODO způsob zjištění jména*/ '');
      $xtpl->parse('prezence.aktivita.form');
      if($r['stav']!=2)
        $xtpl->parse('prezence.aktivita.nezamknutaUpo');
    }
    else
    {
      $xtpl->assign('ucast',$pocet);
      $xtpl->parse('prezence.aktivita.vyplnena');
      $xtpl->reset('prezence.aktivita.form.ucastnik'); //je potřeba vyčistit řádky, které se vyrobily jako aktivita.form.ucastnik
    }
    $xtpl->parse('prezence.aktivita');
    $stav=0;
    $pocet=0;
  }
}
if(mysql_num_rows($a)==0 && $den)
  $xtpl->parse('prezence.zadnaAktivita');

//dokončení zpracování template
$xtpl->parse('prezence');
$xtpl->out('prezence');

$xtpl=$xtpl_temp; //návrat globální proměnné $xtpl
