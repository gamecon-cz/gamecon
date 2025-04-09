<?php

$sponzoriZdrojDir = ADRESAR_WEBU_S_OBRAZKY . '/soubory/systemove/sponzori';
$partneriZdrojDir = ADRESAR_WEBU_S_OBRAZKY . '/soubory/systemove/partneri';

$sponzoriCilDir = ADRESAR_WEBU_S_OBRAZKY . '/soubory/obsah/sponzori/titulka';
$partneriCilDir = ADRESAR_WEBU_S_OBRAZKY . '/soubory/obsah/partneri/titulka';

$zdrojeACile = [$partneriZdrojDir => $partneriCilDir, $sponzoriZdrojDir => $sponzoriCilDir];

foreach ($zdrojeACile as $zdroj => $cil) {
    if (!is_dir($zdroj)) {
       continue;
    }
    if (!file_exists($cil) && !@mkdir($cil, 0755, true) && !is_dir($cil)) {
        throw new \RuntimeException("Cílový adresář '{$cil}' neexistuje a nelze ho vytvořit.");
    }
    foreach (scandir($zdroj) as $soubor) {
        if ($soubor === '.' || $soubor === '..') {
            continue;
        }
        $zdrojSoubor = $zdroj . '/' . $soubor;
        $cilSoubor   = $cil . '/' . $soubor;
        if (file_exists($cilSoubor)) {
            if (filesize($cilSoubor) > 0) {
                continue;
            }
            if (!unlink($cilSoubor) && file_exists($cilSoubor)) {
                throw new \RuntimeException("Chyba při mazání '{$cilSoubor}'");
            }
        }
        if (!copy($zdrojSoubor, $cilSoubor)) {
            throw new \RuntimeException("Chyba při kopírování '{$zdrojSoubor}' do '{$cilSoubor}'");
        }
    }
}
