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
        $akce    = $_POST['akce'] ?? '';
        $kodTymu = (int)($_POST['kodTymu'] ?? 0);
        if ($akce === 'nastavVerejnost') {
            $tym = AktivitaTym::najdiPodleKodu($aktivitaId, $kodTymu);
            $tym->zkontrolujZeJeKapitan($u->id());
            $tym->nastavVerejnost((bool)(int)($_POST['verejny'] ?? 0));
            $response['úspěch'] = true;
        } elseif ($akce === 'pregenerujKod') {
            $tym = AktivitaTym::najdiPodleKodu($aktivitaId, $kodTymu);
            $tym->zkontrolujZeJeKapitan($u->id());
            $response['úspěch'] = true;
            $response['novyKod'] = $tym->pregenerujKod();
        } elseif ($akce === 'odhlasClena') {
            $tym     = AktivitaTym::najdiPodleKodu($aktivitaId, $kodTymu);
            $idClena = (int)($_POST['idClena'] ?? 0);
            $tym->zkontrolujZeJeKapitan($u->id());
            if ($idClena === $u->id()) {
                throw new Chyba('Kapitán nemůže odebrat sám sebe — použij tlačítko Odhlásit');
            }
            $aktivita = Aktivita::zId($aktivitaId);
            $clen     = Uzivatel::zId($idClena);
            if (!$aktivita || !$clen) {
                throw new Chyba('Aktivita nebo uživatel neexistuje');
            }
            $aktivita->odhlas($clen, $u, 'kapitán týmu');
            $response['úspěch'] = true;
        } else {
            $response['úspěch'] = false;
            $response['chyba']  = ['hláška' => 'Neznámá akce'];
        }
    } catch (Chyba $chyba) {
        $response['úspěch'] = false;
        $response['chyba']  = ['hláška' => $chyba->getMessage()];
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
    $zacatek           = $aktivita->zacatek();
    $konec             = $aktivita->konec();
    $response['casText'] = $zacatek && $konec
        ? $zacatek->format('G') . ':00–' . $konec->format('G') . ':00'
        : '';
}

// info o týmu uživatele
if ($kod) {
    $tym               = AktivitaTym::najdiPodleKodu($aktivitaId, $kod);
    $response['verejny']  = $tym->isVerejny();
    $response['jeKapitan'] = $tym->jeKapitanem($uzivatelId);
    $response['clenove'] = array_map(
        fn(\Uzivatel $clen) => [
            'id'        => $clen->id(),
            'jmeno'     => $clen->jmenoNick() ?? '?',
            'jeKapitan' => $clen->id() === $tym->idKapitana(),
        ],
        $tym->clenoveTymu(),
    );
}

// seznam všech týmů
$vsechnyTymy       = AktivitaTym::vsechnyTymy($aktivitaId);
$response['vsechnyTymy'] = array_map(fn(\Gamecon\Aktivita\TymVSeznamu $t) => [
    'kod'       => $t->kod,
    'nazev'     => $t->nazev,
    'pocetClenu' => $t->pocetClenu,
    'limit'     => $t->limit,
    'verejny'   => $t->verejny,
], $vsechnyTymy);

echo json_encode($response, $jsonConfig);
