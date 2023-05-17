<?php

use Gamecon\Vyjimkovac\Vyjimkovac;

require_once __DIR__ . '/../../nastaveni/zavadec.php';

// TODO nutný hack před zmergeování zavaděče mezi redesignem a masterem
// tato proměnná je nastavena zavaděčem a zde upravíme zobrazení výjimek
/** @var Vyjimkovac $vyjimkovac */
$vyjimkovac->zobrazeni(Vyjimkovac::PLAIN);

/**
 * Výstup do logu
 */
function logs($s, bool $zalogovatCas = true)
{
    echo ($zalogovatCas ? date('Y-m-d H:i:s ') : '') . "<pre>$s</pre><br>\n";
}

if (defined('TESTING') && TESTING && !defined('MAILY_DO_SOUBORU')) {
    define('MAILY_DO_SOUBORU', true);
}
