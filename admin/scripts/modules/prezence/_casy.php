<?php

use Gamecon\Cas\DateTimeCz;
use Gamecon\Aktivita\Aktivita;
use Gamecon\XTemplate\XTemplate;

/**
 * Vhackovaný code snippet na zobrazení vybírátka času
 * @param null|DateTimeCz $zacatekDt do tohoto se přiřadí vybraný čas začátku aktivit
 * @param bool $pred true jestli se má vybírat hodina před vybraným časem a false jestli vybraná hodina
 * @return string html kód vybírátka
 */
function _casy(&$zacatekDt, bool $pred = false) {

    $t = new XTemplate(__DIR__ . '/_casy.xtpl');

    $ted = new DateTimeCz();
//  $ted = (new DateTimeCz(PROGRAM_DO))->modify('-1 day'); // pro testovani "probihajiciho" Gamecon
    $t->assign('datum', $ted->format('j.n.'));
    $t->assign('casAktualni', $ted->format('H:i:s'));

    $zacatkyAktivit = Aktivita::zacatkyAktivit(new DateTimeCz(PROGRAM_OD), new DateTimeCz(PROGRAM_DO), 0, ['zacatek']);

    $vybrany = null;
    if (get('cas')) {
        // čas zvolený manuálně
        try {
            $vybrany = new DateTimeCz(get('cas'));
        } catch (Throwable $throwable) {
            $t->assign('chybnyCas', get('cas'));
            $t->parse('casy.chybaCasu');
        }
    } elseif (new DateTime(PROGRAM_OD) <= $ted && $ted <= (new DateTime(PROGRAM_DO))->setTime(23, 59, 59)) {
        // nejspíš GC právě probíhá, čas předvolit automaticky
        $chtenyZacatek = (clone $ted)->setTime(0, 0, 0);
        $chtenyZacatek->zaokrouhlitNaHodinyNahoru('H'); // nejblizsi hodina
        if ($pred) $chtenyZacatek->sub(new DateInterval('PT1H'));
        $posledniVhodnyZacatek = null;
        foreach ($zacatkyAktivit as $zacatekAktivity) {
            // zacatky aktivit jsou razeny od nejstarsich
            if ($zacatekAktivity <= $chtenyZacatek || !$posledniVhodnyZacatek) {
                $posledniVhodnyZacatek = $zacatekAktivity;
            }
            if ($zacatekAktivity == $chtenyZacatek) {
                break; // pozdejsi zacatek uz nenajdeme / nechceme
            }
        }
        if ($posledniVhodnyZacatek) {
            $vybrany = $posledniVhodnyZacatek;
            $t->parse('casy.casAuto');
        }
    } else { // zvolíme první cas, ve kterém je nějaká aktivita
        /** @var DateTimeCz $prvniZacatek */
        $prvniZacatek = reset($zacatkyAktivit);
        if ($prvniZacatek) {
            $vybrany = $prvniZacatek;
            $t->parse('casy.casAutoPrvni');
        }
    }

    if ($zacatkyAktivit) {
        foreach ($zacatkyAktivit as $zacatek) {
            $t->assign('cas', $zacatek->format('l') . ' ' . $zacatek->format('H') . ':00');
            $t->assign('val', $zacatek->format('Y-m-d') . ' ' . $zacatek->format('H') . ':00');
            $t->assign('sel', $vybrany && $vybrany->format('Y-m-d H') === $zacatek->format('Y-m-d H') ? 'selected' : '');
            $t->parse('casy.vyberCasu.cas');
        }
        $t->parse('casy.vyberCasu');
    } else {
        $t->parse('casy.zadnyCas');
    }

    $zacatekDt = $vybrany
        ? clone $vybrany
        : null;

    $t->parse('casy');
    return $t->text('casy');

}
