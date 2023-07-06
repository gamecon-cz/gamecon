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
use Gamecon\XTemplate\XTemplate;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Role\Role;
use Gamecon\Web\Info;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

require_once __DIR__ . '/_ubytovani_tabulka.php';

$ok   = '<img alt="OK" src="files/design/ok-s.png" style="margin-bottom:-2px">';
$warn = '<img alt="warning" src="files/design/warning-s.png" style="margin-bottom:-2px">';
$err  = '<img alt="error" src="files/design/error-s.png" style="margin-bottom:-2px">';

$nastaveni = ['ubytovaniBezZamku' => true, 'jidloBezZamku' => true];
$shop      = $uPracovni ? new Shop($uPracovni, $u, $nastaveni, $systemoveNastaveni) : null;

include __DIR__ . '/_infopult_ovladac.php';

$x = new XTemplate(__DIR__ . '/infopult.xtpl');

$x->assign(['ok' => $ok, 'err' => $err, 'rok' => ROCNIK]);
if ($uPracovni) {
    $x->assign([
        'a'  => $uPracovni->koncovkaDlePohlavi(),
        'ka' => $uPracovni->koncovkaDlePohlavi() ? 'ka' : '',
    ]);
}

$x->assign([
    'prihlasDisabled'             => "disabled",
    'prijelADatMaterialyDisabled' => "disabled",
    'gcOdjedDisabled'             => "disabled",
    'odhlasDisabled'              => "disabled",
]);

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

if ($uPracovni) {
    if (!$uPracovni->gcPrihlasen()) {
        if ($systemoveNastaveni->prihlasovaniUcastnikuSpusteno()) {
            $x->assign('prihlasDisabled', '');
        } else {
            $x->parse('infopult.neprihlasen.nelze');
        }
        $x->parse('infopult.neprihlasen');
    }
    $pokoj          = Pokoj::zUzivatele($uPracovni);
    $spolubydlici   = $pokoj
        ? $pokoj->ubytovani()
        : [];
    $typyProPrehled = [
        TypPredmetu::PREDMET,
        TypPredmetu::TRICKO,
    ];
    if ($u->jeSpravceFinanci()) {
        $typyProPrehled[] = TypPredmetu::VSTUPNE;
    }
    $x->assign([
        'stavUctu'        => ($uPracovni->finance()->stav() < 0 ? $err : $ok) . ' ' . $uPracovni->finance()->stavHr(),
        'stavStyle'       => ($uPracovni->finance()->stav() < 0 ? 'color: #f22; font-weight: bolder;' : ''),
        'pokoj'           => $pokoj ? $pokoj->cislo() : '(nepřidělen)',
        'spolubydlici'    => array_uprint($spolubydlici, static function (Uzivatel $spolubydla) {
            return "<li> {$spolubydla->jmenoNick()} ({$spolubydla->id()}) {$spolubydla->telefon()} </li>";
        }),
        'org'             => $u->jmenoNick(),
        'orgA'            => $u->koncovkaDlePohlavi(),
        'poznamka'        => $uPracovni->poznamka(),
        'ubytovani'       => $uPracovni->shop()->dejPopisUbytovani(),
        'udajeChybiAttr'  => 'href="uzivatel"',
        'balicek'         => $uPracovni->balicekHtml(),
        'prehledPredmetu' => $uPracovni->finance()->prehledHtml(
            $typyProPrehled,
            false,
            $u->jeSpravceFinanci(),
        ),
    ]);

    $chybejiciUdaje = $uPracovni->chybejiciUdaje(
        Uzivatel::povinneUdajeProRegistraci($uPracovni->shop()->ubytovani()->maObjednaneUbytovani()),
    );
    $x->assign(
        'udajeChybiText',
        count($chybejiciUdaje) > 0
            ? $err . ' chybí osobní údaje: <ul style="font-size: smaller">' . implode('', array_map(static fn(string $nazevUdaje) => '<li><i>' . mb_strtolower($nazevUdaje) . '</i></li>', $chybejiciUdaje)) . '</ul>'
            : $ok . ' osobní údaje v pořádku',
    );

    if ($uPracovni->finance()->stav() < 0 && !$uPracovni->gcPritomen()) {
        $x->parse('infopult.upoMaterialy');
    }
    if ($uPracovni->gcPrihlasen()) {
        if (!$uPracovni->gcPritomen()) {
            $x->assign('prijelADatMaterialyDisabled', '');
        } else if (!$uPracovni->gcOdjel()) {
            $x->assign('gcOdjedDisabled', '');
        }
    }
    if ($uPracovni) {
        if (!$uPracovni->gcPrihlasen() || $uPracovni->gcPritomen()) {
            $x->parse('infopult.odhlasitZGc.prihlasenyNepritomny');
        } else if ($uPracovni->gcPrihlasen() && !$uPracovni->gcPritomen() && $u->maRoli(Role::CFO)) {
            $x->assign('odhlasDisabled', '');
        }
        $x->parse('infopult.odhlasitZGc');
    }

    $datumNarozeni                 = DateTimeImmutable::createFromMutable($uPracovni->datumNarozeni());
    $potvrzeniOd                   = $uPracovni->potvrzeniZakonnehoZastupceOd();
    $potrebujePotvrzeniKvuliVeku   = potrebujePotvrzeni($datumNarozeni);
    $mameLetosniPotvrzeniKvuliVeku = $potvrzeniOd && $potvrzeniOd->format('y') === date('y');

    if ($potrebujePotvrzeniKvuliVeku) {
        if ($mameLetosniPotvrzeniKvuliVeku) {
            $x->assign("potvrzeniAttr", 'checked value=""');
            $x->assign("potvrzeniText", $ok . " má potvrzení od rodičů");
        } else {
            $x->assign("potvrzeniText", $err . " chybí potvrzení od rodičů!");
        }
        $x->parse('infopult.uzivatel.potvrzeni');
    }

    if (VYZADOVANO_COVID_POTVRZENI) {
        $mameNahranyLetosniDokladProtiCovidu   = $uPracovni->maNahranyDokladProtiCoviduProRok((int)date('Y'));
        $mameOverenePotvrzeniProtiCoviduProRok = $uPracovni->maOverenePotvrzeniProtiCoviduProRok((int)date('Y'));
        if (!$mameNahranyLetosniDokladProtiCovidu && !$mameOverenePotvrzeniProtiCoviduProRok) {
            /* muze byt overeno rucne bez nahraneho dokladu */
            $x->assign("covidPotvrzeniText", $err . " požádej o doplnění");
        } else if (!$mameNahranyLetosniDokladProtiCovidu) {
            /* potvrzeno rucne na infopultu, bez nahraneho dokladu */
            $x->assign("covidPotvrzeniAttr", 'checked value=""');
            $x->assign("covidPotvrzeniText", $ok . " ověřeno bez dokladu");
        } else {
            $datumNahraniPotvrzeniProtiCovid = (new DateTimeCz($uPracovni->potvrzeniProtiCoviduPridanoKdy()->format(DATE_ATOM)))->relativni();
            $x->assign('covidPotvrzeniOdkazAttr', "href=\n" . $uPracovni->urlNaPotvrzeniProtiCoviduProAdmin() . "\"");
            if ($mameOverenePotvrzeniProtiCoviduProRok) {
                $x->assign("covidPotvrzeniAttr", 'checked value=""');
                $x->assign("covidPotvrzeniText", $ok . " ověřeno dokladem $datumNahraniPotvrzeniProtiCovid");
            } else {
                $x->assign("covidPotvrzeniText", $warn . " neověřený doklad $datumNahraniPotvrzeniProtiCovid");
            }
        }
        $x->parse('infopult.uzivatel.covidSekce');
    }

    $x->assign("telefon", $uPracovni->telefon());

    if ($uPracovni->gcPrihlasen()) {
        $x->assign(
            'ubytovaniTabulka',
            UbytovaniTabulka::ubytovaniTabulkaZ(
                $shop->ubytovani(),
                $systemoveNastaveni,
                true,
            ),
        );
    }

    if ($systemoveNastaveni->gcBezi()) {
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

    if (!$systemoveNastaveni->jsmeNaOstre()) {
        $x->assign(
            'htmlTotoSeUkazujePouzeNaTestu',
            (new Info($systemoveNastaveni))->htmlTotoSeUkazujePouzeNaTestu(),
        );
        $x->parse('infopult.uzivatel.idFioPohybu');
    }

    if ($u?->jeSpravceFinanci()) {
        $x->parse('infopult.uzivatel.objednavky.nadpisVse');
    } else {
        $x->parse('infopult.uzivatel.objednavky.nadpisJenPredmety');
    }
    $x->parse('infopult.uzivatel.objednavky');
    $x->parse('infopult.uzivatel');
} else {
    $x->parse('infopult.neUzivatel');
    $x->parse('infopult.prodejAnonymni');
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

// rychloregistrace
if (!$uPracovni) { // nechceme zobrazovat rychloregistraci (zakladani uctu), kdyz mame vybraneho uzivatele pro praci
    $x->parse('infopult.rychloregistrace');
    if ($systemoveNastaveni->prihlasovaniUcastnikuSpusteno()) {
        $x->parse('infopult.rychloregistrace.prihlasitNaGc');
    }
}

$x->parse('infopult');
$x->out('infopult');

require __DIR__ . '/_shop.php';
