<?php

/**
 * Stránka k editaci ubytovacích informací
 *
 * nazev: Uživatel
 * pravo: 101
 */

use \Gamecon\Cas\DateTimeCz;
use \Gamecon\Cas\DateTimeGamecon;
use \Gamecon\Shop\Shop;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 */

require_once __DIR__ . '/../funkce.php';
require_once __DIR__ . '/../konstanty.php';

$nastaveni = ['ubytovaniBezZamku' => true, 'jidloBezZamku' => true];
$shop = $uPracovni ? new Shop($uPracovni, $nastaveni) : null;

include __DIR__ . '/_uzivatel_ovladac.php';

$x = new XTemplate('uzivatel.xtpl');
xtemplateAssignZakladniPromenne($x, $uPracovni);

// ubytovani vypis
$pokojVypis = Pokoj::zCisla(get('pokoj'));
$ubytovaniVypis = $pokojVypis ? $pokojVypis->ubytovani() : [];

if (get('pokoj')) {
    $x->assign('pokojVypis', get('pokoj'));
    if ($pokojVypis) {
        $x->assign('ubytovaniVypis', array_uprint($ubytovaniVypis, function ($e) {
            $ne = $e->gcPritomen() ? '' : 'ne';
            $color = $ne ? '#f00' : '#0a0';
            $a = $e->koncA();
            return $e->jmenoNick() . " (<span style=\"color:$color\">{$ne}dorazil$a</span>)";
        }, '<br>'));
    } else
        throw new Chyba('pokoj ' . get('pokoj') . ' neexistuje nebo je prázdný');
}

if ($uPracovni && $uPracovni->gcPrihlasen()) {
    $x->assign('shop', $shop);
    $x->parse('uzivatel.ubytovani');
    $x->parse('uzivatel.jidlo');
    $x->parse('uzivatel.pokojPridel');
}

if (!$uPracovni) {
    $x->parse('uzivatel.prodejAnonymni');
}

if (!$uPracovni) {
    $x->assign(
        'status',
        <<<HTML
        <div class="warning inline" onclick="document.getElementById('omniboxUzivateleProPraci').focus()">
            Vyberte uživatele
            <span class="skryt-pri-uzkem">(pole vlevo)</span>
            <span class="zobrazit-pri-uzkem">(pole nahoře)</span>
        </div>
        HTML
    );
} elseif (!$uPracovni->gcPrihlasen()) {
    $x->assign('status', '<div class="error">Uživatel není přihlášen na GC</div>');
}

if ($uPracovni) {
    $up = $uPracovni;
    $a = $up->koncovkaDlePohlavi();
    $pokoj = Pokoj::zUzivatele($up);
    $spolubydlici = $pokoj
        ? $pokoj->ubytovani()
        : [];
    $x->assign([
        'prehled' => $up->finance()->prehledHtml(),
        'slevyAktivity' => ($akt = $up->finance()->slevyAktivity()) ?
            '<li>' . implode('<li>', $akt) :
            '(žádné)',
        'slevyVse' => ($vse = $up->finance()->slevyVse()) ?
            '<li>' . implode('<li>', $vse) :
            '(žádné)',
    ]);
    $datumNarozeni = DateTimeImmutable::createFromMutable($up->datumNarozeni());

    $x->parse('uzivatel.slevy');
    $x->parse('uzivatel.objednavky');
}

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
        // 'op'                    =>          'Číslo OP',
    ];
    $r = dbOneLine('SELECT ' . implode(',', array_keys($udaje)) . ' FROM uzivatele_hodnoty WHERE id_uzivatele = ' . $uPracovni->id());
    $datumNarozeni = new DateTimeImmutable($r['datum_narozeni']);

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
        if ($sloupec === 'datum_narozeni') {
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
            $x->parse('uzivatel.udaje.udaj.nazevSPopiskem');
        } else {
            $x->parse('uzivatel.udaje.udaj.nazevBezPopisku');
        }
        if ($sloupec === 'telefon_uzivatele') {
            $x->assign([
                'zobrazenaHodnota' => formatujTelCislo($zobrazenaHodnota),
            ]);
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
                $x->parse('uzivatel.udaje.udaj.select.option');
            }
            $x->parse('uzivatel.udaje.udaj.select');
        } else {
            $x->parse('uzivatel.udaje.udaj.input');
        }
        $x->parse('uzivatel.udaje.udaj');
    }
    $x->parse('uzivatel.udaje');
}

// načtení předmětů a form s rychloprodejem předmětů, fixme
$o = dbQuery(<<<SQL
  SELECT
    CONCAT(nazev,' ',model_rok) as nazev,
    kusu_vyrobeno-count(n.id_predmetu) as zbyva,
    p.id_predmetu,
    ROUND(p.cena_aktualni) as cena
  FROM shop_predmety p
  LEFT JOIN shop_nakupy n ON(n.id_predmetu=p.id_predmetu)
  WHERE p.stav > 0
  GROUP BY p.id_predmetu, model_rok
  ORDER BY model_rok DESC, nazev
SQL
);
$moznosti = '<option value="">(vyber)</option>';
while ($r = mysqli_fetch_assoc($o)) {
    $zbyva = $r['zbyva'] === null ? '&infin;' : $r['zbyva'];
    $moznosti .= '<option value="' . $r['id_predmetu'] . '"' . ($r['zbyva'] > 0 || $r['zbyva'] === null ? '' : ' disabled') . '>' . $r['nazev'] . ' (' . $zbyva . ') ' . $r['cena'] . '&thinsp;Kč</option>';
}
$x->assign('predmety', $moznosti);

$x->parse('uzivatel');
$x->out('uzivatel');
