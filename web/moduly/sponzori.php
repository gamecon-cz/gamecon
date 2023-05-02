<?php

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */

$this->bezStranky(true);

{ // local variables scope
    foreach (['sponzor' => 'sponzori', 'partner' => 'partneri'] as $kategorie => $adresar) {
        foreach (glob(ADRESAR_WEBU_S_OBRAZKY . "/soubory/obsah/{$adresar}/*") as $soubor) {
            $fn = preg_replace('@.*/([^_].*)\.(?:jpg|png|gif)@', '$1', $soubor, -1, $pocetZmen);
            if ($pocetZmen === 0) {
                continue; // skrývání odebraných sponzorů ([^_] znamená "jen pokud nezačíná podtržítkem" a podtržítko znamená "vyřazený obrázek")
            }
            $t->assign([
                'url' => $fn,
                'src' => URL_WEBU . '/soubory/obsah/' . $adresar . '/' . basename($soubor),
            ]);
            $t->parse("sponzori.$kategorie");
        }
    }
}
