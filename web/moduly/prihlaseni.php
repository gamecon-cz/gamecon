<?php

$this->blackarrowStyl(true);

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
    chyba(hlaska('chybaPrihlaseni'));
}

// TODO linky z starého webu by sem snad neměly vést, ale ověřit
// TODO vracení na referrer při neúspěchu - `chyba()` nezachová post proměnnou
// TODO stejně tak login asi zachovávat (viz také otrlý hráč)
?>

<form method="post">
  E-mail: <input type="text" name="login"><br>
  Heslo:  <input type="password" name="heslo"><br>
  <input type="hidden" name="navrat" value="<?=$_SERVER['HTTP_REFERER'] ?? ''?>">
  <input type="hidden" name="prihlasit" value="true">
  <input type="submit" value="Přihlásit">
</form>
