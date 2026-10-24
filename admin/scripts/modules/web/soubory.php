<?php

use Gamecon\Web\SouboryWebu;

/**
 * PDF, zvuk, archivy a tabulky ke stažení, na které odkazují texty webu
 *
 * nazev: Soubory
 * pravo: 105
 */

$souboryWebu = new SouboryWebu();
$souboryWebu->zpracujPost();

echo $souboryWebu->html();
