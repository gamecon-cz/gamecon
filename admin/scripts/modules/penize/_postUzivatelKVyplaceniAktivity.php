<?php

use Gamecon\Shop\Shop;

/**
 * @var Uzivatel $u
 * @var Uzivatel|null $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

if (post('uzivatelKVyplaceniAktivity')) {
    $uzivatel = Uzivatel::zId(post('uzivatelKVyplaceniAktivity'));
    if (!$uzivatel) {
        chyba(sprintf('Uživatel %d neexistuje.', post('uzivatelKVyplaceniAktivity')));
    }
    if (!$uzivatel->gcPrihlasen()) {
        chyba(sprintf('Uživatel %s není přihlášen na GameCon.', $uzivatel->jmenoNick()));
    }
    $shop            = new Shop($uzivatel, $u, $systemoveNastaveni);
    $prevedenaCastka = $shop->kupPrevodBonusuNaPenize();
    if (!$prevedenaCastka) {
        chyba(sprintf('Uživatel %s nemá žádný bonus k převodu.', $uzivatel->jmenoNick()));
    }
    $uzivatel->finance()->pripis(
        $prevedenaCastka,
        $u,
        post('poznamkaKVyplaceniBonusu'),
    );
    $numberFormatter = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
    oznameni(sprintf('Bonus %s vyplacen uživateli %s.', $numberFormatter->formatCurrency($prevedenaCastka, 'CZK'), $uzivatel->jmenoNick()));
}
