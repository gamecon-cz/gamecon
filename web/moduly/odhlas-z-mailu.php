<?php

$mail = get('email');

$uzivatel = Uzivatel::zMailu($mail);
if ($uzivatel) {
    $uzivatel->odhlasZMaileru();

    echo "<p>Váš e-mail $mail byl úspěšně odhlášen z odběru novinek.</p>";
}
