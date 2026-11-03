<?php

use Gamecon\Web\SouboryWebu;

/**
 * PDF, zvuk, archivy a tabulky ke stažení, na které odkazují texty webu
 *
 * nazev: Soubory
 * ikona: file-arrow-up
 * pravo: 105
 */

$souboryWebu = new SouboryWebu();
$souboryWebu->zpracujPost();

echo $souboryWebu->html();
