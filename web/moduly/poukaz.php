<?php

declare(strict_types=1);

/** @var Uzivatel|null $u */

use Gamecon\Role\Role;

$prihlasen = (bool) $u;
$gcPrihlasen = $prihlasen && $u->gcPrihlasen();
$jizUplatneno = $prihlasen && $u->maRoli(Role::LETOSNI_JEDNA_AKTIVITA_ZDARMA);

// Kód zpracujeme jen ve stavu, kdy ho uživatel reálně smí uplatnit (přihlášený,
// přihlášený na GC a zatím bez slevy). V ostatních stavech se místo formuláře
// zobrazí informace, co má udělat – viz výběr bloku níže.
if ($gcPrihlasen && ! $jizUplatneno && post('kod')) {
    $kodInput = post('kod');
    $kod = is_scalar($kodInput) ? strtoupper(trim((string) $kodInput)) : '';
    if ($kod === '') {
        chyba('Tento slevový kód neplatí. Zkontroluj, že jsi ho opsal(a) správně a velkými písmeny, nebo jestli už nebyl použitý.');
    } else {
        $uplatneno = false;
        $jizMelRoli = false;
        dbBegin();
        try {
            $zmeneno = dbAffectedOrNumRows(dbQuery(
                'UPDATE slevove_kody SET usedAt = NOW(), usedBy = $0 WHERE kod = $1 AND invalidated = 0 AND usedAt IS NULL',
                [$u->id(), $kod],
            ));
            if ($zmeneno) {
                // pridejRoli vrátí false, pokud roli uživatel už má (např. ze souběžného
                // requestu). V tom případě poukaz NEpálíme – jinak by se platný kód
                // „spotřeboval“, aniž by uživateli cokoli nového přidal.
                $roleNovePridana = $u->pridejRoli(Role::LETOSNI_JEDNA_AKTIVITA_ZDARMA, Uzivatel::zId(Uzivatel::SYSTEM));
                if ($roleNovePridana) {
                    dbCommit();
                    $uplatneno = true;
                } else {
                    dbRollback();
                    $jizMelRoli = true;
                }
            } else {
                dbRollback();
            }
        } catch (Throwable $throwable) {
            dbRollback();
            throw $throwable;
        }
        if ($uplatneno) {
            oznameniPresmeruj('Slevový poukaz byl úspěšně uplatněn – jedna aktivita teď bude zdarma.', URL_WEBU);
        }
        if ($jizMelRoli) {
            oznameniPresmeruj('Slevu na jednu aktivitu už máš aktivovanou, tenhle poukaz proto nebyl použit a zůstává platný.', URL_WEBU);
        }
        chyba('Tento slevový kód neplatí. Zkontroluj, že jsi ho opsal(a) správně a velkými písmeny, nebo jestli už nebyl použitý.');
    }
}

$this->blackarrowStyl(true);
$this->info()->nazev('Uplatnění slevového poukazu');

$t->assign('urlWebu', URL_WEBU);

if (! $prihlasen) {
    $t->parse('poukaz.neprihlasen');
} elseif (! $gcPrihlasen) {
    $t->parse('poukaz.bezGcPrihlasky');
} elseif ($jizUplatneno) {
    $t->parse('poukaz.jizUplatneno');
} else {
    $t->parse('poukaz.formular');
}
