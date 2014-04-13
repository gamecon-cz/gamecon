<?php

if(post('zapsatMaillist'))
{
  if(!post('mail') || post('mail')=='e-mail')
    chyba('Nezadali jste e-mail, neuloženo');
  dbQueryS('INSERT INTO maillist(mail,cas,zdroj) VALUES ($0,NOW(),"titulka")'
    ,array(post('mail')));
  oznameni('DÍKY');
}

$titulek->socNahled('files/styly/styl-aktualni/soc-logo.jpg');

// blog hack
$blog = current(dbOneLine('SELECT obsah FROM stranky WHERE id_stranky = 120'));
$from = strpos($blog, '<!-- PRVNÍ ČLÁNEK -->');
$to = strpos($blog, '<!-- KONEC PRVNÍHO ČLÁNKU -->', $from);
$blog = substr($blog, $from, $to - $from);

?>

<script type="text/javascript" src="files/jquery.cycle.all.js"></script>

<style type="text/css">
h2 {margin-bottom: 2px}
h3 {margin-bottom: 4px}
.podpis {color: gray; size: 8pt; text-align:left}
.obrazek {width: 680px; margin-top: 4px;}
.obrazekObsah {width: 310px; float: left; margin: 0px 10px 0px 0px;}
.vice {text-align: right; font-size: 12pt;}
.side { display:none; }
.main { width: 680px; }
.main-middle-in { width: 680px; background-size: 100% auto; }
.main-top, .main-bottom, .main-middle { width: 712px; background-size: 100% 100%; }
</style>

<div style="font-size:13px">

<strong style="font-size:150%">GameCon</strong> je největší festival nepočítačových her v České republice, který proběhne 17.–20. 7. 2014 v Pardubicích. V roce 2014 se bude konat jubilejní 20. ročník a opět se těšte na desítky <strong>RPGček, deskovek, larpů,</strong> akčních her, wargaming, přednášky, klání v Příbězích Impéria, tradiční <strong>mistrovství v DrD</strong> a v neposlední řadě skvělé lidi a vůbec <strong>zážitky</strong>, které ve vás přetrvají minimálně do dalšího roku.<br>

<div style="margin: 12px 0px; height:215px; box-shadow: #444 0 1px 10px" id="motivacniBox">
  <img src="files/styly/styl-aktualni/motivacni-box/gc-1.jpg" width="680" />
  <img src="files/styly/styl-aktualni/motivacni-box/gc-2.jpg" width="680" />
  <img src="files/styly/styl-aktualni/motivacni-box/gc-3.jpg" width="680" />
  <img src="files/styly/styl-aktualni/motivacni-box/gc-4.jpg" width="680" />
  <img src="files/styly/styl-aktualni/motivacni-box/gc-5.jpg" width="680" />
</div>
<script type="text/javascript">
  $('#motivacniBox').cycle({
    fx: 'scrollLeft',
    easing: 'easeInOutBack',
    delay: -1500 
  });
</script>

<div style="font-size: 13pt; font-weight: bold; text-align: center; margin-top: 20px; margin-bottom: 12px;">Přihlašování na letošní ročník spouštíme <span style="color: #ab0000;"> 1.5. ve 20:00</span>.</div> Pokud nechcete, aby vám cokoliv uniklo, zanechte nám níže svůj mail a rádi se připomeneme s (pouze) důležitými aktualitami. Doporučujeme také sledovat náš profil na <a href="https://www.facebook.com/gamecon" onclick="return!window.open(this.href)" title="Facebook">Facebooku</a>.<br><br>

<form method="post" class="gcForm" style="margin: 4px 0 10px 0">
  <input type="text" size="30" name="mail" title="e-mail">
  <input type="submit" name="zapsatMaillist" value="Zapsat se">
</form>

<br><h1>Nejnovější blog</h1>

<?=$blog?>

<br><a class="vice" href="blog">Zobrazit všechny články</a>

</div>
