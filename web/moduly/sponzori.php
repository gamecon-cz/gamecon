<?php

/** @var Modul $this */
/** @var \Gamecon\XTemplate\XTemplate $t */

//$this->bezStranky(true);

{ // local variables scope
    foreach (['sponzor' => 'sponzori', 'partner' => 'partneri'] as $kategorie => $adresar) {
        foreach (glob(ADRESAR_WEBU_S_OBRAZKY . "/soubory/obsah/{$adresar}/*") as $soubor) {
            if (!is_file($soubor)) {
                continue; // přeskočit, pokud to není soubor (adresář například)
            }
            if (str_starts_with(basename($soubor), '_')) {
                continue; // skrývání odebraných sponzorů - podtržítko znamená "vyřazený obrázek"
            }
            $nazev = pathinfo($soubor, PATHINFO_FILENAME);
            $basename = basename($soubor);

            // Odstranění vykřičníku z názvu souboru pro "alt" atribut
            $imageAlternative = ltrim($nazev, '!');

            $href = "https://$basename"; // v názvu je doména, sestavíme, platné URL

            // Podmínka pro nastavení odkazu a OnClick
            if (str_starts_with($nazev, '!')) {
                $href = '#'; // Odkaz bude "#", což znamená, že kliknutí nevede na nic
                // Vytvoření "OnClick" bloku, pokud je odkaz "#"
                $t->parse("sponzori.$kategorie.{$kategorie}OnClick");
            }

            $t->assign([
                'url' => $href,
                'src' => URL_WEBU . '/soubory/obsah/' . $adresar . '/' . basename($soubor),
                'alt' => $imageAlternative,
            ]);

            // Spuštění generování šablony pro danou kategorii
            $t->parse("sponzori.$kategorie");
        }
    }
}
