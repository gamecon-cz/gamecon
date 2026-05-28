<?php

require_once('sdilene-hlavicky.hhp');

$delay = 1500;
$predmet = 'Jaký byl Gamecon 2014?';
$zprava = strtr(
'Ahoj GameCoňáci!

Je to tři dny, co skončil poslední GameCon a dvacet let, co začal. Děkujeme, že jsme opět mohli potkat tolik skvělých a aktivních lidí, povídat si s vámi a hrát s vámi. Dodává nám to energii do dalšího roku.

Moc rádi bychom vás poprosili o jednu věc. Nejcennější zpětnou vazbou pro nás je <b>vyplnění dotazníku</b>, který najdete na adrese {link}.
Budeme vám nepopsatelně vděční, pokud věnujete těch 10 minut jeho vyplnění. Z došlých odpovědí navíc vylosujeme <b>výherce deskovky</b>.

Pokud by vás zajímalo, jak vidíme letošní ročník z naší strany, napíšeme o tom příští týden na našem <a href="http://gamecon.cz/blog">blogu</a>, abychom neovlivnili vaše   odpovědi. Většina fotek už je zveřejněných na naší FB <a href="https://www.facebook.com/media/set/?set=a.846204775390859.1073741832.127768447234499&type=3">stránce</a>, další galerie jsou pak od <a href="https://www.facebook.com/media/set/?set=oa.470697179734559&type=1">Darien</a> či <a href="https://www.facebook.com/filip.appl/media_set?set=a.10152578923963522.1073741850.660348521&type=3">Drirra</a>. Pokud byste se chtěli o cokoliv podělit s ostatnimi, máme i <a href="https://www.facebook.com/groups/gamecon/">skupinu</a>.

Díky vám všem, že jste proměnili tiché pardubické místo na čtyři dny plné života a zábavy. Uvidíme se v další pětiletce!

Užívejte zbytku léta a přejeme krásné prázdniny
Organizační tým GC2014', array("\n"=>"<br>"));


////////////////////////////////////////////////////////////////////////////////


$o = dbQuery('SELECT z.id_uzivatele, email1_uzivatele, ISNULL(zv.id_uzivatele) as vypravec
  FROM r_uzivatele_zidle z
  JOIN uzivatele_hodnoty u ON(u.id_uzivatele=z.id_uzivatele AND u.souhlas_maily=1)
  LEFT JOIN r_uzivatele_zidle zv ON(zv.id_uzivatele=z.id_uzivatele AND zv.id_zidle='.Z_ORG_AKCI.')
  WHERE z.id_zidle='.ID_ZIDLE_PRITOMEN.''.
  ( post('testuid') ? ' AND u.id_uzivatele = '.post('testuid').' ' : '' ).
  '
  AND email1_uzivatele > "michal.hambalek@seznam.cz" -- odesílat až od mailu X (pro případ záseku)
  -- AND u.id_uzivatele IN(618, 1054, 1040, 1255, 1220, 1219, 1052, 502, 51, 621, 132, 341, 119, 139, 342, 1009, 170, 726, 1132, 1230, 471) -- filtr pro případné ruční doplnění ID,
  ORDER BY email1_uzivatele');
//$o=dbQuery('SELECT 483 as id_uzivatele, "dmanik@seznam.cz" as email1_uzivatele'); // test
$pocet=mysql_num_rows($o);
//disabled na ostrém //set_time_limit($pocet*($delay/1000+1)); //1000ms na zprávu počítáme response rezervu
?>
<html>
<head>
  <script>
    function sb(){
      window.scrollTo(0, document.body.scrollHeight);
    }
  </script>
</head>
<body onload="sb()" style="font-family:sans-serif; width:800px; margin: auto">
<h1>Odeslání mailu</h1>
preview:<br>
<div style="box-shadow: 0 0 10px #000; padding: 10px"><?=$zprava?></div>
  
<?php
if( post('srsly') || post('probably') && mysql_num_rows($o) === 1 )
{
  while($r=mysql_fetch_assoc($o))
  {
    $idHash=sprintf('%06x',bcmul($r['id_uzivatele'],971));
    //echo sprintf('%4d',hexdec($idHash)/971).'   '; //návrat zpět
    $link='https://docs.google.com/forms/d/1_U2W5MxAMEqQkY99H3X9eu4WtVsrjiDSi2Uzhkm3VeQ/viewform?entry.4269671='.$idHash;
    $mail=new GcMail(strtr($zprava, array('{link}' => '<a href="'.$link.'">'.$link.'</a>')));
    $mail->adresat($r['email1_uzivatele']); //FIXME!!!
    $mail->predmet('Jaký byl GameCon '.ROK.'?');
    echo 'Odesílám na '.$r['email1_uzivatele'].'… '; @ob_flush(); flush();
    echo '<script>sb()</script>'; //scrollování
    // TODO pokud dojde k failu odeslání, zkusit to ještě několikrát, třeba s pauzou
    $mail->odeslat(); echo 'odesláno.<br />'; @ob_flush(); flush();
    //echo 'neodesláno (odkomentujte v kódu).<br />'; ob_flush(); flush();
    usleep($delay*1000);
  }
  echo '<br /><span style="color:green;font-weight:bold">Všechno odesláno.</span><br />Nemačkejte F5, zavřete okno.<br />';
}
else
{
  ?>
  <p>Toto zaspamuje všechny (<?php echo $pocet ?>), kteří dorazili na GC, předpřipravenou zprávou.</p>
  <form method="post" onsubmit="return confirm('opravdu?')">
    <input type="submit" name="srsly" value="Zahájit spamování" />
  </form>
  <form method="post">
    Odeslat test na id uživatele
    <input type="text" name="testuid">
    <input type="submit" name="probably" value="Odeslat test">
  </form>
  <?php
}

?>

</body>
