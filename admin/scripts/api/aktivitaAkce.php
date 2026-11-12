<?php

use Gamecon\Admin\ApiAdminu;
use Gamecon\Admin\PracovniUzivatel;

/** @var Uzivatel $u */
/** @var Uzivatel|null $uPracovni */

// todo: asi lepší všechny neexistující api z adminu přeposílat automaticky na web než pro každý vytvářet takový soubor

// Přihlašovat pracovního uživatele smí jen infopult, ne každý, kdo ho smí mít, viz PracovniUzivatel::PRAVO_PROGRAMU.
if ($uPracovni !== null && ! PracovniUzivatel::isPovolenProgram($u)) {
    ApiAdminu::odmitni(403);

    return;
}

require ADMIN . '/../web/moduly/api/aktivitaAkce.php';
