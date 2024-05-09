<?php

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\Shop;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 * @var \Gamecon\Shop\Shop $shop
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

if (post('pridelitPokoj') && post('uid')) {
    $uzivatelProUbytovani = Uzivatel::zId(post('uid'));
    if ($uzivatelProUbytovani) {
        Pokoj::ubytujNaCislo($uzivatelProUbytovani, post('pokoj'));
        oznameni('Pokoj přidělen');
    } else {
        chyba("Neznámé ID uživatele " . post('uid'));
    }
}

if (post('zpracujUbytovani')) {
    $shop->zpracujUbytovani(true, false);
    oznameni('Ubytování uloženo');
}

if (post('zpracujJidlo')) {
    $shop->zpracujJidlo();
    oznameni('Jídlo uloženo');
}

if (!empty($_POST['prodej'])) {
    $prodej = $_POST['prodej'];
    unset($prodej['odeslano']);
    $shop = new Shop(
        zakaznik: $uPracovni ?? Uzivatel::zId(Uzivatel::SYSTEM),
        objednatel: $u,
        systemoveNastaveni: $systemoveNastaveni
    );
    $kusu = (int)($prodej['kusu'] ?? 1);
    $shop->prodat((int)$prodej['id_predmetu'], $kusu, true);

    back();
}
