<?php

use Gamecon\Uzivatel\Finance;

/**
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

if (post('prepocitatPoukazyJednaAktivitaZdarma')) {
    $pocet = Finance::prepocitejVsechnyPoukazyNaJednuAktivitu();
    oznameni(sprintf(
        'Přepočítáno kupónů „jedna aktivita zdarma“: %d.',
        $pocet,
    ));
}
