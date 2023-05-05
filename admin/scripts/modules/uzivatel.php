<?php

/**
 * Stránka k editaci ubytovacích informací
 *
 * nazev: Uživatel
 * pravo: 101
 */

use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Uzivatel\Pohlavi;
use Gamecon\Uzivatel\SqlStruktura\UzivatelSqlStruktura as Sql;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$ok   = '<img alt="OK" src="files/design/ok-s.png" style="margin-bottom:-2px">';
$warn = '<img alt="warning" src="files/design/warning-s.png" style="margin-bottom:-2px">';
$err  = '<img alt="error" src="files/design/error-s.png" style="margin-bottom:-2px">';

$nastaveni = ['ubytovaniBezZamku' => true, 'jidloBezZamku' => true];
$shop      = $uPracovni ? new Shop($uPracovni, $u, $nastaveni, $systemoveNastaveni) : null;

include __DIR__ . '/_uzivatel_ovladac.php';

$x = new XTemplate('uzivatel.xtpl');

$x->assign(['ok' => $ok, 'err' => $err, 'rok' => ROCNIK]);
if ($uPracovni) {
    $x->assign([
        'a'  => $uPracovni->koncovkaDlePohlavi(),
        'ka' => $uPracovni->koncovkaDlePohlavi('ka'),
    ]);
}

// ubytovani vypis
$pokojVypis     = Pokoj::zCisla(get('pokoj'));
$ubytovaniVypis = $pokojVypis ? $pokojVypis->ubytovani() : [];

if (get('pokoj')) {
    $x->assign('pokojVypis', get('pokoj'));
    if ($pokojVypis) {
        $x->assign('ubytovaniVypis', array_uprint($ubytovaniVypis, function ($e) {
            $ne    = $e->gcPritomen() ? '' : 'ne';
            $color = $ne ? '#f00' : '#0a0';
            $a     = $e->koncA();
            return $e->jmenoNick() . " (<span style=\"color:$color\">{$ne}dorazil$a</span>)";
        }, '<br>'));
    } else
        throw new Chyba('pokoj ' . get('pokoj') . ' neexistuje nebo je prázdný');
}

if ($uPracovni && $uPracovni->gcPrihlasen()) {
    $x->assign('ubytovaniHtml', $shop->ubytovaniHtml(true));
    $x->assign('jidloHtml', $shop->jidloHtml(true));
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
        HTML,
    );
} else if (!$uPracovni->gcPrihlasen()) {
    $x->assign('status', '<div class="error">Uživatel není přihlášen na GC</div>');
}

if ($uPracovni) {
    $up           = $uPracovni;
    $a            = $up->koncovkaDlePohlavi();
    $pokoj        = Pokoj::zUzivatele($up);
    $spolubydlici = $pokoj
        ? $pokoj->ubytovani()
        : [];
    $x->assign([
        'prehled'       => $up->finance()->prehledHtml(),
        'slevyAktivity' => ($akt = $up->finance()->slevyAktivity())
            ?
            '<li>' . implode('<li>', $akt)
            :
            '(žádné)',
        'slevyVse'      => ($vse = $up->finance()->slevyVse())
            ?
            '<li>' . implode('<li>', $vse)
            :
            '(žádné)',
    ]);
    $datumNarozeni = DateTimeImmutable::createFromMutable($up->datumNarozeni());

    $x->parse('uzivatel.slevy');
    $x->parse('uzivatel.objednavky');
}

// form s osobními údaji
if ($uPracovni) {
    $udaje         = [
        Sql::LOGIN_UZIVATELE      => 'Přezdívka',
        Sql::JMENO_UZIVATELE      => 'Jméno',
        Sql::PRIJMENI_UZIVATELE   => 'Příjmení',
        Sql::POHLAVI              => 'Pohlaví',
        Sql::ULICE_A_CP_UZIVATELE => 'Ulice',
        Sql::MESTO_UZIVATELE      => 'Město',
        Sql::PSC_UZIVATELE        => 'PSČ',
        Sql::TELEFON_UZIVATELE    => 'Telefon',
        Sql::DATUM_NAROZENI       => 'Narozen' . $uPracovni->koncovkaDlePohlavi(),
        Sql::EMAIL1_UZIVATELE     => 'E-mail',
        // 'op'                    =>          'Číslo OP',
    ];
    $r             = dbOneLine('SELECT ' . implode(',', array_keys($udaje)) . ' FROM uzivatele_hodnoty WHERE id_uzivatele = ' . $uPracovni->id());
    $datumNarozeni = new DateTimeImmutable($r['datum_narozeni']);

    foreach ($udaje as $sloupec => $nazev) {
        $hodnota = $r[$sloupec];
        if ($sloupec === 'op') {
            $hodnota = $uPracovni->cisloOp(); // desifruj cislo obcanskeho prukazu
        }
        $zobrazenaHodnota = $hodnota;
        $vstupniHodnota   = $hodnota;
        $vyber            = [];
        $popisek          = '';
        if ($sloupec === Sql::POHLAVI) {
            $vyber            = Pohlavi::seznamProSelect();
            $zobrazenaHodnota = $vyber[$r[Sql::POHLAVI]] ?? '';
        }
        if ($sloupec === 'telefon_uzivatele') {
            $zobrazenaHodnota = $uPracovni->telefon();
        }
        if ($sloupec === 'datum_narozeni') {
            $popisek = sprintf('Věk na začátku Gameconu %d let', vekNaZacatkuLetosnihoGameconu($datumNarozeni));
        }
        $x->assign([
            'nazev'            => $nazev,
            'sloupec'          => $sloupec,
            'vstupniHodnota'   => $vstupniHodnota,
            'zobrazenaHodnota' => $zobrazenaHodnota,
            'vyber'            => $vyber,
            'popisek'          => $popisek,
        ]);
        if ($popisek) {
            $x->parse('uzivatel.udaje.udaj.nazevSPopiskem');
        } else {
            $x->parse('uzivatel.udaje.udaj.nazevBezPopisku');
        }
        if ($sloupec === Sql::POHLAVI) {
            foreach ($vyber as $optionValue => $optionText) {
                $x->assign([
                    'optionValue'    => $optionValue,
                    'optionText'     => $optionText,
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
$o        = dbQuery(<<<SQL
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
SQL,
);
$moznosti = '<option value="">(vyber)</option>';
while ($r = mysqli_fetch_assoc($o)) {
    $zbyva    = $r['zbyva'] === null ? '&infin;' : $r['zbyva'];
    $moznosti .= '<option value="' . $r['id_predmetu'] . '"' . ($r['zbyva'] > 0 || $r['zbyva'] === null ? '' : ' disabled') . '>' . $r['nazev'] . ' (' . $zbyva . ') ' . $r['cena'] . '&thinsp;Kč</option>';
}
$x->assign('predmety', $moznosti);

$x->parse('uzivatel');
$x->out('uzivatel');
