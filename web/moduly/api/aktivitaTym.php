<?php

/** @var Uzivatel $u */

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaTym;

$u = Uzivatel::zSession();
$this->bezStranky(true);
$response = [];

if (!$u) {
    return;
}

$jsonConfig = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
header('Content-type: application/json');

$aktivitaId = array_key_exists('aktivitaId', $_GET)
    ? (int)$_GET['aktivitaId']
    : -1;

// POST akce: nastavVerejnost
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $akce = $_POST['akce'] ?? '';
        $kodTymu = (int)($_POST['kodTymu'] ?? 0);
        if ($akce === 'nastavVerejnost') {
            AktivitaTym::zkontrolujZeJeKapitan($kodTymu, $aktivitaId, $u->id());
            $verejny = (bool)(int)($_POST['verejny'] ?? 0);
            AktivitaTym::nastavVerejnostTymu($kodTymu, $aktivitaId, $verejny);
            $response['úspěch'] = true;
        } else {
            $response['úspěch'] = false;
            $response['chyba'] = ['hláška' => 'Neznámá akce'];
        }
    } catch (Chyba $chyba) {
        $response['úspěch'] = false;
        $response['chyba'] = ['hláška' => $chyba->getMessage()];
    }
    echo json_encode($response, $jsonConfig);
    return;
}

// GET: info o týmu uživatele + veřejné týmy
$uzivatelId = array_key_exists('uzivatelId', $_GET)
    ? (int)$_GET['uzivatelId']
    : $u->id();

$kod = AktivitaTym::vratKodTymuProUzivatele($uzivatelId, $aktivitaId);
$response['kod'] = $kod;

// veřejnost týmu ve kterém je uživatel
if ($kod) {
    $verejny = AktivitaTym::verejnostTymuPodleKodu($kod, $aktivitaId);
    if ($verejny !== null) {
        $response['verejny'] = $verejny;
    }
}

// seznam veřejných týmů (pro nepřihlášené — aby si mohli vybrat)
if (!$kod) {
    $verejneTymy = AktivitaTym::verejneTymy($aktivitaId);
    $response['verejneTymy'] = array_map(fn(\Gamecon\Aktivita\VerejnyTym $tym) => [
        'kod' => $tym->kod,
        'nazev' => $tym->nazev,
        'pocetClenu' => $tym->pocetClenu,
        'limit' => $tym->limit,
    ], $verejneTymy);
}

echo json_encode($response, $jsonConfig);
