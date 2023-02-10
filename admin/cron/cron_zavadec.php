<?php
require_once __DIR__ . '/../../nastaveni/zavadec.php';

// TODO nutný hack před zmergeování zavaděče mezi redesignem a masterem
// tato proměnná je nastavena zavaděčem a zde upravíme zobrazení výjimek
/** @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac */
$vyjimkovac->zobrazeni(\Gamecon\Vyjimkovac\Vyjimkovac::PLAIN);

/**
 * Výstup do logu
 */
function logs($s) {
    echo date('Y-m-d H:i:s ') . $s . "\n";
}

if (defined('TESTING') && TESTING && !defined('MAILY_DO_SOUBORU')) {
    define('MAILY_DO_SOUBORU', true);
}
