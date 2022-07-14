<?php

/**
 * Úvodní stránka sloužící pro infopult a další účely. Zajišťuje registraci na
 * DrD, Trojboj, Gamecon, Placení aj.
 *
 * nazev: Infopult
 * pravo: 100
 */

use Gamecon\Cas\DateTimeCz;
use Gamecon\Shop\Shop;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

require_once __DIR__ . '/../funkce.php';
require_once __DIR__ . '/_ubytovani_tabulka.php';

$ok = '<img alt="OK" src="files/design/ok-s.png" style="margin-bottom:-2px">';
$warn = '<img alt="warning" src="files/design/warning-s.png" style="margin-bottom:-2px">';
$err = '<img alt="error" src="files/design/error-s.png" style="margin-bottom:-2px">';

$nastaveni = ['ubytovaniBezZamku' => true, 'jidloBezZamku' => true];
$shop = $uPracovni ? new Shop($uPracovni, $nastaveni, $systemoveNastaveni) : null;

include __DIR__ . '/_infopult_ovladac.php';

$x = new XTemplate(__DIR__ . '/infopult.xtpl');
xtemplateAssignZakladniPromenne($x, $uPracovni);
$x->assign([
    'prihlasBtnAttr' => "disabled",
    'datMaterialyBtnAttr' => "disabled",
    'gcOdjedBtnAttr' => "disabled",
    'odhlasBtnAttr' => "disabled",
]);

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

if ($uPracovni) {
    if (!$uPracovni->gcPrihlasen()) {
        if (REG_GC) {
            $x->assign('prihlasBtnAttr', "");
        } else {
            $x->parse('infopult.neprihlasen.nelze');
        }
        $x->parse('infopult.neprihlasen');
    }
    $pokoj = Pokoj::zUzivatele($uPracovni);
    $spolubydlici = $pokoj
        ? $pokoj->ubytovani()
        : [];
    $x->assign([
        'stavUctu' => ($uPracovni->finance()->stav() < 0 ? $err : $ok) . ' ' . $uPracovni->finance()->stavHr(),
        'stavStyle' => ($uPracovni->finance()->stav() < 0 ? 'color: #f22; font-weight: bolder;' : ''),
        'pokoj' => $pokoj ? $pokoj->cislo() : '(nepřidělen)',
        'spolubydlici' => spolubydliciTisk($spolubydlici),
        'org' => $u->jmenoNick(),
        'a' => $u->koncovkaDlePohlavi(),
        'poznamka' => $uPracovni->poznamka(),
        'ubytovani' => $uPracovni->dejShop()->dejPopisUbytovani(),
        'udajeChybiAttr' => 'href="uzivatel"',
        'balicek' => $uPracovni->balicekHtml(),
        'prehledPredmetu' => $uPracovni->finance()->prehledHtml([
            Shop::PREDMET,
            Shop::TRICKO,
        ], false),
    ]);

    $chybiUdaje = count(
            array_filter(
                $uPracovni->chybejiciUdaje(),
                function ($x) {
                    return in_array($x, [
                        'jmeno_uzivatele',
                        'prijmeni_uzivatele',
                        'telefon_uzivatele',
                        'email1_uzivatele',
                    ]);
                }
            )
        ) > 0;
    $x->assign(
        'udajeChybiText',
        $chybiUdaje
            ? $err . ' chybí osobní údaje!'
            : $ok . ' osobní údaje v pořádku',
    );

    if ($uPracovni->finance()->stav() < 0 && !$uPracovni->gcPritomen()) {
        $x->parse('infopult.upoMaterialy');
    }
    if ($uPracovni->gcPrihlasen()) {
        if (!$uPracovni->gcPritomen()) {
            $x->assign('datMaterialyBtnAttr', "");
        } elseif (!$uPracovni->gcOdjel()) {
            $x->assign('gcOdjedBtnAttr', "");
        }
    }
    if ($uPracovni->gcPrihlasen() && !$uPracovni->gcPritomen()) {
        $x->assign('odhlasBtnAttr', '');
    }

    $datumNarozeni = DateTimeImmutable::createFromMutable($uPracovni->datumNarozeni());
    $potvrzeniOd = $uPracovni->potvrzeniZakonnehoZastupce();
    $potrebujePotvrzeniKvuliVeku = potrebujePotvrzeni($datumNarozeni);
    $mameLetosniPotvrzeniKvuliVeku = $potvrzeniOd && $potvrzeniOd->format('y') === date('y');

    if ($potrebujePotvrzeniKvuliVeku) {
        if ($mameLetosniPotvrzeniKvuliVeku) {
            $x->assign("potvrzeniAttr", "checked value=\"\"");
            $x->assign("potvrzeniText", $ok . " má potvrzení od rodičů");
        } else {
            $x->assign("potvrzeniText", $err . " chybí potvrzení od rodičů!");
        }
        $x->parse('infopult.uzivatel.potvrzeni');
    }

    if (VYZADOVANO_COVID_POTVRZENI) {
        $mameNahranyLetosniDokladProtiCovidu = $uPracovni->maNahranyDokladProtiCoviduProRok((int)date('Y'));
        $mameOverenePotvrzeniProtiCoviduProRok = $uPracovni->maOverenePotvrzeniProtiCoviduProRok((int)date('Y'));
        if (!$mameNahranyLetosniDokladProtiCovidu && !$mameOverenePotvrzeniProtiCoviduProRok) {
            /* muze byt overeno rucne bez nahraneho dokladu */
            $x->assign("covidPotvrzeniText", $err . " požádej o doplnění");
        } elseif (!$mameNahranyLetosniDokladProtiCovidu) {
            /* potvrzeno rucne na infopultu, bez nahraneho dokladu */
            $x->assign("covidPotvrzeniAttr", "checked value=\"\"");
            $x->assign("covidPotvrzeniText", $ok . " ověřeno bez dokladu");
        } else {
            $datumNahraniPotvrzeniProtiCovid = (new DateTimeCz($uPracovni->potvrzeniProtiCoviduPridanoKdy()->format(DATE_ATOM)))->relativni();
            $x->assign('covidPotvrzeniOdkazAttr', "href=\n" . $uPracovni->urlNaPotvrzeniProtiCoviduProAdmin() . "\"");
            if ($mameOverenePotvrzeniProtiCoviduProRok) {
                $x->assign("covidPotvrzeniAttr", "checked value=\"\"");
                $x->assign("covidPotvrzeniText", $ok . " ověřeno dokladem $datumNahraniPotvrzeniProtiCovid");
            } else {
                $x->assign("covidPotvrzeniText", $warn . " neověřený doklad $datumNahraniPotvrzeniProtiCovid");
            }
        }
        $x->parse('infopult.uzivatel.covidSekce');
    }

    $x->assign("telefon", formatujTelCislo($uPracovni->telefon()));

    if ($uPracovni->gcPrihlasen()) {
        $x->assign(
            'ubytovaniTabulka',
            UbytovaniTabulka::ubytovaniTabulkaZ(
                $shop->ubytovani(),
                $systemoveNastaveni,
                true
            )
        );
    }

    if (GC_BEZI) {
        $zpravyProPotvrzeniZruseniPrace = [];
        if (!$uPracovni->gcPritomen()) {
            $zpravyProPotvrzeniZruseniPrace[] = 'nedostal materiály';
        }
        if ($uPracovni->finance()->stav() < 0) {
            $zpravyProPotvrzeniZruseniPrace[] = 'má záporný zůstatek';
        }
        if ($potrebujePotvrzeniKvuliVeku && !$mameLetosniPotvrzeniKvuliVeku) {
            $zpravyProPotvrzeniZruseniPrace[] = 'nemá potvrzení od rodičů';
        }
        foreach ($zpravyProPotvrzeniZruseniPrace as $zpravaProPotvrzeniZruseniPrace) {
            $x->assign([
                'zpravaProPotvrzeniZruseniPrace' => "Uživatel {$zpravaProPotvrzeniZruseniPrace}. Přesto ukončit práci s uživatelem?",
            ]);
            $x->parse('infopult.potvrditZruseniPrace');
        }
    }

    // if ($u && $u->isSuperAdmin()) {
    //     $x->parse('infopult.uzivatel.idFioPohybu');
    // }

    $x->parse('infopult.uzivatel.objednavky');
    $x->parse('infopult.uzivatel');
} else {
    $x->parse('infopult.neUzivatel');
    $x->parse('infopult.prodejAnonymni');
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

// rychloregistrace
if (!$uPracovni) { // nechceme zobrazovat rychloregistraci (zakladani uctu), kdyz mame vybraneho uzivatele pro praci
    $x->parse('infopult.rychloregistrace');
    if (REG_GC) {
        $x->parse('infopult.rychloregistrace.prihlasitNaGc');
    }
}

$x->parse('infopult');
$x->out('infopult');
