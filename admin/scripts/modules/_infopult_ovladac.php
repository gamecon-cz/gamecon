<?php

/**
 * akce proveditelné z infopult záložky
 */

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 */

/**
 * @var \Gamecon\Shop\Shop|null $shop
 */

use Gamecon\Uzivatel\Finance;

if (!empty($_POST['datMaterialy']) && $uPracovni && $uPracovni->gcPrihlasen()) {
    $uPracovni->dejZidli(ZIDLE_PRITOMEN, $u);
    back();
}

if (!empty($_POST['gcPrihlas']) && $uPracovni && !$uPracovni->gcPrihlasen()) {
    $uPracovni->gcPrihlas($u);
    back();
}

if (!empty($_POST['gcOdhlas']) && $uPracovni && !$uPracovni->gcPritomen()) {
    $uPracovni->gcOdhlas($u);
    back();
}

if (post('gcOdjed') && $uPracovni) {
    $uPracovni->gcOdjed($u);
    back();
}

if (post('platba') && $uPracovni) {
    if (!$uPracovni->gcPrihlasen()) {
        varovani('Platba připsána uživateli, který není přihlášen na Gamecon', false);
    }
    try {
        $uPracovni->finance()->pripis(post('platba'), $u, post('poznamka'), post('idPohybu'));
    } catch (DbDuplicateEntryException $dbDuplicateEntryException) {
        if (post('idPohybu') && FioPlatba::existujePodleFioId(post('idPohybu'))) {
            chyba(sprintf('Tato platba s Fio ID %d již existuje', post('idPohybu')), false);
        } else {
            chyba(
                sprintf("Platbu se nepodařilo uložit. Duplicitní záznam: '%s'", $dbDuplicateEntryException->getMessage()),
                false
            );
        }
    }
    back();
}

if ($uPracovni && ($idPolozky = post(Finance::KLIC_ZRUS_NAKUP_POLOZKY)) && $u->jeSpravceFinanci()) {
    if ($uPracovni->dejShop()->zrusNakupPredmetu($idPolozky, -1 /* vsechny */)) {
        oznameni('Nákup položky zrušen');
    }
}

if (!empty($_POST['rychloreg'])) {
    $tab = $_POST['rychloreg'];
    if (empty($tab['login_uzivatele'])) {
        $tab['login_uzivatele'] = $tab['email1_uzivatele'];
    }
    $tab['nechce_maily'] = isset($tab['nechce_maily']) ? dbNow() : null;
    try {
        $nid = Uzivatel::rychloreg($tab, [
            'informovat' => post('informovat'),
        ]);
    } catch (DuplicitniEmailException $e) {
        throw new Chyba('Uživatel s zadaným e-mailem už v databázi existuje');
    } catch (DuplicitniLoginException $e) {
        throw new Chyba('Uživatel s loginem odpovídajícím zadanému e-mailu už v databázi existuje');
    }
    if ($nid) {
        if ($uPracovni) {
            Uzivatel::odhlasKlic('uzivatel_pracovni');
        }
        $_SESSION["id_uzivatele"] = $nid;
        $uPracovni = Uzivatel::prihlasId($nid, 'uzivatel_pracovni');
        if (!empty($_POST['vcetnePrihlaseni'])) {
            $uPracovni->gcPrihlas($u);
        }
        back();
    }
}

// TODO: nevyužité, smazat nebo dodělat editaci na infompult
if (!empty($_POST['telefon']) && $uPracovni) {
    dbQueryS('UPDATE uzivatele_hodnoty SET telefon_uzivatele=$0 WHERE id_uzivatele=' . $uPracovni->id(), [$_POST['telefon']]);
    $uPracovni->otoc();
    back();
}

if (!empty($_POST['prodej'])) {
    $prodej = $_POST['prodej'];
    unset($prodej['odeslano']);
    $prodej['id_uzivatele'] = $uPracovni ? $uPracovni->id() : Uzivatel::SYSTEM;
    for ($kusu = $prodej['kusu'] ?? 1, $i = 1; $i <= $kusu; $i++) {
        dbQuery('INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum)
  VALUES (' . $prodej['id_uzivatele'] . ',' . $prodej['id_predmetu'] . ',' . ROK . ',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu=' . $prodej['id_predmetu'] . '),NOW())');
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

// TODO: mělo by být obsaženo v modelové třídě
function updateUzivatelHodnoty(array $udaje, int $uPracovniId, \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac): int {
    try {
        $result = dbUpdate('uzivatele_hodnoty', $udaje, ['id_uzivatele' => $uPracovniId]);
        return dbNumRows($result);
    } catch (Exception $e) {
        $vyjimkovac->zaloguj($e);
        chyba('Došlo k neočekávané chybě.');
        return 0;
    }
}

/* Editace v kartě Pŕehled */
if ($uPracovni && $udaje = post('udaje')) {
    foreach (['potvrzeni_zakonneho_zastupce', 'potvrzeni_proti_covid19_overeno_kdy'] as $klic) {
        if (isset($udaje[$klic])) {
            // pokud je hodnota "" tak to znamená že nedošlo ke změně
            if ($udaje[$klic] == "")
                unset($udaje[$klic]);
            else
                $udaje[$klic] = date('Y-m-d');
        } else {
            $udaje[$klic] = null;
        }
    }

    // TODO(SECURITY): nebezpečné krmit data do databáze tímhle způsobem Každý si vytvořit do html formuláře input který se pak také propíŠe do DB
    $zmenenoZaznamu = updateUzivatelHodnoty($udaje, $uPracovni->id(), $vyjimkovac);
    oznameni("Změněno $zmenenoZaznamu záznamů");
    back();
}

if (post('zpracujUbytovani')) {
    $shop->zpracujUbytovani(false);
    oznameni('Ubytování uloženo');
}

if (post('pridelitPokoj') && $uPracovni) {
    $pokojPost = post('pokoj');
    Pokoj::ubytujNaCislo($uPracovni, $pokojPost);
    oznameni('Pokoj přidělen', false);
    try {
        if ($_SERVER['HTTP_REFERER']) {
            parse_str($_SERVER['QUERY_STRING'], $query_string);
            unset($query_string['req']);
            unset($query_string['pokoj']);
            $query_string = http_build_query($query_string);
            $targetAddress = explode("?", $_SERVER['HTTP_REFERER'])[0];
            header('Location: ' . $targetAddress . ($query_string != "" ? "?" . $query_string : ""), true, 303);
        } else
            back();
    } catch (Error $e) {
        back();
    }
}
