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

$obnovPracovnihoUzivateleAShop = static function () use (&$uPracovni, $u, $systemoveNastaveni, $nastaveni): ?Shop {
    $uPracovni = $uPracovni
        ? Uzivatel::zId($uPracovni->id())
        : null;

    return $uPracovni
        ? new Shop($uPracovni, $u, $systemoveNastaveni, $nastaveni)
        : null;
};

if (post('pridelitPokoj') && post('uid')) {
    $uzivatelProUbytovani = Uzivatel::zId(post('uid'));
    if ($uzivatelProUbytovani) {
        Pokoj::ubytujNaCislo($uzivatelProUbytovani, post('pokoj'));
        oznameni('Pokoj přidělen');
    } else {
        chyba("Neznámé ID uživatele " . post('uid'));
    }
}

if ($shop !== null) {
    if (post('zpracujUbytovani')) {
        $shop->zpracujUbytovani(true, false);
        $shop = $obnovPracovnihoUzivateleAShop();
        oznameni('Ubytování uloženo');
    }

    if (post('zpracujJidlo')) {
        $shop->zpracujJidlo();
        $shop = $obnovPracovnihoUzivateleAShop();
        oznameni('Jídlo uloženo');
    }
}

if (!empty($_POST['prodej'])) {
    $prodej = $_POST['prodej'];
    unset($prodej['odeslano']);
    $shop = new Shop(
        zakaznik: $uPracovni ?? Uzivatel::zId(Uzivatel::SYSTEM),
        objednatel: $u,
        systemoveNastaveni: $systemoveNastaveni,
        nastaveni: $nastaveni
    );
    $kusu = (int)($prodej['kusu'] ?? 1);
    $shop->prodat((int)$prodej['id_predmetu'], $kusu, true);

    back();
}
