<?php

declare(strict_types=1);

use Gamecon\PushNotifikace\PushNotifikace;
use Gamecon\PushNotifikace\StrankaAdminu;

/**
 * Testovací push notifikace: tlačítka s předpřipravenými notifikacemi (Gamecon\PushNotifikace\TestovaciNotifikace),
 * každé pošle jednu vybranému (pracovnímu) uživateli na všechna jeho zařízení.
 *
 * nazev: Testovací notifikace
 * ikona: bell-concierge
 * pravo: 119
 * submenu_group: 8
 * submenu_order: 2
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
    URL_ADMIN . '/web/push-notifikace-test',
);
$presmerovatNa = $strankaPushNotifikaci->zpracuj($_POST, $_SERVER, URL_ADMIN);
if ($presmerovatNa !== null) {
    back($presmerovatNa);
}

echo $strankaPushNotifikaci->getHtml(StrankaAdminu::VARIANTA_TESTOVACI);
