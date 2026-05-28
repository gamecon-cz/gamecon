<?php

if(post('zapsatMaillist'))
{
  if(!post('mail') || post('mail')=='e-mail')
    chyba('Nezadali jste e-mail, neuloženo');
  dbQueryS('INSERT INTO maillist(mail,cas,zdroj) VALUES ($0,NOW(),"titulka")'
    ,array(post('mail')));
  oznameni('DÍKY');
}

$titulek->socNahled('/files/styly/styl-aktualni/soc-logo.jpg');

?>

<script type="text/javascript" src="/files/jquery.cycle.all.js"></script>

<div style="font-size:13px">

<strong style="font-size:150%">GameCon</strong> je největší festival nepočítačových her v České republice, který proběhne 18.–21. 7. 2013 v Mohelnici. Můžete se těšit na desítky <strong>RPGček, deskovek, larpů,</strong> akčních her, wargaming, přednášky, tradiční <strong>mistrovství v DrD</strong> a v neposlední řadě skvělé lidi a <strong>legendární párty</strong>.<br />

<div style="margin: 12px 0px; height:152px; box-shadow: #444 0 1px 10px" id="motivacniBox">
  <img src="/files/styly/styl-aktualni/motivacni-box/gc-1.jpg" width="480" />
  <img src="/files/styly/styl-aktualni/motivacni-box/gc-2.jpg" width="480" />
  <img src="/files/styly/styl-aktualni/motivacni-box/gc-3.jpg" width="480" />
  <img src="/files/styly/styl-aktualni/motivacni-box/gc-4.jpg" width="480" />
  <img src="/files/styly/styl-aktualni/motivacni-box/gc-5.jpg" width="480" />
</div>
<script type="text/javascript">
  $('#motivacniBox').cycle({
    fx: 'scrollLeft',
    easing: 'easeInOutBack',
    delay: -1500 
  });
</script>

Ať už chcete přijet jen na odpoledne nebo na celé čtyři dny, je vám k dispozci <strong>osobní program</strong>, který si můžete sestavit a <strong>ubytování</strong> po celou dobu festivalu. Nic dalšího není třeba – hry vás naučí a provede jimi početný tým organizátorů přímo na místě.<br /><br />

<div style="text-align:center;font-weight:bold;font-size:15px;margin:0 50px">GameCon za námi, Mohylvill v troskách. Fotky z něj naleznete v galerii na <a href="https://www.facebook.com/gamecon" title="Facebook">Facebooku</a> nebo <a href="https://plus.google.com/106567731930618318644/posts" title="Google+">Google+</a>.</div><br>

Vstup na <strong>GameCon</strong> je zdarma, platíte si jen ubytování a aktivity, na které opravdu jdete. Pokud jste student nebo se zapojíte do předfestivalového programu, můžete na všechny aktivity získat zásadní slevu.

<!--
<h2>Chci přijet</h2>

<form method="post" class="gcForm" style="margin: 4px 0 10px 0">
  <input type="text" size="30" name="mail" title="e-mail" />
  <input type="submit" name="zapsatMaillist" value="Zapsat se" />
</form>

Pokud chcete přijet na GameCon a mít svoje místo jisté, nebo prostě jen být mezi prvními, kteří budou vědět, až se otevře <strong>registrace a program</strong> GameConu v <strong>květnu 2013</strong>, nechte nám na sebe mail. (P.S.: vaše maily nikomu neprozradíme a nemáme rádi spam, takže se můžete kdykoli odhlásit :)
-->
</div>

