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

// POST akce
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $akce = $_POST['akce'] ?? '';
        $kodTymu = (int)($_POST['kodTymu'] ?? 0);
        if ($akce === 'nastavVerejnost') {
            AktivitaTym::zkontrolujZeJeKapitan($kodTymu, $aktivitaId, $u->id());
            $verejny = (bool)(int)($_POST['verejny'] ?? 0);
            AktivitaTym::nastavVerejnostTymu($kodTymu, $aktivitaId, $verejny);
            $response['úspěch'] = true;
        } elseif ($akce === 'pregenerujKod') {
            AktivitaTym::zkontrolujZeJeKapitan($kodTymu, $aktivitaId, $u->id());
            $novyKod = AktivitaTym::pregenerujKodTymu($kodTymu, $aktivitaId);
            $response['úspěch'] = true;
            $response['novyKod'] = $novyKod;
        } elseif ($akce === 'odhlasClena') {
            AktivitaTym::zkontrolujZeJeKapitan($kodTymu, $aktivitaId, $u->id());
            $idClena = (int)($_POST['idClena'] ?? 0);
            if ($idClena === $u->id()) {
                throw new Chyba('Kapitán nemůže odebrat sám sebe — použij tlačítko Odhlásit');
            }
            $aktivita = Aktivita::zId($aktivitaId);
            $clen = Uzivatel::zId($idClena);
            if (!$aktivita || !$clen) {
                throw new Chyba('Aktivita nebo uživatel neexistuje');
            }
            $aktivita->odhlas($clen, $u, 'kapitán týmu');
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

// GET: info o týmu uživatele + všechny týmy
$uzivatelId = array_key_exists('uzivatelId', $_GET)
    ? (int)$_GET['uzivatelId']
    : $u->id();

$kod = AktivitaTym::vratKodTymuProUzivatele($uzivatelId, $aktivitaId);
$response['kod'] = $kod;

// čas aktivity
$aktivita = Aktivita::zId($aktivitaId);
if ($aktivita) {
    $zacatek = $aktivita->zacatek();
    $konec = $aktivita->konec();
    $response['casText'] = $zacatek && $konec
        ? $zacatek->format('G') . ':00–' . $konec->format('G') . ':00'
        : '';
}

// info o týmu uživatele
if ($kod) {
    $verejny = AktivitaTym::verejnostTymuPodleKodu($kod, $aktivitaId);
    if ($verejny !== null) {
        $response['verejny'] = $verejny;
    }
    $response['jeKapitan'] = AktivitaTym::jeKapitanem($uzivatelId, $aktivitaId);
    $idKapitana = AktivitaTym::idKapitanaTymu($kod, $aktivitaId);
    $response['clenove'] = array_map(
        fn(\Uzivatel $clen) => [
            'id'        => $clen->id(),
            'jmeno'     => $clen->jmenoNick() ?? '?',
            'jeKapitan' => $clen->id() === $idKapitana,
        ],
        AktivitaTym::clenoveTymu($kod, $aktivitaId),
    );
}

// seznam všech týmů
$vsechnyTymy = AktivitaTym::vsechnyTymy($aktivitaId);
$response['vsechnyTymy'] = array_map(fn(\Gamecon\Aktivita\TymVSeznamu $tym) => [
    'kod' => $tym->kod,
    'nazev' => $tym->nazev,
    'pocetClenu' => $tym->pocetClenu,
    'limit' => $tym->limit,
    'verejny' => $tym->verejny,
], $vsechnyTymy);

echo json_encode($response, $jsonConfig);
