<?php

/** @var Uzivatel $u */
/** @var Uzivatel|null $uPracovni */

// todo: asi lepší všechny neexistující api z adminu přeposílat automaticky na web než pro každý vytvářet takový soubor

$u = $uPracovni;

require ADMIN . '/../web/moduly/api/aktivitaAkce.php';
