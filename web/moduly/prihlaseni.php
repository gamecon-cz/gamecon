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
    Chyba::nastav(hlaska('chybaPrihlaseni'));
}

?>

<?=$chyba?>

<form method="post" class="formular formular_stranka formular_stranka-login">
  <div class="formular_strankaNadpis">Přihlášení</div>

  <label class="formular_polozka">
    E-mailová adresa
    <input type="text" name="login" value="<?=post('login')?>" placeholder="">
  </label>
  <a href="zapomenute-heslo" class="formular_zapomenuteHeslo">zapomenuté heslo</a>
  <label class="formular_polozka">
    Heslo
    <input type="password" name="heslo" placeholder="">
  </label>
  <!-- Neodhlašovat: <input type="checkbox" name="trvale" value="true" checked><br> -->
  <input type="hidden" name="navrat" value="<?=post('navrat') ?? $_SERVER['HTTP_REFERER'] ?? ''?>">
  <input type="hidden" name="prihlasit" value="true">
  <input type="submit" value="Přihlásit" class="formular_primarni">

  <div class="formular_registrovat">
    Nemám účet <a href="registrace">registrovat</a>
  </div>
</form>
