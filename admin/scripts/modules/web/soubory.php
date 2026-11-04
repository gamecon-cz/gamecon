<?php

use Gamecon\Web\NahravaniPoCastech;
use Gamecon\Web\NahravaniPoCastechPozadavek;
use Gamecon\Web\SouboryWebu;

/**
 * PDF, dokumenty, zvuk, archivy, tabulky a obrázky ke stažení, na které odkazují texty webu
 *
 * nazev: Soubory
 * ikona: file-arrow-up
 * pravo: 105
 */

/**
 * @var Uzivatel $u
 */

$nahravaniPoCastech = NahravaniPoCastech::proUzivatele($u->id());
if (NahravaniPoCastechPozadavek::jePozadavek()) {
    (new NahravaniPoCastechPozadavek($nahravaniPoCastech))->odpovez();

    // exit, not $BEZ_DEKORACE: only the answer for the upload script, with nothing of the admin page around it
    exit;
}

$souboryWebu = new SouboryWebu(poCastech: $nahravaniPoCastech);
$souboryWebu->zpracujPost();

echo $souboryWebu->html();
