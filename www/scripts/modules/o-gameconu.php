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

// hlavička hack
$hlavicka = dbOneCol('SELECT obsah FROM stranky WHERE id_stranky = 125');
$hlavicka = markdown($hlavicka);

// blog hack
$blog = current(dbOneLine('SELECT obsah FROM stranky WHERE id_stranky = 120'));
$from = strpos($blog, '<!-- PRVNÍ ČLÁNEK -->');
$to = strpos($blog, '<!-- KONEC PRVNÍHO ČLÁNKU -->', $from);
$blog = substr($blog, $from, $to - $from);

?>

<?=$hlavicka?>

<form method="post" class="gcForm" style="margin: 4px 0 10px 0">
  <input type="text" size="30" name="mail" title="e-mail">
  <input type="submit" name="zapsatMaillist" value="Zapsat se">
</form>

<br><h1>Nejnovější blog</h1>

<?=$blog?>

<br><a class="vice" href="blog">Zobrazit všechny články</a>

</div>
