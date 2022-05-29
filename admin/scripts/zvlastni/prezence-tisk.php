<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\XTemplate\XTemplate;

$naStranku = 15;
$prazdnychRadku = 4;
$minSledujicich = 5;

$t = new XTemplate('prezence-tisk.xtpl');

$aktivity = Aktivita::zIds(get('ids'));

// řazení podle typu a názvu
usort($aktivity, static function (Aktivita $a, Aktivita $b): int {
    $rozdilTypu = (int)$a->typId() - (int)$b->typId(); // seřazní podle typu aktivity
    if ($rozdilTypu !== 0) {
        return $rozdilTypu;
    }

    return strcmp($a->nazev(), $b->nazev()); // seřazení podle názvu aktivity
});

$dejVekVeZkratce = static function (?int $vek): string {
    if ($vek === null) {
        return '?';
    }
    if ($vek >= 18) {
        return '18+';
    }
    return (string)$vek;
};

foreach ($aktivity as $aktivita) {
    $datum = $aktivita->zacatek();
    $aktivita->zamkni();
    $t->assign('aktivita', $aktivita);

    // pomocná funkce pro zalamování stránek
    $radkuNaStrance = 0;
    $pridejRadek = static function () use (&$radkuNaStrance, $naStranku, $t) {
        $radkuNaStrance++;
        if ($radkuNaStrance >= $naStranku) {
            $t->parse('aktivity.aktivita');
            $radkuNaStrance = 0;
        }
    };

    foreach ($aktivita->prihlaseni() as $prihlasenyUzivatel) {
        $vekCislo = $prihlasenyUzivatel->vekKDatu($datum);
        $vek = $dejVekVeZkratce($vekCislo);
        $t->assign('vek', $vek);
        $t->assign('u', $prihlasenyUzivatel);
        $t->parse('aktivity.aktivita.ucastnik');
        $pridejRadek();
    }

    for ($i = 0; $i < $prazdnychRadku; $i++) {
        $t->parse('aktivity.aktivita.prazdnyRadek');
        $pridejRadek();
    }

    $seznamSledujicich = $aktivita->seznamSledujicich();
    if ($seznamSledujicich) {
        $zbyvaRadku = $naStranku - $radkuNaStrance;
        $potrebaRadku = 2 + min(count($seznamSledujicich), $minSledujicich);
        if ($zbyvaRadku < $potrebaRadku) {
            for ($i = 0; $i < $zbyvaRadku; $i++) {
                $t->parse('aktivity.aktivita.prazdnyRadek');
            }
            $t->parse('aktivity.aktivita');
            $radkuNaStrance = 0;
        }

        $t->parse('aktivity.aktivita.hlavickaNahradnik');
        $radkuNaStrance += 2; // hlavička s náhradníky zabírá 2 řádky

        foreach ($seznamSledujicich as $sledujici) {
            $vek = $sledujici->vekKDatu($datum);
            if ($vek === null) {
                $vek = "?";
            } elseif ($vek >= 18) {
                $vek = "18+";
            }
            $t->assign('vek', $vek);
            $t->assign('u', $sledujici);
            $t->parse('aktivity.aktivita.nahradnik');
            $pridejRadek();

            // náhradníky tisknout jen do konce stránky
            if ($radkuNaStrance == 0) {
                break;
            }
        }
    }

    // dotisknout zbytek stránky, pokud je třeba
    while ($radkuNaStrance > 0) {
        $t->parse('aktivity.aktivita.prazdnyRadek');
        $pridejRadek();
    }
}

$t->parse('aktivity');
$t->out('aktivity');
