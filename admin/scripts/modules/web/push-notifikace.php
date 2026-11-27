<?php

declare(strict_types=1);

use Gamecon\PushNotifikace\PushNotifikace;
use Gamecon\PushNotifikace\StrankaAdminu;

/**
 * Ruční odeslání push notifikace vybranému (pracovnímu) uživateli na všechna jeho zařízení.
 * Pravidla v Gamecon\PushNotifikace\OdeslaniZAdminu, koncept v docs/generated/web-push-notifikace.md.
 *
 * nazev: Push notifikace
 * ikona: bell
 * pravo: 119
 * submenu_group: 8
 * submenu_order: 1
 */

/**
 * @var Uzivatel                                       $u
 * @var Uzivatel|null                                  $uPracovni
 * @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */
$strankaPushNotifikaci = new StrankaAdminu(
    PushNotifikace::zNastaveni($systemoveNastaveni),
    $u,
    $uPracovni ?? null,
    URL_ADMIN . '/web/push-notifikace',
);
$presmerovatNa = $strankaPushNotifikaci->zpracuj($_POST, $_SERVER, URL_ADMIN);
if ($presmerovatNa !== null) {
    back($presmerovatNa);
}

echo $strankaPushNotifikaci->getHtml(StrankaAdminu::VARIANTA_RUCNI);
