<?php

use Gamecon\Admin\ApiAdminu;
use Gamecon\Admin\PracovniUzivatel;

/**
 * $u zůstává operátor (jeho práva, třeba odemknutí týmu), webové API pracuje s týmem $uPracovni, a bez něj s týmem
 * přihlášeného.
 *
 * @var Uzivatel $u
 * @var Uzivatel|null $uPracovni
 */

// Tým pracovního uživatele (i jeho kód pro připojení) smí spravovat jen infopult, viz PracovniUzivatel::PRAVO_PROGRAMU.
if ($uPracovni !== null && ! PracovniUzivatel::isPovolenProgram($u)) {
    ApiAdminu::odmitni(403);

    return;
}

require ADMIN . '/../web/moduly/api/aktivitaTym.php';
