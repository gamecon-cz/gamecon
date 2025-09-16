<?php

use Gamecon\Uzivatel\AnonymizovanyUzivatel;
use Gamecon\XTemplate\XTemplate;

/**
 * nazev: Anonymizace 👻
 * pravo: 108
 * submenu_group: 6
 */

/**
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$t = new XTemplate(__DIR__ . '/anonymizace.xtpl');

// Anonymizace jednoho uživatele
if (post('anonymizovat_uzivatele')) {
    $idUzivatele = (int)post('id_uzivatele');

    if ($idUzivatele && $idUzivatele > 0) {
        try {
            $uzivatel = Uzivatel::zId($idUzivatele);
            if ($uzivatel) {
                AnonymizovanyUzivatel::anonymizujUzivatele($uzivatel);
                oznameni("Uživatel ID $idUzivatele byl anonymizován");
            } else {
                chyba("Uživatel s ID $idUzivatele nebyl nalezen");
            }
        } catch (Exception $e) {
            chyba("Chyba při anonymizaci uživatele: " . $e->getMessage());
        }
    } else {
        chyba("Neplatné ID uživatele");
    }
}

// Zobrazení formulářů
$t->parse('anonymizace');
$t->out('anonymizace');
