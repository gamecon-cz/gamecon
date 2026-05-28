<?php

/** 
 * nazev: Editace stránek
 * pravo: 105
 */

$xtpl2=new XTemplate('editace-stranek.xtpl');

if(post('obsahStranky'))
{
  $obsah=post('obsahStranky');
  dbQueryS('UPDATE stranky SET obsah=$obsah 
    WHERE id_stranky='  .post('zobrazit'));
}

if(post('zobrazit'))
{ //vybrána stránka k editaci
  $odpoved=dbQuery('SELECT * FROM stranky WHERE id_stranky='.post('zobrazit')); //todo nezabezpečeno proti injectu!!! viz fce dbQueryS
  $xtpl2->assign(mysql_fetch_array($odpoved));
  $xtpl2->parse('editaceStranek.stranka');
  $xtpl2->out('editaceStranek.stranka');
}
elseif(post('help'))
{
  $xtpl2->parse('editaceStranek.help');
  $xtpl2->out('editaceStranek.help');
}
else
{ //žádná stránka k editaci nevybrána, jen seznam
  $radky=dbQuery('SELECT s.url_stranky, s.id_stranky, m.nazev FROM stranky s
    LEFT JOIN menu m ON(m.navazana_stranka=s.id_stranky)
    ORDER BY m.nazev="" DESC, m.nazev');
  $skryta=0; //unimplemented
  while($radek=mysql_fetch_array($radky))
  {
    //unimplemented
    /*
    //if(!$skryta && $radek['skryta']) //přešli jsme do skrytých položek, oddělovač
      $xtpl2->parse('editaceStranek.vypis.radek.oddelovac');
    //$xtpl2->assign('odd',$odd?$odd='':$odd='odd'); //vyznaceni
    */ 
    $xtpl2->assign($radek);
    $xtpl2->parse('editaceStranek.vypis.radek');
    //$skryta=$radek['skryta']; //unimplemented
  }
  $xtpl2->parse('editaceStranek.vypis');
  $xtpl2->out('editaceStranek.vypis');
}



?>
