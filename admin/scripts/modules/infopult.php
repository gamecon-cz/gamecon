<?php

/**
 * Úvodní stránka sloužící pro infopult a další účely. Zajišťuje registraci na
 * DrD, Trojboj, Gamecon, Placení aj.
 *
 * nazev: Infopult
 * pravo: 100
 */

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 */

$nastaveni = ['ubytovaniBezZamku' => true, 'jidloBezZamku' => true];
$shop = $uPracovni ? new Shop($uPracovni, $nastaveni) : null;

if (!empty($_POST['datMaterialy']) && $uPracovni && $uPracovni->gcPrihlasen()) {
    $uPracovni->dejZidli(ZIDLE_PRITOMEN, $u->id());
    back();
}

if (post('platba') && $uPracovni) {
    if (!$uPracovni->gcPrihlasen()) {
        varovani('Platba připsána uživateli, který není přihlášen na Gamecon', false);
    }
    try {
        $uPracovni->finance()->pripis(post('platba'), $u, post('poznamka'), post('idPohybu'));
    } catch (DbDuplicateEntryException $dbDuplicateEntryException) {
        if (post('idPohybu') && FioPlatba::existujePodleFioId(post('idPohybu'))) {
            chyba(sprintf('Tato platba s Fio ID %d již existuje', post('idPohybu')), false);
        } else {
            chyba(
                sprintf("Platbu se nepodařilo uložit. Duplicitní záznam: '%s'", $dbDuplicateEntryException->getMessage()),
                false
            );
        }
    }
    back();
}

if (!empty($_POST['gcPrihlas']) && $uPracovni && !$uPracovni->gcPrihlasen()) {
    $uPracovni->gcPrihlas();
    back();
}

if (!empty($_POST['rychloreg'])) {
    $tab = $_POST['rychloreg'];
    if (empty($tab['login_uzivatele'])) {
        $tab['login_uzivatele'] = $tab['email1_uzivatele'];
    }
    $tab['nechce_maily'] = isset($tab['nechce_maily']) ? dbNow() : null;
    try {
        $nid = Uzivatel::rychloreg($tab, [
            'informovat' => post('informovat'),
        ]);
    } catch (DuplicitniEmailException $e) {
        throw new Chyba('Uživatel s zadaným e-mailem už v databázi existuje');
    } catch (DuplicitniLoginException $e) {
        throw new Chyba('Uživatel s loginem odpovídajícím zadanému e-mailu už v databázi existuje');
    }
    if ($nid) {
        if ($uPracovni) {
            Uzivatel::odhlasKlic('uzivatel_pracovni');
        }
        $_SESSION["id_uzivatele"] = $nid;
        $uPracovni = Uzivatel::prihlasId($nid, 'uzivatel_pracovni');
        if (!empty($_POST['vcetnePrihlaseni'])) {
            $uPracovni->gcPrihlas();
        }
        back();
    }
}

if (!empty($_POST['telefon']) && $uPracovni) {
    dbQueryS('UPDATE uzivatele_hodnoty SET telefon_uzivatele=$0 WHERE id_uzivatele=' . $uPracovni->id(), [$_POST['telefon']]);
    $uPracovni->otoc();
    back();
}

if (!empty($_POST['prodej'])) {
    $prodej = $_POST['prodej'];
    unset($prodej['odeslano']);
    if ($uPracovni) {
        $prodej['id_uzivatele'] = $uPracovni->id();
    }
    if (!$prodej['id_uzivatele']) {
        $prodej['id_uzivatele'] = 0;
    }
    for ($kusu = $prodej['kusu'] ?? 1, $pocet = 1; $pocet <= $kusu; $pocet++) {
        dbQuery('INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum)
    VALUES (' . $prodej['id_uzivatele'] . ',' . $prodej['id_predmetu'] . ',' . ROK . ',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu=' . $prodej['id_predmetu'] . '),NOW())');
    }
    $idPredmetu = (int)$prodej['id_predmetu'];
    $nazevPredmetu = dbOneCol(
        <<<SQL
        SELECT nazev FROM shop_predmety
        WHERE id_predmetu = $idPredmetu
        SQL
    );
    $yu = '';
    if ($kusu >= 5) {
        $yu = 'ů';
    } elseif ($kusu > 1) {
        $yu = 'y';
    }
    oznameni("Prodáno $kusu kus$yu $nazevPredmetu");
    back();
}

if (!empty($_POST['gcOdhlas']) && $uPracovni && !$uPracovni->gcPritomen()) {
    $uPracovni->gcOdhlas();
    back();
}

if (post('gcOdjed')) {
    $uPracovni->gcOdjed();
    back();
}

// TODO: mělo by být obsaženo v modelové třídě
/**
 * @param mixin $udaje 
 * @param int $uPracovniId
 * @param \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 */
function updateUzivatelHodnoty($udaje, $uPracovniId, $vyjimkovac)
{
    try {
        dbUpdate('uzivatele_hodnoty', $udaje, ['id_uzivatele' => $uPracovniId]);
    } catch (Exception $e) {
        $vyjimkovac->zaloguj($e);
        chyba('Došlo k neočekávané chybě.');
    }
}

/* Editace v kartě Pŕehled */
if ($uPracovni && post('prehledUprava')) {
    $udaje = post('udaje');

    foreach ([
        'potvrzeni_zakonneho_zastupce',
        'potvrzeni_proti_covid19_overeno_kdy',
    ] as &$pole) {
        if (isset($udaje[$pole])) {
            // pokud je hodnota "" tak to znamená že nedošlo ke změně
            if ($udaje[$pole] == "")
                unset($udaje[$pole]);
            else
                $udaje[$pole] = date('Y-m-d');
        } else {
            $udaje[$pole] = null;
        }
    }

    // TODO(SECURITY): nebezpečné krmit data do databáze tímhle způsobem Každý si vytvořit do html formuláře input který se pak také propíŠe do DB
    updateUzivatelHodnoty($udaje, $uPracovni->id(), $vyjimkovac);
    back();
}

if (post('zpracujUbytovani')) {
    $shop->zpracujUbytovani();
    oznameni('Ubytování uloženo');
}

if (post('pridelitPokoj') && $uPracovni) {
    $pokojPost = post('pokoj');
    Pokoj::ubytujNaCislo($uPracovni, $pokojPost);
    oznameni('Pokoj přidělen', false);
    if ($_SERVER['HTTP_REFERER']) {
        parse_str($_SERVER['QUERY_STRING'], $query_string);
        $query_string['pokoj'] = $pokojPost;
        unset($query_string['req']);
        $query_string = http_build_query($query_string);
        $targetAddress = explode("?", $_SERVER['HTTP_REFERER'])[0];
        header('Location: ' . $targetAddress . "?" . $query_string, true, 303);
    } else
        back();
}


if (post('zmenitUdaj') && $uPracovni) {
    $udaje = post('udaj');
    if ($udaje['op'] ?? null) {
        $uPracovni->cisloOp($udaje['op']);
        unset($udaje['op']);
    }
    if (empty($udaje['potvrzeni_zakonneho_zastupce'])) {
        // datum potvrzeni je odskrnute (prohlizec nezaskrtly chceckbox neposle), musime ho smazat
        $udaje['potvrzeni_zakonneho_zastupce'] = null;
    }
    if (empty($udaje['potvrzeni_proti_covid19_overeno_kdy'])) {
        // datum potvrzeni je odskrnute (prohlizec nezaskrtly chceckbox neposle), musime ho smazat
        $udaje['potvrzeni_proti_covid19_overeno_kdy'] = null;
    }
    try {
        dbUpdate('uzivatele_hodnoty', $udaje, ['id_uzivatele' => $uPracovni->id()]);
    } catch (DbDuplicateEntryException $e) {
        if ($e->key() === 'email1_uzivatele') {
            chyba('Uživatel se stejným e-mailem již existuje.');
        } else if ($e->key() === 'login_uzivatele') {
            chyba('Uživatel se stejným e-mailem již existuje.');
        } else {
            chyba('Uživatel se stejným údajem již existuje.');
        }
    } catch (Exception $e) {
        $vyjimkovac->zaloguj($e);
        chyba('Došlo k neočekávané chybě.');
    }

    $uPracovni->otoc();
    back();
}

// TODO: přesunout do nějakého utility souboru
#region utility
$ok = '<img src="files/design/ok-s.png" style="margin-bottom:-2px">';
$warn = '<img src="files/design/warning-s.png" style="margin-bottom:-2px">';
$err = '<img src="files/design/error-s.png" style="margin-bottom:-2px">';

/**
 * @param string $cislo
 */
function formatujTelCislo($cislo)
{
    $bezmezer = str_replace(" ", "", $cislo);
    if ($bezmezer == "")
        return "";
    $predvolbaKonec = max(strlen($bezmezer) - 9, 0);
    $formatovane = substr($bezmezer, 0, $predvolbaKonec) . " " . substr($bezmezer, $predvolbaKonec, 3) . " " . substr($bezmezer, $predvolbaKonec + 3, 3) . " " . substr($bezmezer, $predvolbaKonec + 6, 3);
    return $formatovane;
}
#endregion utility

$x = new XTemplate('infopult.xtpl');
$x->assign('prihlasBtnAttr', "disabled");
$x->assign('datMaterialyBtnAttr', "disabled");
$x->assign('gcOdjedBtnAttr', "disabled");
$x->assign('odhlasBtnAttr', "disabled");
$x->assign('ok', $ok);
$x->assign('err', $err);

// ubytovani
$pokojVypis = Pokoj::zCisla(get('pokoj'));
$ubytovaniVypis = $pokojVypis ? $pokojVypis->ubytovani() : [];

/**
 * @param \Uzivatel[] $spolubydlici
 */
function spolubydliciTisk($spolubydlici)
{
    return array_uprint($spolubydlici, static function (Uzivatel $e) {
        return "<li> {$e->jmenoNick()} ({$e->id()}) {$e->telefon()} </li>";
    });
}

if ($uPracovni) {
    if (!$uPracovni->gcPrihlasen()) {
        $x->assign([
            'a' => $uPracovni->koncovkaDlePohlavi(),
            'ka' => $uPracovni->koncovkaDlePohlavi() ? 'ka' : '',
            'rok' => ROK,
        ]);
        if (REG_GC) {
            $x->assign('prihlasBtnAttr', "");
        } else {
            $x->parse('uvod.neprihlasen.nelze');
        }
        $x->parse('uvod.neprihlasen');
    }
    /** @var \Uzivatel $up */
    $up = $uPracovni;
    $a = $up->koncovkaDlePohlavi();
    $pokoj = Pokoj::zUzivatele($up);
    $spolubydlici = $pokoj
        ? $pokoj->ubytovani()
        : [];
    $x->assign([
        'a' => $up->koncovkaDlePohlavi(),
        'stav' => ($up->finance()->stav() < 0 ? $err : $ok) . ' ' . $up->finance()->stavHr(),
        'stavStyle' => ($up->finance()->stav() < 0 ? '"color: #f22; font-weight: bolder;"' : ""),
        'prehled' => $up->finance()->prehledHtml(),
        'slevyAktivity' => ($akt = $up->finance()->slevyAktivity()) ?
            '<li>' . implode('<li>', $akt) :
            '(žádné)',
        'slevyVse' => ($vse = $up->finance()->slevyVse()) ?
            '<li>' . implode('<li>', $vse) :
            '(žádné)',
        'id' => $up->id(),
        'pokoj' => $pokoj ? $pokoj->cislo() : '(nepřidělen)',
        'spolubydlici' => spolubydliciTisk($spolubydlici),
        'aa' => $u->koncovkaDlePohlavi(),
        'org' => $u->jmenoNick(),
        'shop' => $up->dejShop(),
        'poznamka' => $up->poznamka(),
        'up' => $up,

        'pokojVypis' => $pokoj ? $pokoj->cislo() : "",
        'ubytovani' => spolubydliciTisk($spolubydlici),
    ]);

    if (get('pokoj')) {
        $x->assign('pokojVypis', get('pokoj'));
        if ($pokojVypis) {
            $x->assign('ubytovani', array_uprint($ubytovaniVypis, function ($e) {
                $ne = $e->gcPritomen() ? '' : 'ne';
                $color = $ne ? '#f00' : '#0a0';
                $a = $e->koncA();
                return $e->jmenoNick() . " (<span style=\"color:$color\">{$ne}dorazil$a</span>)";
            }, '<br>'));
        } else
            throw new Chyba('pokoj ' . get('pokoj') . ' neexistuje nebo je prázdný');
    }


    if ($up->finance()->stav() < 0 && !$up->gcPritomen()) {
        $x->parse('uvod.upoMaterialy');
    }
    if (!$up->gcPrihlasen()) {
    } elseif (!$up->gcPritomen()) {
        $x->assign('datMaterialyBtnAttr', "");
    } elseif (!$up->gcOdjel()) {
        $x->assign('gcOdjedBtnAttr', "");
    } else {
    }
    if ($up->gcPrihlasen() && !$up->gcPritomen()) {
        // $x->parse('uvod.gcOdhlas');
    }
    $r = dbOneLine('SELECT datum_narozeni, potvrzeni_zakonneho_zastupce FROM uzivatele_hodnoty WHERE id_uzivatele = ' . $uPracovni->id());
    $datumNarozeni = new DateTimeImmutable($r['datum_narozeni']);
    $potvrzeniOd = $r['potvrzeni_zakonneho_zastupce']
        ? new DateTimeImmutable($r['potvrzeni_zakonneho_zastupce'])
        : null;
    $potrebujePotvrzeniKvuliVeku = potrebujePotvrzeni($datumNarozeni);
    $mameLetosniPotvrzeniKvuliVeku = $potvrzeniOd && $potvrzeniOd->format('y') === date('y');

    if (!$potrebujePotvrzeniKvuliVeku) {
        $x->assign("potvrzeniAttr", "checked disabled");
        $x->assign("potvrzeniText", $ok . " nepotřebuje potvrzení od rodičů");
    } else if ($mameLetosniPotvrzeniKvuliVeku) {
        $x->assign("potvrzeniAttr", "checked value=\"\"");
        $x->assign("potvrzeniText", $ok . " má potvrzení od rodičů");
    } else {
        $x->assign("potvrzeniText", $err . " chybí potvrzení od rodičů!");
    }

    if (VYZADOVANO_COVID_POTVRZENI) {
        $mameNahranyLetosniDokladProtiCovidu = $up->maNahranyDokladProtiCoviduProRok((int)date('Y'));
        $mameOverenePotvrzeniProtiCoviduProRok = $up->maOverenePotvrzeniProtiCoviduProRok((int)date('Y'));
        if (!$mameNahranyLetosniDokladProtiCovidu && !$mameOverenePotvrzeniProtiCoviduProRok) {
            /* muze byt overeno rucne bez nahraneho dokladu */
            $x->assign("covidPotvrzeniText", $err . " požádej o doplnění");
        } elseif (!$mameNahranyLetosniDokladProtiCovidu) {
            /* potvrzeno rucne na infopultu, bez nahraneho dokladu */
            $x->assign("covidPotvrzeniAttr", "checked value=\"\"");
            $x->assign("covidPotvrzeniText", $ok . " ověřeno bez dokladu");
        } else {
            $datumNahraniPotvrzeniProtiCovid = (new DateTimeCz($up->potvrzeniProtiCoviduPridanoKdy()->format(DATE_ATOM)))->relativni();
            $x->assign('covidPotvrzeniOdkazAttr', "href=\n" . $up->urlNaPotvrzeniProtiCoviduProAdmin() . "\"");
            if ($mameOverenePotvrzeniProtiCoviduProRok) {
                $x->assign("covidPotvrzeniAttr", "checked value=\"\"");
                $x->assign("covidPotvrzeniText", $ok . " ověřeno dokladem $datumNahraniPotvrzeniProtiCovid");
            } else {
                $x->assign("covidPotvrzeniText", $warn . " neověřený doklad $datumNahraniPotvrzeniProtiCovid");
            }
        }
        $x->parse('uvod.uzivatel.covidSekce');
    }

    $x->assign("telefon", formatujTelCislo($up->telefon()));

    if ($up->gcPrihlasen()) {
        $x->parse('uvod.uzivatel.ubytovani');
    }

    if (GC_BEZI) {
        $zpravyProPotvrzeniZruseniPrace = [];
        if (!$up->gcPritomen()) {
            $zpravyProPotvrzeniZruseniPrace[] = 'nedostal materiály';
        }
        if ($up->finance()->stav() < 0) {
            $zpravyProPotvrzeniZruseniPrace[] = 'má záporný zůstatek';
        }
        if ($potrebujePotvrzeniKvuliVeku && !$mameLetosniPotvrzeniKvuliVeku) {
            $zpravyProPotvrzeniZruseniPrace[] = 'nemá potvrzení od rodičů';
        }
        foreach ($zpravyProPotvrzeniZruseniPrace as $zpravaProPotvrzeniZruseniPrace) {
            $x->assign([
                'zpravaProPotvrzeniZruseniPrace' => "Uživatel {$zpravaProPotvrzeniZruseniPrace}. Přesto ukončit práci s uživatelem?",
            ]);
            $x->parse('uvod.potvrditZruseniPrace');
        }
    }

    if ($u && $u->isSuperAdmin()) {
        $x->parse('uvod.uzivatel.idFioPohybu');
    }

    $x->parse('uvod.uzivatel');
} else {
    $x->parse('uvod.neUzivatel');
}

// načtení předmětů a form s rychloprodejem předmětů, fixme
$o = dbQuery('
  SELECT
    CONCAT(nazev," ",model_rok) as nazev,
    kusu_vyrobeno-count(n.id_predmetu) as zbyva,
    p.id_predmetu,
    ROUND(p.cena_aktualni) as cena
  FROM shop_predmety p
  LEFT JOIN shop_nakupy n ON(n.id_predmetu=p.id_predmetu)
  WHERE p.stav > 0
  GROUP BY p.id_predmetu
  ORDER BY model_rok DESC, nazev');
$moznosti = '<option value="0">(vyber)</option>';
while ($r = mysqli_fetch_assoc($o)) {
    $zbyva = $r['zbyva'] === null ? '&infin;' : $r['zbyva'];
    $moznosti .= '<option value="' . $r['id_predmetu'] . '"' . ($r['zbyva'] > 0 || $r['zbyva'] === null ? '' : ' disabled') . '>' . $r['nazev'] . ' (' . $zbyva . ') ' . $r['cena'] . '&thinsp;Kč</option>';
}
$x->assign('predmety', $moznosti);


// rychloregistrace
if (!$uPracovni) { // nechceme zobrazovat rychloregistraci (zakladani uctu), kdyz mame vybraneho uzivatele pro praci
    $x->parse('uvod.rychloregistrace');
    if (REG_GC) {
        $x->parse('uvod.rychloregistrace.prihlasitNaGc');
    }
}

$x->parse('uvod');
$x->out('uvod');
