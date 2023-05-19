<?php

use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/** @var Modul $this */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$this->blackarrowStyl(true);

function obnovHesloUzivateli(?Uzivatel $uzivatel, SystemoveNastaveni $systemoveNastaveni)
{
    if (!$uzivatel) {
        chyba('Chyba: Zadané uživatelské jméno nebo email neexistují.');
    }

    // vygenerování nového hesla
    $pwgen = new PWGen\PWGen();
    $pwgen->setAmbiguous(false);
    $noveHeslo = $pwgen->generate();

    // příprava mailu
    $mail = new GcMail(
        $systemoveNastaveni,
        hlaskaMail('zapomenuteHeslo', $uzivatel, $uzivatel->mail(), $noveHeslo)
    );
    $mail->adresat($uzivatel->mail());
    $mail->predmet('Znovuposlání hesla na Gamecon.cz');

    // odeslání mailu a změna hesla
    if ($mail->odeslat()) {
        $uzivatel->heslo($noveHeslo);
        oznameni('E-mail s novým heslem byl odeslán.');
    } else {
        chyba('Chyba: e-mail se nepodařilo odeslat, kontaktuj nás prosím na info@gamecon.cz.');
        // TODO zalogovat warning
    }
}

if (post('login')) {
    $u = Uzivatel::zNicku(post('login'));
    obnovHesloUzivateli($u, $systemoveNastaveni);
}

if (post('mail')) {
    $u = Uzivatel::zMailu(post('mail'));
    obnovHesloUzivateli($u, $systemoveNastaveni);
}

?>

<div class="stranka">

    <h1>Zapomenuté heslo</h1>

    <p>Pokud jste zapomněli své heslo, můžete si nechat vygenerovat nové a zaslat si ho na email, který je pro váš účet
        aktivní. Po úspěšném přihlášení doporučujeme heslo změnit.</p>

    <h2>Vygenerovat nové heslo a zaslat na email</h2>

    <label for="loginProObnovuHesla"><strong>Znám svůj login:</strong></label>
    <form method="post">
        <input type="text" name="login" id="loginProObnovuHesla">
        <input type="submit" value="odeslat">
    </form>

    <label for="emailProObnovuHesla"><strong>Znám svůj e-mail:</strong></label>
    <form method="post">
        <input type="text" name="mail" id="emailProObnovuHesla">
        <input type="submit" value="odeslat">
    </form>
</div>
