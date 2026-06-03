<?php

/** @var Uzivatel|null $u */

use Gamecon\Role\Role;

if (!$u) {
    back(URL_WEBU . '/prihlaseni');
}

if (!$u->gcPrihlasen()) {
    back(URL_WEBU . '/prihlaska');
}

if ($u->maRoli(Role::LETOSNI_JEDNA_AKTIVITA_ZDARMA)) {
    chyba('Uživatel už má aktivovaný slevový kód.');
}

if (post('kod'))
{
    if (dbRecordExists('slevove_kody', ['kod' => post('kod'), 'invalidated' => '0', 'usedAt' => null]))
    {
        dbBegin();
        $u->pridejRoli(Role::LETOSNI_JEDNA_AKTIVITA_ZDARMA, Uzivatel::zId(Uzivatel::SYSTEM));
        dbQuery('UPDATE slevove_kody SET usedAt = NOW(), usedBy = $0 WHERE kod = $1', [$u->id(), post('kod')]);
        dbCommit();
        oznameniPresmeruj("Poukaz byl úspěšně uplatněn.", URL_WEBU);
    } else
    {
        chyba("Neplatný kód poukazu.");
    }
}

$this->blackarrowStyl(true);

$this->info()->nazev('Uplatnění poukazu');
