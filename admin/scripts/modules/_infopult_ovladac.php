<?php

/**
 * akce proveditelné z infopult záložky
 */

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 * @var \Gamecon\Shop\Shop|null $shop
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

use Gamecon\Cas\Exceptions\InvalidDateTimeFormat;
use Gamecon\Role\Role;
use Gamecon\Uzivatel\Finance;
use Gamecon\Uzivatel\Exceptions\DuplicitniEmail;
use Gamecon\Uzivatel\Exceptions\DuplicitniLogin;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Cas\DateTimeCz;

if (!empty($_POST['prijelADatMaterialy']) && $uPracovni && $uPracovni->gcPrihlasen()) {
    $uPracovni->pridejRoli(Role::PRITOMEN_NA_LETOSNIM_GC, $u);
    back();
}

if (!empty($_POST['gcPrihlas']) && $uPracovni && !$uPracovni->gcPrihlasen()) {
    $uPracovni->gcPrihlas($u);
    back();
}

if (!empty($_POST['gcOdhlas']) && $uPracovni && !$uPracovni->gcPritomen() && $u->maRoli(Role::CFO)) {
    $uPracovni->odhlasZGc('rucne-inpfopult', $u);
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
        $castka             = post('platba');
        $poznamka           = post('poznamka');
        $idPohybu           = !$systemoveNastaveni->jsmeNaOstre()
            ? post('idPohybu')
            : null;
        $provedenoKdy       = post('provedenoKdy');
        $provedenoKdyObjekt = null;
        try {
            $provedenoKdyObjekt = $provedenoKdy && !$systemoveNastaveni->jsmeNaOstre()
                ? DateTimeImmutableStrict::createFromFormat(DateTimeCz::FORMAT_DATUM_A_CAS_STANDARD, $provedenoKdy)
                : null;
        } catch (InvalidDateTimeFormat $invalidDateTimeFormat) {
            chyba(sprintf("Neplatný formát data platby. Má být '%s'", DateTimeCz::FORMAT_DATUM_A_CAS_STANDARD));
        }

        $uPracovni->finance()->pripis(
            $castka,
            $u,
            $poznamka,
            $idPohybu,
            $provedenoKdyObjekt,
        );
        oznameni("Platba s částkou {$castka} byla připsána");
    } catch (DbDuplicateEntryException $dbDuplicateEntryException) {
        if (post('idPohybu') && FioPlatba::existujePodleFioId(post('idPohybu'))) {
            chyba(sprintf('Tato platba s Fio ID %d již existuje', post('idPohybu')), false);
        } else {
            chyba(
                sprintf("Platbu se nepodařilo uložit. Duplicitní záznam: '%s'", $dbDuplicateEntryException->getMessage()),
                false,
            );
        }
    }
    back();
}

if ($uPracovni && ($idPolozky = post(Finance::KLIC_ZRUS_NAKUP_POLOZKY)) && $u->jeSpravceFinanci()) {
    if ($uPracovni->shop()->zrusNakupPredmetu($idPolozky, -1 /* vsechny */)) {
        oznameni('Nákup položky zrušen');
    }
}

if (!empty($_POST['rychloregistrace'])) {
    try {
        $idUzivateleZRychloregistrace = Uzivatel::rychloregistrace($systemoveNastaveni);
    } catch (DuplicitniEmail $e) {
        throw new Chyba('Uživatel s zadaným e-mailem už v databázi existuje');
    } catch (DuplicitniLogin $e) {
        throw new Chyba('Uživatel s loginem odpovídajícím zadanému e-mailu už v databázi existuje');
    }
    if ($idUzivateleZRychloregistrace) {
        if ($uPracovni) {
            Uzivatel::odhlasKlic('uzivatel_pracovni');
        }
        $_SESSION["id_uzivatele"] = $idUzivateleZRychloregistrace;
        $uPracovni                = Uzivatel::prihlasId($idUzivateleZRychloregistrace, 'uzivatel_pracovni');
        if (!empty($_POST['vcetnePrihlaseni'])) {
            $uPracovni->gcPrihlas($u);
        }
        oznameni("Vytořen uživatel s ID {$uPracovni->id()}");
    }
}

// TODO: nevyužité, smazat nebo dodělat editaci na infompult
if (!empty($_POST['telefon']) && $uPracovni) {
    dbQueryS('UPDATE uzivatele_hodnoty SET telefon_uzivatele=$0 WHERE id_uzivatele=' . $uPracovni->id(), [$_POST['telefon']]);
    $uPracovni->otoc();
    back();
}

// TODO: mělo by být obsaženo v modelové třídě
function updateUzivatelHodnoty(array $udaje, int $uPracovniId, \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac): int
{
    try {
        $result = dbUpdate('uzivatele_hodnoty', $udaje, ['id_uzivatele' => $uPracovniId]);
        return dbAffectedOrNumRows($result);
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

    if (post('kontrolaOsobnichUdajuSubmit')) {
        $priznakZkontrolovanychUdajuZmenen = $uPracovni->nastavZkontrolovaneUdaje($u, (bool)post('kontrolaOsobnichUdaju'));
        if ($priznakZkontrolovanychUdajuZmenen) {
            $zmenenoZaznamu++;
        }
    }

    oznameni("Změněno $zmenenoZaznamu záznamů");
    back();
}

if (post('zpracujUbytovani')) {
    $shop->zpracujUbytovani(false, false);
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
            $query_string  = http_build_query($query_string);
            $targetAddress = explode("?", $_SERVER['HTTP_REFERER'])[0];
            header('Location: ' . $targetAddress . ($query_string != "" ? "?" . $query_string : ""), true, 303);
        } else
            back();
    } catch (Error $e) {
        back();
    }
}
