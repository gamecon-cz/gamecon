<?php

/**
 * Úvodní stránka sloužící pro infopult a další účely. Zajišťuje registraci na
 * DrD, Trojboj, Gamecon, Placení aj.
 *
 * nazev: Infopult
 * pravo: 100
 */

use Gamecon\Accounting;
use Gamecon\Accounting\TransactionCategory;
use Gamecon\Accounting\Transaction;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Pravo;
use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Role\Role;
use Gamecon\Web\Info;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;

/**
 * @var Uzivatel|null|void $u
 * @var Uzivatel|null|void $uPracovni
 * @var \Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

require_once __DIR__ . '/_submoduly/ubytovani_tabulka.php';
require_once __DIR__ . '/_submoduly/osobni-udaje/osobni_udaje.php';

$ok = '<img alt="OK" src="files/design/ok-s.png" style="margin-bottom:-2px">';
$warn = '<img alt="warning" src="files/design/warning-s.png" style="margin-bottom:-2px">';
$err = '<img alt="error" src="files/design/error-s.png" style="margin-bottom:-2px">';

$nastaveni = ['ubytovaniBezZamku' => true, 'jidloBezZamku' => true];
$shop = $uPracovni ? new Shop($uPracovni, $u, $systemoveNastaveni, $nastaveni) : null;

const OPERATION_CANCEL_TRANSACTION = 'cancelTransaction';

include __DIR__ . '/_infopult_ovladac.php';

$x = new XTemplate(__DIR__ . '/infopult.xtpl');

$x->assign(['ok' => $ok, 'warn' => $warn, 'err' => $err, 'rok' => ROCNIK]);
if ($uPracovni) {
    $x->assign([
        'a' => $uPracovni->koncovkaDlePohlavi(),
        'ka' => $uPracovni->koncovkaDlePohlavi() ? 'ka' : '',
    ]);
}

$x->assign([
    'prihlasDisabled' => "disabled",
    'prijelADatMaterialyDisabled' => "disabled",
    'gcOdjedDisabled' => "disabled",
    'odhlasDisabled' => "disabled",
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
        if ($systemoveNastaveni->prihlasovaniUcastnikuSpusteno()) {
            $x->assign('prihlasDisabled', '');
        } else {
            if (!$systemoveNastaveni->jsmeNaOstre()) {
                $x->assign('urlNastaveniZacatkuRegistraci', URL_ADMIN . '/nastaveni#' . SystemoveNastaveniKlice::REG_GC_OD);
                $x->assign('urlNastaveniKonceRegistraci', URL_ADMIN . '/nastaveni#' . SystemoveNastaveniKlice::REG_GC_DO);
                $x->assign('totoSeUkazujePouzeNaTestu', (new Info($systemoveNastaveni))->htmlTotoSeUkazujePouzeNaTestu());
                $x->parse('infopult.neprihlasen.registraceNaGcNeniSpustena.hintJakSpustitRegistrace');
            }
            $x->parse('infopult.neprihlasen.registraceNaGcNeniSpustena');
        }
        $x->parse('infopult.neprihlasen');
    }
    $pokoj = Pokoj::zUzivatele($uPracovni);
    $spolubydlici = $pokoj
        ? $pokoj->ubytovani()
        : [];
    $typyProPrehled = [
        TypPredmetu::PREDMET,
        TypPredmetu::TRICKO,
    ];
    if ($u->maPravo(Pravo::MUZE_RUSIT_NAKUPY)) {
        $typyProPrehled[] = TypPredmetu::VSTUPNE;
    }
    $x->assign([
        'stavUctu' => sprintf(
            '%s <span class="stav-uctu-castka">%d</span> Kč',
            $uPracovni->finance()->stav() < 0
                ? $err
                : $ok,
            $uPracovni->finance()->stav(),
        ),
        'stavStyle' => ($uPracovni->finance()->stav() < 0 ? 'color: #f22; font-weight: bolder;' : ''),
        'pokoj' => $pokoj ? $pokoj->cislo() : '(nepřidělen)',
        'spolubydlici' => array_uprint($spolubydlici, static function (Uzivatel $spolubydla) {
            return "<li> {$spolubydla->jmenoNick()} ({$spolubydla->id()}) {$spolubydla->telefon()} </li>";
        }),
        'org' => $u->jmenoNick(),
        'orgA' => $u->koncovkaDlePohlavi(),
        'poznamka' => $uPracovni->poznamka(),
        'ubytovani' => $uPracovni->shop()->dejPopisUbytovani(),
        'balicek' => $uPracovni->balicekHtml(),
        'prehledPredmetu' => implode("", array_map(fn(Transaction $t) => "<tr>" . "<td>" . $t->getDescription() . "</td>" .
            ($u?->maPravo(Pravo::MUZE_RUSIT_NAKUPY) ?
                "<td><form method='post' onsubmit=\"return confirm('Opravdu zrušit objednávku " . $t->getDescription() . "');\">" .
                "<input type='hidden' name='" . OPERATION_CANCEL_TRANSACTION . "' value='" . $t->getId() . "'>" .
                "<button type='submit'><i class='fa fa-trash' aria-hidden='true'/></button>" .
                "</form></td>" :
            "<td></td>") .
            "</tr>", array_filter(Accounting::getPersonalFinance($uPracovni)->getTransactions(),
        fn(Transaction $t) => $t->getCategory() == TransactionCategory::SHOP_ITEMS ||
            ($u->maPravo(Pravo::MUZE_RUSIT_NAKUPY) && $t->getCategory() == TransactionCategory::VOLUNTARY_DONATION))))
    ]);

    $maObjednaneUbytovani = $uPracovni->shop()->ubytovani()->maObjednaneUbytovani();
    $chybejiciUdaje = $uPracovni->chybejiciUdaje(
        Uzivatel::povinneUdajeProRegistraci($maObjednaneUbytovani),
    );
    $udajePovinneAleNezkontrolovane = $maObjednaneUbytovani && !$uPracovni->maZkontrolovaneUdaje();

    $udajeStav = '';
    if (count($chybejiciUdaje) > 0) {
        $udajeStav = $err . ' chybí údaje';
    } else {
        if ($maObjednaneUbytovani) {
            if ($udajePovinneAleNezkontrolovane) {
                $udajeStav = $err . ' zkontrolovat údaje';
            } else {
                $udajeStav = $ok . ' údaje v pořádku';
            }
        } else {
            $udajeStav = $ok . ' údaje kompletní';
        }
    }
    $x->assign('udajeStav', $udajeStav);

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

    $datumNarozeni = DateTimeImmutable::createFromMutable($uPracovni->datumNarozeni());
    $potrebujePotvrzeniKvuliVeku = potrebujePotvrzeni($datumNarozeni);
    $potvrzeniOd = $uPracovni->potvrzeniZakonnehoZastupceOd();
    $mameLetosniPotvrzeniKvuliVeku = $potvrzeniOd && $potvrzeniOd->format('Y') == ROCNIK;
    $nahranePotvrzeni = $uPracovni->potvrzeniZakonnehoZastupceSouborOd();
    $mameLetosniNahranePotvrzeni = $nahranePotvrzeni && $nahranePotvrzeni->format('Y') == ROCNIK;

    if ($potrebujePotvrzeniKvuliVeku) {
        if ($mameLetosniPotvrzeniKvuliVeku) {
            $x->assign("potvrzeniAttr", 'checked value=""');
            $x->assign("potvrzeniText", $ok . " má potvrzení od rodičů");
        } else {
            $x->assign("potvrzeniText", $err . " chybí potvrzení od rodičů!");
        }
        if ($mameLetosniNahranePotvrzeni) {
            $x->assign("potvrzeniOdkaz", '<a href="infopult/potvrzeni-rodicu?id=' . $uPracovni->id() . '">odkaz na potvrzení</a>');
        }
        $x->parse('infopult.uzivatel.potvrzeni');
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

    if ($u->jeInfopultak() && !$u->jeSefInfopultu()) {
        $zpravyProPotvrzeni = [];
        $a = $uPracovni->koncovkaDlePohlavi();
        if (!$uPracovni->gcPritomen()) {
            $zpravyProPotvrzeni['materialy'] = "nemá potvrzeno že přijel{$a} a že dostal{$a} materiály";
        }
        if (Accounting::getPersonalFinance($uPracovni)->getTotal() < 0) {
            $zpravyProPotvrzeni[] = 'má nedoplatek';
        }
        if ($potrebujePotvrzeniKvuliVeku && !$mameLetosniPotvrzeniKvuliVeku) {
            $zpravyProPotvrzeni[] = 'nemá potvrzení od rodičů';
        }
        if (count($chybejiciUdaje) > 0) {
            $zpravyProPotvrzeni[] = 'nemá kompletní osobní údaje';
        }
        if ($udajePovinneAleNezkontrolovane) {
            $zpravyProPotvrzeni[] = 'nemá zkontrolované osobní údaje';
        }
        if ($zpravyProPotvrzeni !== []) {
            $zpravyProPotvrzeni = array_map(static fn(string $zprava) => "- $zprava", $zpravyProPotvrzeni);
            $zpravyProPotvrzeniZruseniPraceText = implode("\n", $zpravyProPotvrzeni);

            $ucastnikNazev = $uPracovni->jeMuz()
                ? 'Účastník'
                : 'Účastnice';
            $x->assign(
                'zpravaProPotvrzeniZruseniPrace',
                // json_encode kvůli JS error "SyntaxError: '' string literal contains an unescaped line break"
                json_encode(
                    "{$ucastnikNazev}\n{$zpravyProPotvrzeniZruseniPraceText}.\nPřesto ukončit práci s uživatelem?",
                ),
            );
            $x->parse('infopult.potvrditZruseniPrace');

            $zpravyProPotvrzeniZmenyStavu = $zpravyProPotvrzeni;
            unset($zpravyProPotvrzeniZmenyStavu['materialy']);
            if ($zpravyProPotvrzeniZmenyStavu !== []) {
                $zpravyProPotvrzeniZmenyStavuText = implode("\n", $zpravyProPotvrzeniZmenyStavu);
                $x->assign(
                    'zpravaProPotvrzeniZmenyStavu',
                    // json_encode kvůli JS error "SyntaxError: '' string literal contains an unescaped line break"
                    json_encode("{$ucastnikNazev}\n{$zpravyProPotvrzeniZmenyStavuText}.\nPřesto dát materiály?"),
                );
                $x->parse('infopult.potvrditZmenuStavu');
            }
        }
    }

    $maUbytovani = $uPracovni->shop()->ubytovani()->maObjednaneUbytovani();
    $x->assign(
        'udajeHtml',
        OsobniUdajeTabulka::osobniUdajeTabulkaZ($uPracovni, $maUbytovani),
    );

    $qrKod = $uPracovni->finance()->dejQrKodProPlatbu();
    $x->assign(
        "qrKodPlatba", $qrKod->getDataUri()
    );

    if (!$systemoveNastaveni->jsmeNaOstre()) {
        $x->assign(
            'htmlTotoSeUkazujePouzeNaTestu',
            (new Info($systemoveNastaveni))->htmlTotoSeUkazujePouzeNaTestu(),
        );
        $x->parse('infopult.uzivatel.idFioPohybu');
    }

    $x->parse('infopult.uzivatel.objednavky');
    $x->parse('infopult.uzivatel');
} else {
    $x->parse('infopult.neUzivatel');
}

// načtení předmětů a form s rychloprodejem předmětů, fixme
$o = dbQuery(
    <<<SQL
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
    $zbyva = $r['zbyva'] === null ? '&infin;' : $r['zbyva'];
    $moznosti .= '<option value="' . $r['id_predmetu'] . '"' . ($r['zbyva'] > 0 || $r['zbyva'] === null ? '' : ' disabled') . '>' . $r['nazev'] . ' (' . $zbyva . ') ' . $r['cena'] . '&thinsp;Kč</option>';
}
$x->assign('predmety', $moznosti);

// rychloregistrace
if (!$uPracovni) { // nechceme zobrazovat rychloregistraci (zakladani uctu), kdyz mame vybraneho uzivatele pro praci
    $x->parse('infopult.rychloregistrace');
}

$x->parse('infopult');
$x->out('infopult');

require __DIR__ . '/_shop.php';
