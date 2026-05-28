<?php

/**
 * Vygenerování SQL dotazů pro update zůstatku gamecorun u letošních uživatelů.
 * Měl by být prováděn synchronně s překlopením proměnné "rok" v nastavení a
 * překlopením webu. Není jak zjistit, jestli byl nebo nebyl proveden, a zároveň
 * je potřeba ho provést jen jednou - je dobré udělat záznam někde (ideálně
 * na více místech), že se tak stalo.   
 */  

require_once('sdilene-hlavicky.hhp');

$hodnoty='';
$znackyText='';

if(post('znacky'))
{
  $znacky=explode("\n",post('znacky'));
  $o=dbQuery('
    SELECT *
    FROM prihlaska_ostatni p
    JOIN uzivatele_hodnoty u USING(id_uzivatele)
    WHERE p.rok='.ROK);
  $i=0;
  $uzivatele=array();
  while($r=mysql_fetch_assoc($o))
  {
    $un=new Uzivatel($r);
    $pohlavi=$r['pohlavi']=='f'?'žena':'muž';
    $uzivatele[$r['id_uzivatele']][]=$pohlavi;
    $uzivatele[$r['id_uzivatele']][]=$un->vek();
  }
  foreach($znacky as $znacka)
  {
    if(!preg_match('@\d+@',$znacka)) continue;
    $id=bcdiv(hexdec($znacka),971);
    if($id && isset($uzivatele[$id]))
      $hodnoty.=implode("\t",$uzivatele[$id])."\n";
    else
      $hodnoty.="\n";
    $znackyText.=$znacka."\n";
  }
}

?>

<h1>Údaje k doplnění anketní tabulky</h1>
<p>Do levého sloupce zkopírujte sloupec s značkami (bez hlavičkové buňky). V pravém se pak objeví údaje, možno ctrl+c ctrl+v zpět do godoc tabulky. S požadavky na rozšíření o další údaje mě kontaktujte klidně.</p>

<form method="post">
  <textarea style="width:100px;height:400px" name="znacky"><?php echo $znackyText ?></textarea>
  <textarea style="width:600px;height:400px" name="hodnoty"><?php echo $hodnoty ?></textarea>
  <br /><input type="submit" value="Načíst">
</form>

