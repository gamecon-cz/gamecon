<?php

$this->blackarrowStyl(true);

$chyba = '';

if(post('odhlasit')) {
  if($u) $u->odhlas();
  back();
}

if(post('prihlasit')) {
  if(post('trvale'))
    $u = Uzivatel::prihlasTrvale(post('login'), post('heslo'));
  else
    $u = Uzivatel::prihlas(post('login'), post('heslo'));

  if($u)
    back(post('navrat'));
  else
    $chyba = hlaska('chybaPrihlaseni');
}

?>

<?=$chyba?>

<form method="post">
  E-mail: <input type="text" name="login" value="<?=post('login')?>"><br>
  Heslo: <input type="password" name="heslo"><br>
  Neodhlašovat: <input type="checkbox" name="trvale" value="true" checked><br>
  <input type="hidden" name="navrat" value="<?=post('navrat') ?? $_SERVER['HTTP_REFERER'] ?? ''?>">
  <input type="hidden" name="prihlasit" value="true">
  <input type="submit" value="Přihlásit">
</form>

<a href="registrace">zaregistrovat</a>
<a href="zapomenute-heslo">zapomenuté heslo</a>
