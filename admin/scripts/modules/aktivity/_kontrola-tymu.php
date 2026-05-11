<?php

declare(strict_types=1);

use Gamecon\Aktivita\AktivitaTym;
use Gamecon\XTemplate\XTemplate;

/** Provede kontrolu stavu týmů a vyrenderuje výsledky do šablony. */
function renderujVysledkyKontrolyTymu(XTemplate $tpl): void
{
    $tymyBezAktivity = array_map(
        static fn(AktivitaTym $tym) => [
            'id'         => $tym->getId(),
            'nazev'      => $tym->getNazev() ?? '',
            'pocetClenu' => $tym->pocetClenu(),
        ],
        AktivitaTym::tymyBezAktivity(),
    );

    $pripraveneBezKapitana = array_map(
        static function (AktivitaTym $tym): array {
            $aktivity = $tym->dalsiAktivity();
            $aktivitaNazev = $aktivity ? reset($aktivity)->nazev() : '';
            $clenove = array_map(
                static fn(\Uzivatel $clen) => [
                    'id'    => $clen->id(),
                    'nick'  => $clen->nick(),
                    'jmeno' => $clen->jmenoNaWebu(),
                ],
                $tym->clenoveTymu(),
            );
            return [
                'id'       => $tym->getId(),
                'nazev'    => $tym->getNazev() ?? '',
                'aktivita' => $aktivitaNazev,
                'clenove'  => $clenove,
            ];
        },
        AktivitaTym::pripraveneTymyBezKapitana(),
    );

    $spatnaKola          = AktivitaTym::tymySPatnymKolemTurnaje();
    $hraciNeprihlaseni   = AktivitaTym::hraciNeprihlaseniNaAktivityTymu();
    $hraciSPatnymTymem   = AktivitaTym::hraciSPatnymTymem();

    $maNejakeChyby = false;

    if ($tymyBezAktivity) {
        $maNejakeChyby = true;
        $tpl->assign('pocetTymyBezAktivity', count($tymyBezAktivity));
        foreach ($tymyBezAktivity as $tym) {
            $tpl->assign([
                'tba_id'         => $tym['id'],
                'tba_nazev'      => $tym['nazev'],
                'tba_pocetClenu' => $tym['pocetClenu'],
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

    if ($hraciSPatnymTymem) {
        $maNejakeChyby = true;
        $tpl->assign('pocetHraciSPatnymTymem', count($hraciSPatnymTymem));
        foreach ($hraciSPatnymTymem as $hrac) {
            $tpl->assign([
                'hspt_nick'        => $hrac['nick'],
                'hspt_jmeno'       => $hrac['jmeno'],
                'hspt_aktivita'    => $hrac['aktivita'],
                'hspt_chyba'       => $hrac['chyba'],
                'hspt_idUzivatele' => $hrac['idUzivatele'],
                'hspt_idAktivity'  => $hrac['idAktivity'],
            ]);
            $tpl->parse('tymy.kontrolaVysledky.hraciSPatnymTymem.hracSPatnymTymem');
        }
        $tpl->parse('tymy.kontrolaVysledky.hraciSPatnymTymem');
    }

    if (!$maNejakeChyby) {
        $tpl->parse('tymy.kontrolaVysledky.kontrolaOk');
    }

    $tpl->parse('tymy.kontrolaVysledky');
}
