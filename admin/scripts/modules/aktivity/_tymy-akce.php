<?php

declare(strict_types=1);

use Gamecon\Aktivita\AktivitaTym;

/**
 * Zpracuje POST akce pro správu týmů.
 * Pokud akci zpracuje, ukončí skript (redirect nebo echo).
 */
function zpracujAkciTymu(\Uzivatel $u): void
{
    if (post('rozebratTym')) {
        $idTymu = (int)post('idTymu');
        if ($idTymu > 0) {
            AktivitaTym::najdi($idTymu)->rozebratTym();
        }
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
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
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }

    if (post('predatKapitana')) {
        $idTymu        = (int)post('idTymu');
        $novyKapitanId = (int)post('novyKapitanId');
        // TODO: AktivitaTym::najdi($idTymu)->nastavKapitana($novyKapitanId);
        echo json_encode([
            'akce'          => 'predatKapitana',
            'idTymu'        => $idTymu,
            'novyKapitanId' => $novyKapitanId,
        ]);
        exit;
    }

    if (post('predatKapitanaAutomaticky')) {
        $idTymu = (int)post('idTymu');
        // TODO: vybrat prvního člena týmu bez kapitána a nastavit ho
        echo json_encode([
            'akce'   => 'predatKapitanaAutomaticky',
            'idTymu' => $idTymu,
        ]);
        exit;
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
}
