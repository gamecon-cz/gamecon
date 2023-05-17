<?php

use Gamecon\Kanaly\GcMail;

$this->blackarrowStyl(true);

function obnovHesloUzivateli($uzivatel)
{
    if (!$uzivatel) chyba('Chyba: Zadané uživatelské jméno nebo email neexistují.');

    // vygenerování nového hesla
    $pwgen = new PWGen\PWGen();
    $pwgen->setAmbiguous(false);
    $noveHeslo = $pwgen->generate();

    // příprava mailu
    $mail = new GcMail(hlaskaMail('zapomenuteHeslo', $uzivatel, $uzivatel->mail(), $noveHeslo));
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
    obnovHesloUzivateli($u);
}

if (post('mail')) {
    $u = Uzivatel::zMailu(post('mail'));
    obnovHesloUzivateli($u);
}

?>

<div class="stranka">

    <h1>Zapomenuté heslo</h1>

    <p>Pokud jste zapomněli své heslo, můžete si nechat vygenerovat nové a zaslat si ho na email, který je pro váš účet
        aktivní. Po úspěšném přihlášení doporučujeme heslo změnit.</p>

    <h2>Vygenerovat nové heslo a zaslat na email</h2>

    <?php
    if (date('Y-m-d') === '2023-05-11') {
        ?>
        <div>
            <strong>
                Omlouváme se, do dnešní půlnoci Gamecon.cz neodesílá maily, takže Ti ani nepřijde email pro obnovu
                hesla.
                Zkus to prosím zítra.
            </strong>
        </div>
        <?php
    } else {
        ?>
        <strong>Znám svůj login:</strong>
        <form method="post">
            <input type="text" name="login">
            <input type="submit" value="odeslat">
        </form>

        <strong>Znám svůj e-mail:</strong>
        <form method="post">
            <input type="text" name="mail">
            <input type="submit" value="odeslat">
        </form>
        <?php
    }
    ?>
</div>
