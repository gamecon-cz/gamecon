<?php

/**
 * Úvodní stránka sloužící pro infopult a další účely. Zajišťuje registraci na
 * DrD, Trojboj, Gamecon, Placení aj.
 *
 * nazev: Úvod
 * pravo: 100
 */

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;

/** @var Uzivatel|null|void $uPracovni */

if (!empty($_POST['datMaterialy']) && $uPracovni && $uPracovni->gcPrihlasen()) {
    $uPracovni->dejZidli(Z_PRITOMEN);
    back();
}

if (post('platba') && $uPracovni && $uPracovni->gcPrihlasen()) {
    $uPracovni->finance()->pripis(post('platba'), $u, post('poznamka'));
    back();
}

if (!empty($_POST['gcPrihlas']) && $uPracovni && !$uPracovni->gcPrihlasen()) {
    $uPracovni->gcPrihlas();
    back();
}

if (!empty($_POST['rychloreg'])) {
    $tab = $_POST['rychloreg'];
    if (empty($tab['login_uzivatele'])) $tab['login_uzivatele'] = $tab['email1_uzivatele'];
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
    if ($uPracovni)
        $prodej['id_uzivatele'] = $uPracovni->id();
    if (!$prodej['id_uzivatele'])
        $prodej['id_uzivatele'] = 0;
    dbQuery('INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum)
    VALUES (' . $prodej['id_uzivatele'] . ',' . $prodej['id_predmetu'] . ',' . ROK . ',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu=' . $prodej['id_predmetu'] . '),NOW())');
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

if (post('poznamkaNastav')) {
    $uPracovni->poznamka(post('poznamka'));
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
        chyba('Uživatel se stejnou přezdívkou již existuje.');
    }

    $uPracovni->otoc();
    back();
}

$x = new XTemplate('uvod.xtpl');
if ($uPracovni && $uPracovni->gcPrihlasen()) {
    $up = $uPracovni;
    $x->assign('ok', $ok = '<img src="files/design/ok-s.png" style="margin-bottom:-2px">');
    $x->assign('err', $err = '<img src="files/design/error-s.png" style="margin-bottom:-2px">');
    $a = $up->koncovkaDlePohlavi();
    $pokoj = Pokoj::zUzivatele($up);
    $spolubydlici = $pokoj
        ? $pokoj->ubytovani()
        : [];
    $x->assign([
        'a' => $up->koncovkaDlePohlavi(),
        'stav' => ($up->finance()->stav() < 0 ? $err : $ok) . ' ' . $up->finance()->stavHr(),
        'prehled' => $up->finance()->prehledHtml(),
        'slevyAktivity' => ($akt = $up->finance()->slevyAktivity()) ?
            '<li>' . implode('<li>', $akt) :
            '(žádné)',
        'slevyVse' => ($vse = $up->finance()->slevyVse()) ?
            '<li>' . implode('<li>', $vse) :
            '(žádné)',
        'id' => $up->id(),
        'pokoj' => $pokoj ? $pokoj->cislo() : '(nepřidělen)',
        'spolubydlici' => array_uprint($spolubydlici, static function (Uzivatel $e) {
            return "<li> {$e->jmenoNick()} ({$e->id()}) {$e->telefon()} </li>";
        }),
        'ubytovani' => $up->dejShop()->dejPopisUbytovani(),
        'aa' => $u->koncovkaDlePohlavi(),
        'org' => $u->jmenoNick(),
        'poznamka' => $up->poznamkaHtml(),
        'up' => $up,
    ]);
    if ($up->finance()->stav() < 0 && !$up->gcPritomen()) {
        $x->parse('uvod.uzivatel.nepritomen.upoMaterialy');
    }
    if (!$up->gcPritomen()) {
        $x->parse('uvod.uzivatel.nepritomen');
    } elseif (!$up->gcOdjel()) {
        $x->parse('uvod.uzivatel.pritomen');
    } else {
        $x->parse('uvod.uzivatel.odjel');
    }
    if (!$up->gcPritomen()) {
        $x->parse('uvod.uzivatel.gcOdhlas');
    }
    $r = dbOneLine('SELECT datum_narozeni, potvrzeni_zakonneho_zastupce FROM uzivatele_hodnoty WHERE id_uzivatele = ' . $uPracovni->id());
    $datumNarozeni = new DateTimeImmutable($r['datum_narozeni']);
    $potvrzeniOd = $r['potvrzeni_zakonneho_zastupce']
        ? new DateTimeImmutable($r['potvrzeni_zakonneho_zastupce'])
        : null;
    $potrebujePotvrzeniKvuliVeku = potrebujePotvrzeni($datumNarozeni);
    $mameLetosniPotvrzeniKvuliVeku = $potvrzeniOd && $potvrzeniOd->format('y') === date('y');
    if ($potrebujePotvrzeniKvuliVeku && !$mameLetosniPotvrzeniKvuliVeku) {
        $x->parse('uvod.uzivatel.chybiPotvrzeni');
    }
    $mameNahranyLetosniDokladProtiCovidu = $up->maNahranyDokladProtiCoviduProRok((int)date('Y'));
    $mameOverenePotvrzeniProtiCoviduProRok = $up->maOverenePotvrzeniProtiCoviduProRok((int)date('Y'));
    if (!$mameNahranyLetosniDokladProtiCovidu && !$mameOverenePotvrzeniProtiCoviduProRok /* muze byt overeno rucne bez nahraneho dokladu */) {
        $x->parse('uvod.uzivatel.chybiPotvrzeniProtiCovid');
    } elseif (!$mameNahranyLetosniDokladProtiCovidu) { // potvrzeno rucne na infopultu, bez nahraneho dokladu
        $x->parse('uvod.uzivatel.overenoPotvrzeniProtiCovidIkona');
        $x->parse('uvod.uzivatel.overenoPredlozenePotvrzeniProtiCovid');
    } else {
        $x->assign('urlNaPotvrzeniProtiCovid', $up->urlNaPotvrzeniProtiCoviduProAdmin());
        $x->assign(
            'datumNahraniPotvrzeniProtiCovid',
            (new DateTimeCz($up->potvrzeniProtiCoviduPridanoKdy()->format(DATE_ATOM)))->relativni()
        );
        $x->parse('uvod.uzivatel.potvrzeniProtiCovid');
        if ($mameOverenePotvrzeniProtiCoviduProRok) {
            $x->parse('uvod.uzivatel.overenoPotvrzeniProtiCovidIkona');
        } else {
            $x->parse('uvod.uzivatel.overitPotvrzeniProtiCovid');
        }
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
    $x->parse('uvod.uzivatel');
    $x->parse('uvod.slevy');
    $x->parse('uvod.objednavky');
} else if ($uPracovni && !$uPracovni->gcPrihlasen()) {// kvůli zkratovému vyhodnocení a nevolání metody na non-object
    $x->assign([
        'a' => $uPracovni->koncovkaDlePohlavi(),
        'ka' => $uPracovni->koncovkaDlePohlavi() ? 'ka' : '',
        'rok' => ROK,
    ]);
    if (REG_GC) {
        $x->parse('uvod.neprihlasen.prihlasit');
    } else {
        $x->parse('uvod.neprihlasen.nelze');
    }
    $x->parse('uvod.neprihlasen');
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

// form s osobními údaji
if ($uPracovni) {
    $udaje = [
        'login_uzivatele' => 'Přezdívka',
        'jmeno_uzivatele' => 'Jméno',
        'prijmeni_uzivatele' => 'Příjmení',
        'pohlavi' => 'Pohlaví',
        'ulice_a_cp_uzivatele' => 'Ulice',
        'mesto_uzivatele' => 'Město',
        'psc_uzivatele' => 'PSČ',
        'telefon_uzivatele' => 'Telefon',
        'datum_narozeni' => 'Narozen' . $uPracovni->koncA(),
        'email1_uzivatele' => 'E-mail',
        'poznamka' => 'Poznámka',
        // 'op'                    =>          'Číslo OP',
        'potvrzeni_zakonneho_zastupce' => 'Potvrzení',
        'potvrzeni_proti_covid19_overeno_kdy' => 'Covid-19',
    ];
    $r = dbOneLine('SELECT ' . implode(',', array_keys($udaje)) . ' FROM uzivatele_hodnoty WHERE id_uzivatele = ' . $uPracovni->id());
    $datumNarozeni = new DateTimeImmutable($r['datum_narozeni']);
    $potvrzeniOd = $r['potvrzeni_zakonneho_zastupce']
        ? new DateTimeImmutable($r['potvrzeni_zakonneho_zastupce'])
        : null;
    $potrebujePotvrzeniKvuliVeku = potrebujePotvrzeni($datumNarozeni);
    $potrebujePotvrzeniKvuliVekuZprava = '';
    $mameLetosniPotvrzeniKvuliVeku = $potvrzeniOd && $potvrzeniOd->format('y') === date('y');
    $mameNahranyLetosniDokladProtiCovidu = $uPracovni->maNahranyDokladProtiCoviduProRok((int)date('Y'));
    $mameOverenePotvrzeniProtiCoviduProRok = $uPracovni->maOverenePotvrzeniProtiCoviduProRok((int)date('Y'));
    foreach ($udaje as $sloupec => $nazev) {
        $hodnota = $r[$sloupec];
        if ($sloupec === 'op') {
            $hodnota = $uPracovni->cisloOp(); // desifruj cislo obcanskeho prukazu
        }
        $zobrazenaHodnota = $hodnota;
        $vstupniHodnota = $hodnota;
        $vyber = [];
        $popisek = '';
        if ($sloupec === 'pohlavi') {
            $vyber = ['f' => 'žena', 'm' => 'muž'];
            $zobrazenaHodnota = $vyber[$r['pohlavi']] ?? '';
        }
        if ($sloupec === 'potvrzeni_zakonneho_zastupce') {
            $popisek = sprintf(
                'Zda máme letošní potvrzení od rodiče nebo zákonného zástupce, že účastník může na Gamecon, i když mu na začátku Gameconu (%s) ještě nebude patnáct.',
                DateTimeGamecon::zacatekLetosnihoGameconu()->formatDatumStandard()
            );
            $vstupniHodnota = $potrebujePotvrzeniKvuliVeku && !$mameLetosniPotvrzeniKvuliVeku
                ? date('Y-m-d') // ulozi se dnesni datum pouze pokud je zaskrtly checkbox
                : $hodnota; // nepotrebujeme nove potvrzeni, nechavame puvodni hodnotu
            $zobrazenaHodnota = $mameLetosniPotvrzeniKvuliVeku ? 'máme' : '';
        } else if ($sloupec === 'potvrzeni_proti_covid19_overeno_kdy') {
            $popisek = 'Zda máme letošní potvrzení o očkování, prodělané nemoci nebo negativních testech na Covid-19.';
            $vstupniHodnota = !$mameOverenePotvrzeniProtiCoviduProRok
                ? date('Y-m-d') // ulozi se dnesni datum pouze pokud je zaskrtly checkbox
                : $hodnota; // nepotrebujeme nove overeni, nechavame puvodni hodnotu
            $zobrazenaHodnota = $mameOverenePotvrzeniProtiCoviduProRok ? 'oveřeno' : '';
        } else if ($sloupec === 'datum_narozeni') {
            $popisek = sprintf('Věk na začátku Gameconu %d let', vekNaZacatkuLetosnihoGameconu($datumNarozeni));
        }
        $x->assign([
            'nazev' => $nazev,
            'sloupec' => $sloupec,
            'vstupniHodnota' => $vstupniHodnota,
            'zobrazenaHodnota' => $zobrazenaHodnota,
            'vyber' => $vyber,
            'popisek' => $popisek,
        ]);
        if ($popisek) {
            $x->parse('uvod.udaje.udaj.nazevSPopiskem');
        } else {
            $x->parse('uvod.udaje.udaj.nazevBezPopisku');
        }
        if ($sloupec === 'pohlavi') {
            foreach ($vyber as $optionValue => $optionText) {
                $x->assign([
                    'optionValue' => $optionValue,
                    'optionText' => $optionText,
                    'optionSelected' => $vstupniHodnota === $optionValue
                        ? 'selected'
                        : '',
                ]);
                $x->parse('uvod.udaje.udaj.select.option');
            }
            $x->parse('uvod.udaje.udaj.select');
        } else if ($sloupec === 'poznamka') {
            $x->parse('uvod.udaje.udaj.text');
        } else if ($sloupec === 'potvrzeni_zakonneho_zastupce') {
            $x->assign([
                'checked' => $mameLetosniPotvrzeniKvuliVeku
                    ? 'checked'
                    : '',
            ]);
            $x->assign([
                'disabled' => !$potrebujePotvrzeniKvuliVeku
                    ? 'disabled'
                    : '',
            ]);
            $x->parse('uvod.udaje.udaj.checkbox');
        } else if ($sloupec === 'potvrzeni_proti_covid19_overeno_kdy') {
            $x->assign(['disabled' => '']);
            $x->assign([
                'checked' => $mameOverenePotvrzeniProtiCoviduProRok
                    ? 'checked'
                    : '',
            ]);
            $x->parse('uvod.udaje.udaj.checkbox');
        } else {
            $x->parse('uvod.udaje.udaj.input');
        }
        if ($sloupec === 'potvrzeni_zakonneho_zastupce') {
            if ($potrebujePotvrzeniKvuliVeku) {
                $potrebujePotvrzeniKvuliVekuZprava = sprintf(
                    'Uživalel potřebuje letošní potvrzení od rodiče nebo zákonného zástupce, že může na Gamecon, i když mu na začátku Gameconu (%s) ještě nebude patnáct. Přesto uložit?',
                    DateTimeGamecon::zacatekLetosnihoGameconu()->formatDatumStandard()
                );
                if (!$mameLetosniPotvrzeniKvuliVeku) {
                    $x->parse('uvod.udaje.udaj.chybi');
                }
            }
        } else if ($sloupec === 'potvrzeni_proti_covid19_overeno_kdy') {
            if (!$mameNahranyLetosniDokladProtiCovidu && !$mameOverenePotvrzeniProtiCoviduProRok) {
                $x->parse('uvod.udaje.udaj.chybi');
            }
        } else if ($sloupec !== 'poznamka') {
            if ($hodnota == '') {
                $x->parse('uvod.udaje.udaj.chybi');
            }
        }
        $x->parse('uvod.udaje.udaj');
    }
    $x->assign([
        'potrebujePotvrzeni' => $potrebujePotvrzeniKvuliVeku ? '1' : '0',
        'potrebujePotvrzeniZprava' => $potrebujePotvrzeniKvuliVekuZprava,
    ]);
    $x->parse('uvod.udaje');
}

// rychloregistrace
if (REG_GC) $x->parse('uvod.rychloregPrihlasitNaGc');

$x->parse('uvod');
$x->out('uvod');
