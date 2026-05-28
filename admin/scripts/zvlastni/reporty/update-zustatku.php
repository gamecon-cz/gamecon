<?php

/**
 * Vygenerování SQL dotazů pro update zůstatku gamecorun u letošních uživatelů.
 * Měl by být prováděn synchronně s překlopením proměnné "rok" v nastavení a
 * překlopením webu. Není jak zjistit, jestli byl nebo nebyl proveden, a zároveň
 * je potřeba ho provést jen jednou - je dobré udělat záznam někde (ideálně
 * na více místech), že se tak stalo.   
 */  

require_once('sdilene-hlavicky.hhp');

$o=dbQuery('
  SELECT u.*
  FROM r_uzivatele_zidle z
  JOIN uzivatele_hodnoty u USING(id_uzivatele)
  WHERE z.id_zidle='.Z_PRIHLASEN);
$i=0;
$dotazy='';
while($r=mysql_fetch_assoc($o))
{
  $un=new Uzivatel($r);
  $un->nactiPrava(); //sql subdotaz, zlo
  $dotazy.='UPDATE uzivatele_hodnoty SET zustatek='.$un->finance()->gamecoruny().' WHERE id_uzivatele='.$un->id().";\n";
  $i++;
}

?>

<h1>Sql update pro uzavření financí</h1>

<p>Sled příkazů v okně po provedení na databázi aktualizuje zůstatky u jednotlivých uživatelů tak, jak reálně vypadají po skončení aktuálního gameconu (tj. ročník <?php echo ROK ?>).</p>

<p>Po provedení dotazu všechny výsledky výpočtu peněz <strong>přestanou platit</strong> až do okamžiku překlopení webu (tj. hlavně konstanty ROK) na další ročník.</p>

<textarea style="width:650px;height:400px">
<?php echo $dotazy ?>
</textarea>
