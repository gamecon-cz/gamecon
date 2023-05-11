<?php

/**
 * Stránka pro registraci a úpravu registračních údajů.
 *
 * Pokud je uživatel přihlášen, stránka vždycky slouží jen k úpravě. Pokud
 * uživatel přihlášen není, slouží vždy k registraci a poslání dál na přihlášku
 * na GC (pokud reg jede).
 *
 * Pokud uživatel není přihlášen a zkusí se přihlásit na GC, přihláška ho pošle
 * právě sem.
 */

use Gamecon\Uzivatel\Registrace;

/** @var Uzivatel|null $u */

$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev('Registrace');

$registrace = new Registrace($u);

$registrace->zpracujRegistraci();
$registrace->zpracujUpravu();

$registrace->zobrazHtml();
