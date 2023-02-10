<?php

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 * @var \Gamecon\Shop\Shop $shop
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
    $shop->zpracujUbytovani();
    oznameni('Ubytování uloženo');
}

if (post('zpracujJidlo')) {
    $shop->zpracujJidlo();
    oznameni('Jídlo uloženo');
}

if (post('zmenitUdaj') && $uPracovni) {
    $udaje = post('udaj');
    if ($udaje['op'] ?? null) {
        $uPracovni->cisloOp($udaje['op']);
        unset($udaje['op']);
    }
    try {
        dbUpdate('uzivatele_hodnoty', $udaje, ['id_uzivatele' => $uPracovni->id()]);
    } catch (DbDuplicateEntryException $e) {
        if ($e->key() === 'email1_uzivatele') {
            chyba('Uživatel se stejným e-mailem již existuje.');
        } else if ($e->key() === 'login_uzivatele') {
            chyba('Uživatel se stejným e-mailem již existuje.');
        } else {
            chyba('Uživatel se stejným údajem již existuje.');
        }
    } catch (Exception $e) {
        $vyjimkovac->zaloguj($e);
        chyba('Došlo k neočekávané chybě.');
    }

    $uPracovni->otoc();
    back();
}

if (!empty($_POST['prodej'])) {
    $prodej = $_POST['prodej'];
    unset($prodej['odeslano']);
    $prodej['id_uzivatele'] = $uPracovni ? $uPracovni->id() : Uzivatel::SYSTEM;
    for ($kusu = $prodej['kusu'] ?? 1, $i = 1; $i <= $kusu; $i++) {
        dbQuery('INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum)
  VALUES (' . $prodej['id_uzivatele'] . ',' . $prodej['id_predmetu'] . ',' . ROCNIK . ',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu=' . $prodej['id_predmetu'] . '),NOW())');
    }
    $idPredmetu = (int)$prodej['id_predmetu'];
    $nazevPredmetu = dbOneCol(
        <<<SQL
      SELECT nazev FROM shop_predmety
      WHERE id_predmetu = $idPredmetu
      SQL
    );
    $yu = '';
    if ($kusu >= 5) {
        $yu = 'ů';
    } elseif ($kusu > 1) {
        $yu = 'y';
    }
    oznameni("Prodáno $kusu kus$yu $nazevPredmetu");
    back();
}
