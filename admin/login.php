<?php

/** @var string $stranka */

/** @var string $podstranka */

/** @var SystemoveNastaveni $systemoveNastaveni */

use Gamecon\Login\Login;
use Gamecon\Web\Info;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

$info  = new Info($systemoveNastaveni);
$login = new Login($info, $systemoveNastaveni);

// načtení menu
$menuObject = new AdminMenu(__DIR__ . '/scripts/modules/');
$menu       = $menuObject->pole();

// načtení submenu
$submenu       = [];
$submenuObject = null;
if (!empty($menu[$stranka]['submenu'])) {
    $submenuObject = new AdminMenu(__DIR__ . '/scripts/modules/' . $stranka . '/', true);
    $submenu       = $submenuObject->pole();
}

// zjištění práv na zobrazení stránky
$strankaExistuje    = isset($menu[$stranka]);
$podstrankaExistuje = isset($submenu[$podstranka]);

// konstrukce stránky
if ($strankaExistuje) {
    $info->nazev($menu[$stranka]['nazev'], 'Administrace');
}

echo $login->htmlLogin();
