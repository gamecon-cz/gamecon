<?php

$this->blackarrowStyl(true);

$chyba = '';

if(post('odhlasit')) {
  if($u) $u->odhlas();
  back();
}

if(post('prihlasit')) {
  if(post('zapamatovat'))
    $u = Uzivatel::prihlasTrvale(post('login'), post('heslo'));
  else
    $u = Uzivatel::prihlas(post('login'), post('heslo'));

  if($u)
    back(post('navrat'));
  else
    $chyba = hlaska('chybaPrihlaseni');
}

// TODO linky z starého webu by sem snad neměly vést, ale ověřit
// TODO nepoužívá standardní volání `chyba()`, protože nezachovává post proměnné a mizely by hodnoty v formuláři a referrer
?>

<?=$chyba?>

<form method="post">
  E-mail: <input type="text" name="login" value="<?=post('login')?>"><br>
  Heslo:  <input type="password" name="heslo"><br>
  <input type="hidden" name="navrat" value="<?=post('navrat') ?? $_SERVER['HTTP_REFERER'] ?? ''?>">
  <input type="hidden" name="prihlasit" value="true">
  <input type="submit" value="Přihlásit">
</form>

<!-- TODO linky na registraci a zapomenuté heslo -->
