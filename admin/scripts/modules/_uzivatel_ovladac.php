<?php

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\Shop;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 * @var \Gamecon\Shop\Shop|null $shop
 * @var array<string, bool> $nastaveni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$nastaveni ??= [];

if (post('pridelitPokoj') && $uPracovni) {
    Pokoj::ubytujNaCislo($uPracovni, post('pokoj'));
    oznameni('Pokoj přidělen');
}

if (!empty($_POST['prodej'])) {
    $prodej = $_POST['prodej'];
    unset($prodej['odeslano']);
    $shop = new Shop(
        zakaznik: $uPracovni ?? Uzivatel::zId(Uzivatel::ANONYM),
        objednatel: $u,
        systemoveNastaveni: $systemoveNastaveni,
        nastaveni: $nastaveni
    );
    $kusu = (int)($prodej['kusu'] ?? 1);
    $shop->prodat((int)$prodej['id_varianty'], $kusu, true);

    back();
}
