<?php

require __DIR__ . '/sdilene-hlavicky.php';

$delay = 500;
$predmet = 'Jaký byl Gamecon 2015?';
$zprava = strtr(
'Ahoj GameCoňačky a GameCoňáci!

festival je za námi, čtyři dny utekly jako voda a my bychom vám chtěli moc poděkovat za vaši účast i nadšení.

Jako každý rok, bychom vás rádi moc poprosili o poslední věc: <b>Vyplnění dotazníku</b>, který nám dává cenná data a postřehy, kterak udělat další ročník lepší. Jeho vyplnění nezabere ani 10 minut a naleznete jej pod následujícím odkazem:
{link}

Z vašich odpovědí na podzim vylosujeme <b>výherce deskovky</b>.

Pokud již netrpělivě čekáte na fotky, první střípky od Darien naleznete na naší <a href="https://www.facebook.com/media/set/?set=a.1049227911755210.1073741834.127768447234499&type=3&uploaded=11" target="_blank">facebookové stránce</a>, naopak cokoliv sdílet s ostatními můžete ve <a href="https://www.facebook.com/groups/gamecon/" target="_blank">facebookové skupině</a>. Pokud vás pak zajímá, co letošní GameCon znamenal pro nás, píšeme o tom na našem <a href="http://gamecon.cz/blog/jaky-byl-gc-2015" target="_blank">blogu</a>.


Děkujeme vám všem za vaši přízeň a za to, že jste udělali letošní GameCon takový, jakým byl.

Těšíme se zase za rok, 14.-17.7. v Pardubicích!
Váš org.tým GC 2015', ["\n"=>"<br>\n"]);


////////////////////////////////////////////////////////////////////////////////

function echoFlush($text) {
  echo $text;
  @ob_flush();
  flush();
}




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
<div style="box-shadow: 0 0 10px #000; padding: 10px"><?=$zprava?></div><br>

<?php

  // skutečně odeslat maily
  if(post('mailyReal')) {
    $adresy = explode(';', post('mailyReal'));
    // kontrola časového limitu
    $limit = ini_get('max_execution_time');
    $odhad = (($delay / 1000) + 0.5) * count($adresy);
    if($limit < $odhad)
      die("Nelze odeslat, maximální doba skriptu je $limit&thinsp;s zatímco odhadované odeslání všech zabere až $odhad&thinsp;s.");
    // odeslat
    foreach($adresy as $adresa) {
      $uid = dbOneCol('SELECT id_uzivatele FROM uzivatele_hodnoty WHERE email1_uzivatele = $1', [$adresa]);
      $hash = $uid ? sprintf('%06x', bcmul($uid, 971)) : '000000';
      $link = 'https://docs.google.com/forms/d/1EMNE-WWHNL6TDfE1SsypMZwYVl5sd-ugUYLj2Bvm_uo/viewform?entry.4269671='.$hash;
      $mail = new GcMail();
      $mail->text( strtr($zprava, ['{link}' => "<a href=\"$link\">$link</a>"]) );
      $mail->adresat($adresa);
      $mail->predmet('Jaký byl GameCon '.ROK.'?');
      echoFlush('Odesílám na '.$adresa.'… ');
      echoFlush('<script>sb()</script>'); // scrollování
      if($mail->odeslat()) echoFlush('odesláno<br>');
      else echoFlush('chyba<br>');
      usleep($delay * 1000);
      //echo sprintf('%4d',hexdec($hash)/971).'   '; // návrat zpět hashe
    }
    ?>
    <br><span style="color:green;font-weight:bold">Všechno odesláno.</span><br>Nemačkejte F5, zavřete okno.<br>
    <?php

  // zobrazit maily, jak se načetly
  } elseif(post('maily')) {
    $maily = preg_replace('@\s*[;,]\s*|\s+@', "\n", post('maily'));
    $maily = explode("\n", $maily);
    ?>
    <form method="post">
      <input type="hidden" name="mailyReal" value="<?=implode(';', $maily)?>">
      <div><?=implode('; ', $maily)?></div>
      <div>(načteno <?=count($maily)?>)</div>
      <input type="submit" value="zahájit odesílání" onclick="return confirm('po kliknutí na OK se zahájí odeslání na všechny zadané maily')">
      <input type="button" value="zrušit" onclick="window.location.href=window.location.href">
    </form>
    <?php

  // zobrazit formulář na zadání mailů
  } else {
    ?>
    <form method="post">
      <textarea name="maily" style="display:block;width:100%;height:5em"></textarea>
      <input type="submit" value="načíst maily">
    </form>
    <?php
  }

?>
</body>
