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
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev(t('registrace.nazev'));
$this->setObsahPrelozeny(true);

$registrace = new Registrace($systemoveNastaveni, $u);

if ($u) {
    // čtečka QR kódu pro přihlášení jiného zařízení, Registrace::zobrazHtml()
    $this->pridejJsSoubor(__DIR__ . '/../soubory/ctecka-qr.js');
}

$registrace->zpracujRegistraci();
$registrace->zpracujUpravu();
$registrace->zpracujOdhlaseniOstatnichZarizeni();

$registrace->zobrazHtml();
