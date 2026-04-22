<?php

/** @var Uzivatel $u */

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaTym;

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
        } elseif ($akce === 'zalozPrazdnyTym') {
            $aktivita = Aktivita::zId($aktivitaId);
            if (!$aktivita) {
                throw new Chyba('Aktivita nenalezena');
            }
            $tym                       = AktivitaTym::zalozPrazdnyTym($u->id(), $aktivitaId);
            $response['úspěch']        = true;
            $response['kodTymu']       = $tym->getKod();
            $response['aktivityKVyberu'] = array_values(array_map(
                static fn(Aktivita $a) => [
                    'id'      => $a->id(),
                    'nazev'   => $a->nazev(),
                    'casText' => ($a->zacatek()?->format('G:i') ?? '') . '–' . ($a->konec()?->format('G:i') ?? ''),
                ],
                $aktivita->deti(),
            ));
        } elseif ($akce === 'potvrdVyberAktivit') {
            $tym = AktivitaTym::najdiPodleKodu($aktivitaId, $kodTymu);
            $tym->zkontrolujZeJeKapitan($u->id());
            $idVybranychAktivit = array_map('intval', (array)($_POST['idVybranychAktivit'] ?? []));
            foreach ($idVybranychAktivit as $idVybraneAktivity) {
                $tym->pridejNaAktivitu($idVybraneAktivity);
            }
            $aktivita = Aktivita::zId($aktivitaId);
            if (!$aktivita) {
                throw new Chyba('Aktivita nenalezena');
            }
            // todo(tym): tady musí stoprocentně dojít k přihlášení uživatele jinak není úspěch a pořád hrozí smazání týmu
            $aktivita->prihlas($u, $u, tym: $tym);
            $response['úspěch'] = true;
        } elseif ($akce === 'nastavLimit') {
            $tym   = AktivitaTym::najdiPodleKodu($aktivitaId, $kodTymu);
            $tym->zkontrolujZeJeKapitan($u->id());
            $limit = (int)($_POST['limit'] ?? 0);
            if ($limit < 1) {
                throw new Chyba('Neplatný limit');
            }
            $tym->nastavLimit($limit);
            $response['úspěch'] = true;
        } elseif ($akce === 'predejKapitana') {
            $tym             = AktivitaTym::najdiPodleKodu($aktivitaId, $kodTymu);
            $tym->zkontrolujZeJeKapitan($u->id());
            $idNovehoKapitana = (int)($_POST['idNovehoKapitana'] ?? 0);
            if (!$idNovehoKapitana) {
                throw new Chyba('Chybí ID nového kapitána');
            }
            if ($idNovehoKapitana === $u->id()) {
                throw new Chyba('Nemůžeš předat kapitána sám sobě');
            }
            $tym->nastavKapitana($idNovehoKapitana);
            $response['úspěch'] = true;
        } elseif ($akce === 'zamkni') {
            $tym             = AktivitaTym::najdiPodleKodu($aktivitaId, $kodTymu);
            $tym->zkontrolujZeJeKapitan($u->id());
            $tym->zkontrolujZeJdeZamknout();
            $tym->zamkni();
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

// čas aktivity + příznak předpřípravy
$aktivita = Aktivita::zId($aktivitaId);
if ($aktivita) {
    $response['jeTrebaPredpripravit'] = $aktivita->tymova() && $aktivita->jeTrebaPredpripravitTym();
    $zacatek           = $aktivita->zacatek();
    $konec             = $aktivita->konec();
    $response['casText'] = $zacatek && $konec
        ? $zacatek->format('G') . ':00–' . $konec->format('G') . ':00'
        : '';
}

$tym = AktivitaTym::najdiPodleUzivateleAktivity($uzivatelId, $aktivitaId);

// info o týmu uživatele
if ($tym) {
    $response['kod'] = $tym->getKod();
    $response['verejny']    = $tym->jeVerejny();
    $response['jeKapitan']  = $tym->jeKapitanem($uzivatelId);
    $response['casZalozeniMs'] = $tym->casZalozeniMs();
    $response['limitTymu']  = $tym->limitTymu();
    $response['zamceny'] = $tym->jeZamceny();
    $response['minKapacita'] = $aktivita?->tymMinKapacita();
    $response['maxKapacita'] = $aktivita?->tymMaxKapacita();
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
$vsechnyTymy       = AktivitaTym::vsechnyTymyAktivity($aktivitaId);
$response['vsechnyTymy'] = array_map(fn(AktivitaTym $t) => [
    'id'         => $t->getId(),
    'nazev'      => $t->getNazev(),
    'pocetClenu' => $t->pocetClenu(),
    'limit'      => $t->limitTymu(),
    'verejny'    => $t->jeVerejny(),
], $vsechnyTymy);

echo json_encode($response, $jsonConfig);
