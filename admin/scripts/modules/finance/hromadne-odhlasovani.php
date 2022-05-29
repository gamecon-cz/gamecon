<?php

use Gamecon\XTemplate\XTemplate;

/**
 * Nástroj na hromadné odhlašování účastníků (obvykle neplatičů)
 *
 * nazev: Hromadné odhlášení účastníků
 * pravo: 108
 * submenu_group: 5
 */

/**
 * @var Uzivatel $u
 */

/**
 * @param array $idUzivatelu
 * @return Uzivatel[]|null
 */
$dejUzivatelekOdhlaseni = static function (array $idUzivatelu): ?array {
    $idUzivatelu = array_unique(
        array_map('intval', $idUzivatelu)
    );
    $neznamaId = [];
    $uzivatele = array_map(static function ($id) use (&$neznamaId) {
        $uzivatel = Uzivatel::zId($id);
        if (!$uzivatel) {
            $neznamaId[] = $id;
        }
        return $uzivatel;
    }, $idUzivatelu);

    $potize = [];
    if (count($neznamaId) > 0) {
        $uzivatele = array_filter($uzivatele);
        $potize[] = 'Některá ID uživatelů neexistují. Zkontroluj je a případně vyřaď ze seznamu k odhlášení: ' . implode(',', $neznamaId);
    }

    array_walk(
        $uzivatele,
        static function (Uzivatel $uzivatel) use (&$potize) {
            if ($uzivatel->gcPritomen()) {
                $potize[] = sprintf("%s s ID %d už je na Gameconu. Zkontroluj seznam a případně ID vyřaď ze seznamu k odhlášení.", $uzivatel->jmenoNick(), $uzivatel->id());
            }
        }
    );
    if (count($potize) > 0) {
        chyba(implode('; ', $potize));
        return null;
    }

    $odhlaseni = array_filter($uzivatele, static function (Uzivatel $uzivatel) {
        return !$uzivatel->gcPrihlasen();
    });
    if (count($odhlaseni)) {
        oznameni(sprintf(
            "Někteří účastníci jsou již odhlášeni z Gameconu. Vyřazuji je ze seznamu k odhlášení: %s",
            implode(', ', array_map(static function (Uzivatel $uzivatel) {
                return $uzivatel->jmenoNick() . ' s ID ' . $uzivatel->id();
            }, $odhlaseni))
        ), false);
        $uzivatele = array_filter($uzivatele, static function (Uzivatel $uzivatel) {
            return $uzivatel->gcPrihlasen();
        });
    }

    return $uzivatele;
};

$t = new XTemplate(__DIR__ . '/hromadne-odhlasovani.xtpl');

$idUzivateluRaw = trim(post('ids') ?? '');
$t->assign('ids', $idUzivateluRaw);

if (post('pripravit')) {
    ini_set('memory_limit', '256M');
    if ($idUzivateluRaw === '') {
        chyba('Žádní uživatelé nebyli odhlášeni. Nejdříve vyplň jejich IDčka.');
        return;
    }
    if (!preg_match_all('~(?<idcka>\d+)~', $idUzivateluRaw, $matches)) {
        chyba('Žádní uživatelé nebyli odhlášeni. Zadaná IDčka nejsou čísla.');
        return;
    }
    $idUzivatelu = $matches['idcka'];

    $uzivatele = $dejUzivatelekOdhlaseni($idUzivatelu);

    if (count($uzivatele) > 0) {
        foreach ($uzivatele as $uzivatel) {
            $t->assign('id', $uzivatel->id());
            $t->assign('jmenoNick', $uzivatel->jmenoNick());
            $t->assign('stavUctu', $uzivatel->finance()->stavHr());
            $t->parse('hromadneOdhlasovani.vypis.uzivatel');
        }
        $t->parse('hromadneOdhlasovani.vypis');
    }
}

if (post('odhlasit')) {
    $idUzivatelu = post('id');
    $uzivatele = $dejUzivatelekOdhlaseni($idUzivatelu);

    array_walk($uzivatele, static function (Uzivatel $uzivatel) use (&$potize, $u) {
        try {
            $uzivatel->gcOdhlas($u);
        } catch (\Gamecon\Exceptions\CanNotKickOutUserFromGamecon $canNotKickOutUserFromGamecon) {
            $potize[] = sprintf(
                "Nelze ohlásit účastníka %s s ID %d: '%s'",
                $uzivatel->jmenoNick(),
                $uzivatel->id(),
                $canNotKickOutUserFromGamecon->getMessage()
            );
        }
    });

    $uPracovni = Uzivatel::zSession('uzivatel_pracovni');
    if ($uPracovni && in_array($uPracovni->id(), $idUzivatelu, false)) {
        $uPracovni->otoc(); // prenacti "prava" vcetne Prihlasen na GC, aby se v Uvodu neukazoval porad jako prihlaseny
    }

    oznameni(
        sprintf(
            'Bylo odhlášeno %d uživatelů (%s)',
            count($uzivatele),
            implode(', ', array_map(static function (Uzivatel $uzivatel) {
                return $uzivatel->jmenoNick();
            }, $uzivatele))
        )
    );
}

$t->parse('hromadneOdhlasovani');
$t->out('hromadneOdhlasovani');
