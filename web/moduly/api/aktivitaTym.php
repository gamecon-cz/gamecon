<?php

/** @var Uzivatel $u */

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\AktivitaTym;
use Gamecon\Role\Role;

$response = [];

if (!$u) {
    return;
}

if(!isset($uPracovni)) {
    $uPracovni = $u;
}

$jsonConfig = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
header('Content-type: application/json');

$aktivitaId = array_key_exists('aktivitaId', $_GET)
    ? (int)$_GET['aktivitaId']
    : (int)($_POST['aktivitaId'] ?? -1);

// POST akce
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $akce   = $_POST['akce'] ?? '';
        $idTymu = (int)($_POST['idTymu'] ?? 0);
        $tym = null;
        if ($idTymu) {
            $tym = AktivitaTym::najdi($idTymu);
            $tym->zkontrolujZeJeKapitan($uPracovni->id());
            $tym->zkontrolujZeNeniZamceny();
        }
        $response['úspěch'] = true;

        if ($akce === 'nastavVerejnost') {
            $verejny = (bool)(int)($_POST['verejny'] ?? 0);
            $tym->nastavVerejnost($verejny);
        } elseif ($akce === 'pregenerujKod') {
            $response['novyKod'] = $tym->pregenerujKod();
        } elseif ($akce === 'odhlasClena') {
            $idClena = (int)($_POST['idClena'] ?? 0);
            if ($idClena === $uPracovni->id()) {
                throw new Chyba('Kapitán nemůže odebrat sám sebe — použij tlačítko Odhlásit');
            }
            $aktivita = Aktivita::zId($aktivitaId);
            $clen     = Uzivatel::zId($idClena);
            if (!$aktivita || !$clen) {
                throw new Chyba('Aktivita nebo uživatel neexistuje');
            }
            $aktivita->odhlas($clen, $uPracovni, 'kapitán týmu');
        } elseif ($akce === 'zalozPrazdnyTym') {
            $aktivita = Aktivita::zId($aktivitaId);
            if (!$aktivita) {
                throw new Chyba('Aktivita nenalezena');
            }
            $aktivita->prihlas($uPracovni, $u);
        } elseif ($akce === 'prihlasKapitana') {
            $aktivita = Aktivita::zId($aktivitaId);
            if (!$aktivita) {
                throw new Chyba('Aktivita nenalezena');
            }
            $aktivita->prihlas($uPracovni, $u, tym: $tym);
        } elseif ($akce === 'potvrdVyberAktivit') {
            $idVybranychAktivit = array_map('intval', (array)($_POST['idVybranychAktivit'] ?? []));
            foreach ($idVybranychAktivit as $idVybraneAktivity) {
                $tym->pridejNaAktivitu($idVybraneAktivity);
            }
            $aktivita = Aktivita::zId($aktivitaId);
            if (!$aktivita) {
                throw new Chyba('Aktivita nenalezena');
            }
            // todo(tym): tady musí stoprocentně dojít k přihlášení uživatele jinak není úspěch a pořád hrozí smazání týmu
            $aktivita->prihlas($uPracovni, $u, tym: $tym);
        } elseif ($akce === 'nastavLimit') {
            $limit = (int)($_POST['limit'] ?? 0);
            if ($limit < 1) {
                throw new Chyba('Neplatný limit');
            }
            $tym->nastavLimit($limit);
        } elseif ($akce === 'predejKapitana') {
            $idNovehoKapitana = (int)($_POST['idNovehoKapitana'] ?? 0);
            if (!$idNovehoKapitana) {
                throw new Chyba('Chybí ID nového kapitána');
            }
            if ($idNovehoKapitana === $uPracovni->id()) {
                throw new Chyba('Nemůžeš předat kapitána sám sobě');
            }
            $tym->nastavKapitana($idNovehoKapitana);
        } elseif ($akce === 'zamkni') {
            $tym->zkontrolujZeJdeZamknout();
            $tym->zamkni();
        } elseif ($akce === 'odemkni') {
            if (!$u->maRoli(Role::SEF_INFOPULTU)) {
                throw new Chyba('Nemáš oprávnění odemknout tým');
            }
            $tym->odemkni();
        } else {
            throw new Chyba('Neznámá akce');
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
    : $uPracovni->id();

// čas aktivity + příznak předpřípravy
$aktivita = Aktivita::zId($aktivitaId);
if ($aktivita) {
    $response['jeTrebaPredpripravit'] = $aktivita->turnaj()?->jeTrebaVybratAktivityTurnaje() ?? false;
    $zacatek           = $aktivita->zacatek();
    $konec             = $aktivita->konec();
    $response['casText'] = $zacatek && $konec
        ? $zacatek->format('G') . ':00–' . $konec->format('G') . ':00'
        : '';
}

$tym = AktivitaTym::najdiPodleUzivateleAktivityNeboKapitana($uzivatelId, $aktivitaId);

// info o týmu uživatele
if ($tym) {
    $response['id']  = $tym->getId();
    $response['kod'] = $tym->getKod();
    $response['verejny']    = $tym->jeVerejny();
    $response['jeKapitan']  = $tym->jeKapitanem($uzivatelId);
    $response['casZalozeniMs'] = $tym->casZalozeniMs();
    $response['casExpiraceMs'] = $tym->casExpiraceMs();
    $response['limitTymu']  = $tym->limitTymu();
    $response['zamceny'] = $tym->jeZamceny();
    $response['jeSmazatPoExpiraci'] = $tym->jeSmazatPoExpiraci();
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
    $response['aktivityTymuId'] = array_map(fn(Aktivita $a) => $a->id(),$tym->dalsiAktivity());
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
