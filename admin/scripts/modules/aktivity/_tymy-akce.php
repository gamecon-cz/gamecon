<?php

declare(strict_types=1);

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaTym;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Zpracuje POST akce pro správu týmů.
 * Pokud akci zpracuje, ukončí skript (redirect nebo echo).
 */
function zpracujAkciTymu(\Uzivatel $u, SystemoveNastaveni $systemoveNastaveni): void
{
    if (post('rozebratTym')) {
        $idTymu = (int)post('idTymu');
        if ($idTymu > 0) {
            AktivitaTym::najdi($idTymu)->rozebratTym();
        }
        reload();
    }

    if (post('zamknoutTym') || post('odemknoutTym')) {
        if (!$u->jeSefInfopultu()) {
            throw new \Chyba('Nemáte oprávnění zamykat/odemykat týmy');
        }
        $idTymu = (int)post('idTymu');
        if ($idTymu > 0) {
            $tym = AktivitaTym::najdi($idTymu);
            if (post('zamknoutTym')) {
                $tym->zamkni();
            } else {
                $tym->odemkni();
            }
        }
        reload();
    }

    if (post('predatKapitana')) {
        $idTymu        = (int)post('idTymu');
        $novyKapitanId = (int)post('novyKapitanId');
        if ($idTymu > 0 && $novyKapitanId > 0) {
            AktivitaTym::najdi($idTymu)->nastavKapitana($novyKapitanId);
        }
        reload();
    }

    if (post('predatKapitanaAutomaticky')) {
        $idTymu = (int)post('idTymu');
        if ($idTymu > 0) {
            $tym     = AktivitaTym::najdi($idTymu);
            $clenove = $tym->clenoveTymu();
            if ($clenove) {
                $tym->nastavKapitana($clenove[0]->id());
            }
        }
        reload();
    }

    if (post('prihlasitTymNaAktivitu')) {
        $idTymu     = (int)post('idTymu');
        $idAktivity = (int)post('idAktivity');
        // TODO: AktivitaTym::najdi($idTymu)->pridejNaAktivitu($idAktivity);
        echo json_encode([
            'akce'      => 'prihlasitTymNaAktivitu',
            'idTymu'    => $idTymu,
            'idAktivity' => $idAktivity,
        ]);
        exit;
    }

    if (post('odhlasitTymOdAktivity')) {
        $idTymu     = (int)post('idTymu');
        $idAktivity = (int)post('idAktivity');
        // TODO: odhlásit tým od dané aktivity kola
        echo json_encode([
            'akce'      => 'odhlasitTymOdAktivity',
            'idTymu'    => $idTymu,
            'idAktivity' => $idAktivity,
        ]);
        exit;
    }

    if (post('odhlasitHraceZAktivity')) {
        $idUzivatele = (int)post('idUzivatele');
        $idAktivity  = (int)post('idAktivity');
        if ($idUzivatele > 0 && $idAktivity > 0) {
            $hrac     = \Uzivatel::zId($idUzivatele);
            $aktivita = Aktivita::zId($idAktivity, systemoveNastaveni: $systemoveNastaveni);
            if ($hrac && $aktivita) {
                $aktivita->odhlas($hrac, $u, 'admin-tymy-kontrola', Aktivita::BEZ_POKUT);
            }
        }
        reload();
    }
}
