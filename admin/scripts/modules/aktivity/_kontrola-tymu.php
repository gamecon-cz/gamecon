<?php

declare(strict_types=1);

use Gamecon\XTemplate\XTemplate;

/**
 * Provede (mock) kontrolu stavu týmů a vyrenderuje výsledky do šablony.
 * TODO: nahradit skutečnými dotazy do databáze.
 */
function renderujVysledkyKontrolyTymu(XTemplate $tpl): void
{
    $tymyBezAktivity = [
        ['id' => 42, 'nazev' => 'Rychlé Šelmy'],
        ['id' => 77, 'nazev' => 'Modré Draky'],
    ];

    $pripraveneBezKapitana = [
        [
            'id'       => 13,
            'nazev'    => 'Tichá Voda',
            'aktivita' => 'Deskový turnaj',
            'clenove'  => [
                ['id' => 101, 'nick' => 'franta', 'jmeno' => 'František Novák'],
                ['id' => 102, 'nick' => 'pepa', 'jmeno' => 'Josef Dvořák'],
            ],
        ],
    ];

    $spatnaKola = [
        [
            'id'       => 55,
            'nazev'    => 'Zlaté Orly',
            'aktivita' => 'Velký turnaj',
            'kola'     => [
                [
                    'cislo'    => 1,
                    'cas'      => 'Pátek 14:00',
                    'aktivity' => [
                        ['id' => 201, 'nazev' => 'Velký turnaj sk. A', 'prihlasena' => true],
                        ['id' => 202, 'nazev' => 'Velký turnaj sk. B', 'prihlasena' => true],
                    ],
                ],
                [
                    'cislo'    => 2,
                    'cas'      => 'Sobota 10:00',
                    'aktivity' => [
                        ['id' => 203, 'nazev' => 'Velký turnaj sk. C', 'prihlasena' => false],
                        ['id' => 204, 'nazev' => 'Velký turnaj sk. D', 'prihlasena' => false],
                    ],
                ],
                [
                    'cislo'    => 3,
                    'cas'      => 'Neděle 10:00',
                    'aktivity' => [
                        ['id' => 205, 'nazev' => 'Velký turnaj finále', 'prihlasena' => true],
                    ],
                ],
            ],
        ],
    ];

    $hraciNeprihlaseni = [
        [
            'nick'           => 'jana',
            'jmeno'          => 'Jana Horáková',
            'idTymu'         => 13,
            'nazevTymu'      => 'Tichá Voda',
            'aktivita'       => 'Deskový turnaj',
            'chybiPrihlaska' => 'Kolo 2 – Deskový turnaj (sobota 14:00)',
        ],
        [
            'nick'           => 'marek',
            'jmeno'          => 'Marek Procházka',
            'idTymu'         => 55,
            'nazevTymu'      => 'Zlaté Orly',
            'aktivita'       => 'Velký turnaj',
            'chybiPrihlaska' => 'Kolo 3 – Velký turnaj (neděle 10:00)',
        ],
    ];

    $maNejakeChyby = false;

    if ($tymyBezAktivity) {
        $maNejakeChyby = true;
        $tpl->assign('pocetTymyBezAktivity', count($tymyBezAktivity));
        foreach ($tymyBezAktivity as $tym) {
            $tpl->assign([
                'tba_id'    => $tym['id'],
                'tba_nazev' => $tym['nazev'],
            ]);
            $tpl->parse('tymy.kontrolaVysledky.tymyBezAktivity.tymBezAktivity');
        }
        $tpl->parse('tymy.kontrolaVysledky.tymyBezAktivity');
    }

    if ($pripraveneBezKapitana) {
        $maNejakeChyby = true;
        $tpl->assign('pocetPripraveneBezKapitana', count($pripraveneBezKapitana));
        foreach ($pripraveneBezKapitana as $tym) {
            $tpl->assign([
                'pbk_id'       => $tym['id'],
                'pbk_nazev'    => $tym['nazev'],
                'pbk_aktivita' => $tym['aktivita'],
            ]);
            foreach ($tym['clenove'] as $clen) {
                $tpl->assign([
                    'cbk_id'    => $clen['id'],
                    'cbk_nick'  => $clen['nick'],
                    'cbk_jmeno' => $clen['jmeno'],
                ]);
                $tpl->parse('tymy.kontrolaVysledky.pripraveneBezKapitana.tymBezKapitana.clenBezKapitana');
            }
            $tpl->parse('tymy.kontrolaVysledky.pripraveneBezKapitana.tymBezKapitana');
        }
        $tpl->parse('tymy.kontrolaVysledky.pripraveneBezKapitana');
    }

    if ($spatnaKola) {
        $maNejakeChyby = true;
        $tpl->assign('pocetSpatnaKola', count($spatnaKola));
        foreach ($spatnaKola as $tym) {
            $tpl->assign([
                'sk_id'       => $tym['id'],
                'sk_nazev'    => $tym['nazev'],
                'sk_aktivita' => $tym['aktivita'],
            ]);
            foreach ($tym['kola'] as $kolo) {
                $pocetPrihlasenych = count(array_filter($kolo['aktivity'], fn($a) => $a['prihlasena']));
                $tpl->assign([
                    'koloSloupec_cislo' => $kolo['cislo'],
                    'koloSloupec_cas'   => $kolo['cas'],
                    'koloSloupec_trida' => $pocetPrihlasenych !== 1 ? 'tymy-kola-sloupec--chyba' : 'tymy-kola-sloupec--ok',
                ]);
                if ($kolo['aktivity'] === []) {
                    $tpl->parse('tymy.kontrolaVysledky.spatnaKolaTurnaje.tymSpatnaKola.koloSloupec.koloPrazdne');
                } else {
                    foreach ($kolo['aktivity'] as $aktivita) {
                        $tpl->assign([
                            'koloAktivita_id'    => $aktivita['id'],
                            'koloAktivita_nazev' => $aktivita['nazev'],
                        ]);
                        if ($aktivita['prihlasena']) {
                            $tpl->parse('tymy.kontrolaVysledky.spatnaKolaTurnaje.tymSpatnaKola.koloSloupec.koloAktivitaPrihlasena');
                        } else {
                            $tpl->parse('tymy.kontrolaVysledky.spatnaKolaTurnaje.tymSpatnaKola.koloSloupec.koloAktivitaNeprihlasena');
                        }
                    }
                }
                $tpl->parse('tymy.kontrolaVysledky.spatnaKolaTurnaje.tymSpatnaKola.koloSloupec');
            }
            $tpl->parse('tymy.kontrolaVysledky.spatnaKolaTurnaje.tymSpatnaKola');
        }
        $tpl->parse('tymy.kontrolaVysledky.spatnaKolaTurnaje');
    }

    if ($hraciNeprihlaseni) {
        $maNejakeChyby = true;
        $tpl->assign('pocetHraciNeprihlaseni', count($hraciNeprihlaseni));
        foreach ($hraciNeprihlaseni as $hrac) {
            $tpl->assign([
                'hn_nick'           => $hrac['nick'],
                'hn_jmeno'          => $hrac['jmeno'],
                'hn_tymId'          => $hrac['idTymu'],
                'hn_tymNazev'       => $hrac['nazevTymu'],
                'hn_aktivita'       => $hrac['aktivita'],
                'hn_chybiPrihlaska' => $hrac['chybiPrihlaska'],
            ]);
            $tpl->parse('tymy.kontrolaVysledky.hraciNeprihlaseni.hracNeprihlaseni');
        }
        $tpl->parse('tymy.kontrolaVysledky.hraciNeprihlaseni');
    }

    if (!$maNejakeChyby) {
        $tpl->parse('tymy.kontrolaVysledky.kontrolaOk');
    }

    $tpl->parse('tymy.kontrolaVysledky');
}
