<?php
require_once('sdilene-hlavicky.hhp');
$delay=1500;
$o=dbQuery('SELECT z.id_uzivatele, email1_uzivatele, ISNULL(zv.id_uzivatele) as vypravec
  FROM r_uzivatele_zidle z
  JOIN uzivatele_hodnoty u ON(u.id_uzivatele=z.id_uzivatele AND u.souhlas_maily=1)
  LEFT JOIN r_uzivatele_zidle zv ON(zv.id_uzivatele=z.id_uzivatele AND zv.id_zidle='.Z_ORG_AKCI.')
  WHERE z.id_zidle='.ID_ZIDLE_PRITOMEN.'
  -- AND email1_uzivatele>"z@email.cz" -- odesílat až od mailu X (pro případ záseku)
  -- AND u.id_uzivatele IN(618, 1054, 1040, 1255, 1220, 1219, 1052, 502, 51, 621, 132, 341, 119, 139, 342, 1009, 170, 726, 1132, 1230, 471) -- filtr pro případné ruční doplnění ID,
  ORDER BY email1_uzivatele');
//$o=dbQuery('SELECT 483 as id_uzivatele, "dmanik@seznam.cz" as email1_uzivatele'); // test
$pocet=mysql_num_rows($o);
set_time_limit($pocet*($delay/1000+1)); //1000ms na zprávu počítáme response rezervu
?>
<html>
<head>
  <script>
    function sb(){
      window.scrollTo(0, document.body.scrollHeight);
    }
  </script>
</head>
<body onload="sb()">
<h1>Odeslání mailu</h1>
  
<?php
if(!post('srsly'))
{
  ?>
  <p>Toto zaspamuje všechny (<?php echo $pocet ?>), kteří dorazili na GC, předpřipravenou zprávou.</p>
  <form method="post" onsubmit="return confirm('opravdu?')">
    <input type="submit" name="srsly" value="Zahájit spamování" />
  </form>
  <?php
}
else
{
  while($r=mysql_fetch_assoc($o))
  {
    $idHash=sprintf('%06x',bcmul($r['id_uzivatele'],971));
    //echo sprintf('%4d',hexdec($idHash)/971).'   '; //návrat zpět
    $link='https://docs.google.com/forms/d/1Tq3cg6dSNW4eLjISLRYunjZb4dUezAeg_fxt7CJJsqg/viewform?entry.4269671='.$idHash;
    $mail=new GcMail(
'Ahoj hráči a hráčky!<br><br>

Díky vám všem, že jste dorazili a udělali kouzlo jménem GameCon 2013. Jste to především vy, kdo děláte GameCon tak zábavným, dodáváte mu jeho atmosféru a nám dodáváte motivaci pracovat na dalším, jubilejním dvacátém ročníku. Fotky naleznete již brzy na <a href="https://www.facebook.com/gamecon">facebooku</a> a <a href="https://plus.google.com/106567731930618318644/posts">Google+</a>, máme na vás však ještě poslední prosbu.<br><br>

Chceme, aby byl GameCon pro vás co nejlepší. Proto vás moc <b>prosíme o vyplnění krátkého dotazníku</b> (10-13 otázek):<br>
<a href="'.$link.'">'.$link.'</a><br><br>

Z došlých odpovědí losujeme jako obvykle výherce deskovky.<br><br>

Děkujeme vám za pomoc<br>
Organizační tým GC 2013');
    $mail->adresat($r['email1_uzivatele']); //FIXME!!!
    $mail->predmet('Jaký byl GameCon '.ROK.'?');
    echo 'Odesílám na '.$r['email1_uzivatele'].'… '; ob_flush(); flush();
    echo '<script>sb()</script>'; //scrollování
    // TODO pokud dojde k failu odeslání, zkusit to ještě několikrát, třeba s pauzou
    $mail->odeslat(); echo 'odesláno.<br />'; ob_flush(); flush();
    //echo 'neodesláno (odkomentujte v kódu).<br />'; ob_flush(); flush();
    usleep($delay*1000);
  }
  echo '<br /><span style="color:green;font-weight:bold">Všechno odesláno.</span><br />Nemačkejte F5, zavřete okno.<br />';
}
?>

</body>
