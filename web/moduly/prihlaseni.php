<?php

$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev('Přihlášení');

/** @var Uzivatel|null $u */
if (post('odhlasit')) {
    if ($u) {
        $u->odhlas();
    }
    back();
}

if (post('prihlasit')) {
    if (post('trvale')) {
        $u = Uzivatel::prihlasTrvale(post('login'), post('heslo'));
    } else {
        $u = Uzivatel::prihlas(post('login'), post('heslo'));
    }

    if ($u) {
        back(URL_WEBU . '/prihlaska');
    } else {
        Chyba::nastav(hlaska('chybaPrihlaseni'));
    }
}

?>

<form method="post" class="formular formular_stranka formular_stranka-login">
    <div class="bg"></div>

    <div class="formular_strankaNadpis">Přihlášení</div>

    <label class="formular_polozka">
        E-mailová adresa
        <input type="text" name="login" autofocus value="<?= post('login') ?>" placeholder="" tabindex="1">
    </label>

    <a href="zapomenute-heslo" class="formular_zapomenuteHeslo" tabindex="2">zapomenuté heslo</a>
    <label class="formular_polozka">
        Heslo
        <input type="password" name="heslo" placeholder="" tabindex="1">
    </label>

    <label style="margin: 30px 0; display: block" class="formular_polozka-checkbox">
        <input type="checkbox" name="trvale" value="true" checked tabindex="1">
        Trvale přihlásit
    </label>

    <input type="hidden" name="prihlasit" value="true">

    <input type="submit" value="Přihlásit se" class="formular_primarni formular_primarni-sipka" tabindex="1">

    <div class="formular_registrovat formular_duleziteInfo">
        Nemám účet <a href="registrace" tabindex="2">registrovat</a>
    </div>
</form>
