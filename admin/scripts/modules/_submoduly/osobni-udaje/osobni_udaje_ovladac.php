<?php

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;
use Gamecon\Uzivatel\ZpusobZobrazeniNaWebu;

if (post('zmenitUdaj') && $uPracovni) {
    $udaje = (array)post('udaj');
    if ($udaje['op'] ?? null) {
        $uPracovni->cisloOp($udaje['op']);
        unset($udaje['op']);
    }
    if (isset($udaje['kontrola'])) {
        $uPracovni->nastavZkontrolovaneUdaje($u, (bool)$udaje['kontrola']);
        unset($udaje['kontrola']);
    }
    if (isset($udaje[Sql::ZPUSOB_ZOBRAZENI_NA_WEBU])) {
        $udaje[Sql::ZPUSOB_ZOBRAZENI_NA_WEBU]
            = ZpusobZobrazeniNaWebu::zHodnoty($udaje[Sql::ZPUSOB_ZOBRAZENI_NA_WEBU])->value;
    }
    $zmeniloSeJmenoNaWebu = Uzivatel::zmeniloSeJmenoNaWebu($uPracovni, $udaje);
    $udajeBylyUlozeny = false;
    try {
        if ($udaje !== []) {
            dbUpdate('uzivatele_hodnoty', $udaje, ['id_uzivatele' => $uPracovni->id()]);
            $udajeBylyUlozeny = true;
        }
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
    if ($udajeBylyUlozeny && $zmeniloSeJmenoNaWebu) {
        Uzivatel::invalidujProgramCacheJeLiVypravecem($uPracovni->id());
    }

    $uPracovni->otoc();

    if ($uPracovni->maZkontrolovaneUdaje()) {
        $maObjednaneUbytovani = $uPracovni->shop()->ubytovani()->maObjednaneUbytovani();
        $chybejiciUdaje       = $uPracovni->chybejiciUdaje(Uzivatel::povinneUdajeProRegistraci($maObjednaneUbytovani));
        if (count($chybejiciUdaje) > 0) {
            $uPracovni->nastavZkontrolovaneUdaje($u, false);
            $uPracovni->uloz();
            varovani('Uživatel nemá kompletní údaje. Potvrzení že měl údaje zkontrolované bylo zrušeno. Oprav údaje a zkontroluj je znovu.');
        }
    }

    back();
}
