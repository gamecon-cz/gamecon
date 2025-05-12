<?php

use Gamecon\Cas\DateTimeCz;
use Gamecon\Shop\Shop;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Pravo;

/**
 * @see web/sablony/blackarrow/prihlaska.xtpl
 * @var \Gamecon\XTemplate\XTemplate $t
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$this->pridejJsSoubor('soubory/blackarrow/prihlaska/prihlaska.js');
$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev('Přihláška');

$covidSekceFunkce = require __DIR__ . '/covid-sekce-funkce.php';

function cestaKObrazkuEshopPredmetu(string $soubor): string
{
    return adresarKObrazkuEshopPredmetu() . '/' . $soubor;
}

function adresarKObrazkuEshopPredmetu(): string
{
    return WWW . '/soubory/obsah/materialy/' . ROCNIK . '/eshop';
}

/**
 * @throws \RuntimeException
 * Pomocná funkce pro náhled předmětu pro aktuální ročník
 */
function miniauturaNahleduPredmetu(string $cestaKObrazku): string
{
    return Nahled::zeSouboru($cestaKObrazku)
        ->kvalita(98)
        ->pasuj(268)
        ->url();
}

/**
 * @throws \RuntimeException
 * Pomocná funkce pro náhled předmětu pro aktuální ročník
 */
function nahledPredmetu(string $cestaKObrazku): string
{
    return Nahled::zeSouboru($cestaKObrazku)
        ->kvalita(98)
        ->url();
}

if (post('pridatPotvrzeniProtiCovidu')) {
    if (!$u->zpracujPotvrzeniProtiCovidu()) {
        if (is_ajax()) {
            echo json_encode(['chyba' => 'Potvrzení se nezdařilo nahrát.']);
            exit;
        }
        chyba('Nejdříve vlož potvrzení.');
    } else {
        if (is_ajax()) {
            echo json_encode(['covidSekce' => $covidSekceFunkce($u->shop())]);
            exit;
        }
        oznameni('Potvrzení bylo uloženo.');
    }
}

if (post('pridatPotvrzeniRodicu')) {
    if (!$u->zpracujPotvrzeniRodicu()) {
        chyba('Nejdříve vlož potvrzení.');
    } else {
        oznameni('Potvrzení bylo uloženo.');
    }
    back();
}

if (po(GC_BEZI_DO)) {
    if ($u && $u->gcPritomen()) {
        $t->parse('prihlaskaPoGc.ucastnilSe');
    } else {
        $t->assign('rok', $systemoveNastaveni->rocnik() + 1);
        $t->parse('prihlaskaPoGc.neucastnilSe');
    }
    $t->parse('prihlaskaPoGc');

    return;
}

if (VYZADOVANO_COVID_POTVRZENI && $u && ($systemoveNastaveni->gcBezi() || $u->gcPritomen())) {
    $t->assign('covidSekce', $covidSekceFunkce(new Shop($u, $u, $systemoveNastaveni)));
    $t->parse('prihlaskaUzavrena.covidSekce.doklad');
    $letosniRok = (int)date('Y');
    if (!$u->maNahranyDokladProtiCoviduProRok($letosniRok) && !$u->maOverenePotvrzeniProtiCoviduProRok($letosniRok)) {
        $t->parse('prihlaskaUzavrena.covidSekce.submit');
    }
    $t->parse('prihlaskaUzavrena.covidSekce');
}

if (!$u?->gcPrihlasen() && po($systemoveNastaveni->prihlasovaniUcastnikuDo())) {
    $t->assign('konec', $systemoveNastaveni->prihlasovaniUcastnikuDo()->format(DateTimeCz::FORMAT_DATUM_A_CAS_STANDARD));
    $t->parse('prihlaskaPo');

    return;
}

if ($systemoveNastaveni->gcBezi()) {
    if ($u?->gcPritomen()) {
        $t->parse('prihlaskaUzavrena.proselInfopultem');
        $t->parse('prihlaskaUzavrena');

        return;
    }
    if ($u?->gcPrihlasen()) {
        $t->parse('prihlaskaUzavrena.neproselInfopultem');
        $t->parse('prihlaskaUzavrena');

        return;
    }
}

if (!$u) {
    oznameniPresmeruj(
        'Tato stránka vyžaduje přihlášení',
        URL_WEBU . '/prihlaseni',
        Chyba::VAROVANI
    );
}

if (pred($systemoveNastaveni->prihlasovaniUcastnikuOd())) {
    $t->assign('zacatek', $systemoveNastaveni->rocnik() < date('Y')
        ? '(upřesníme)'
        // ještě jsme nepřeklopili ročník
        : $systemoveNastaveni->prihlasovaniUcastnikuOd()->formatCasZacatekUdalosti());
    $t->parse('prihlaskaPred');

    return;
}

$shop  = new Shop($u, $u, $systemoveNastaveni);
$pomoc = new Pomoc($u);

if (post('odhlasit')) {
    if (po($systemoveNastaveni->gcBeziOd())) {
        $sama = $u->jeZena()
            ? 'sama'
            : 'sám';
        chyba("Během Gameconu se nemůžeš $sama odhlást. Stav se na infopultu.");
    } else {
        $u->odhlasZGc('rucne-sam-sebe', $u);
        oznameni(hlaska('odhlaseniZGc', $u));
    }
}

if (post('prihlasitNeboUpravit')) {
    $prihlasovani = false;
    if (!$u->gcPrihlasen()) {
        $prihlasovani = true;
        $u->gcPrihlas($u);
    }
    $shop->zpracujPredmety();
    $shop->zpracujUbytovani();
    $shop->zpracujJidlo();
    $shop->zpracujVstupne();
    $u->zpracujPotvrzeniProtiCovidu();
    $pomoc->zpracuj();
    if ($prihlasovani) {
        oznameni(hlaska('prihlaseniNaGc', $u));
    } else {
        oznameni(hlaska('aktualizacePrihlasky'));
    }
}

// informace o slevách (jídlo nevypisovat, protože tabulka správně vypisuje cenu po slevě)
$slevy = $u->finance()->slevyVse();
$slevy = array_diff($slevy, ['jídlo zdarma', 'jídlo se slevou']);
if ($slevy) {
    $t->assign([
        'slevy' => implode(', ', $slevy),
        'titul' => mb_strtolower($u->status()),
    ]);
    $t->parse('prihlaska.slevy');
}

if (!$u->maPravo(Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC)){
    $t->parse('prihlaska.ubytovaniTriPlusNoci');
    if ((int)date('Y') === 2025){
       $t->parse('prihlaska.triPlusNoci2025');
    }
}

if ($u->jeOrganizator()) {
    $t->parse('prihlaska.poznamkaKUbytovaniVNedeli');
}

$t->assign('ka', $u->koncovkaDlePohlavi()
    ? 'ka'
    : '');
if ($u->maPravo(Pravo::UBYTOVANI_ZDARMA)) {
    $t->parse('prihlaska.ubytovaniInfoOrg');
} elseif ($u->maPravo(Pravo::PORADANI_AKTIVIT) && !$u->maPravo(Pravo::BEZ_SLEVY_ZA_VEDENI_AKTIVIT)) {
    $t->parse('prihlaska.ubytovaniInfoVypravec');
}

$adresarKObrazkuPredmetu = adresarKObrazkuEshopPredmetu();
if (is_dir($adresarKObrazkuPredmetu)) {
    foreach (scandir(adresarKObrazkuEshopPredmetu()) as $soubor) {
        if ($soubor === '.' || $soubor === '..') {
            continue;
        }
        $cestaKObrazku = cestaKObrazkuEshopPredmetu($soubor);
        if (!is_file($cestaKObrazku)) {
            continue;
        }
        if (!Obrazek::jeToPodporovanyObrazek($cestaKObrazku)) {
            continue;
        }
        $chybiObrazek  = false;
        try {
            $obrazek = nahledPredmetu($cestaKObrazku);
        } catch (\RuntimeException $runtimeException) {
            $obrazek      = $cestaKObrazku;
            $chybiObrazek = true;
        }

        $chybiMiniatura = false;
        try {
            $miniatura = miniauturaNahleduPredmetu($cestaKObrazku);
        } catch (\RuntimeException $runtimeException) {
            $miniatura      = $cestaKObrazku;
            $chybiMiniatura = true;
        }
        $bezPripony = basename($soubor, '.' . pathinfo($soubor, PATHINFO_EXTENSION));
        $nazev      = trim(
            preg_replace(
                '~^\d+~', // odstraníme pořadové číslo souboru
                '',
                preg_replace(
                    '~[^[:alnum:]]+~u',
                    ' ',
                    $bezPripony
                )
            )
        );

        $t->assign([
            'obrazek'   => $obrazek,
            'miniatura' => $miniatura,
            'nazev'     => $nazev,
            'display'   => ($chybiObrazek || $chybiMiniatura) && (!$u || !$u->maPravo(Pravo::ADMINISTRACE_INFOPULT))
                ? 'none'
                : 'inherit',
        ]);
        $t->parse('prihlaska.nahled');
    }
}

$t->assign([
    'a'                               => $u->koncovkaDlePohlavi(),
    'jidlo'                           => $shop->jidloHtml(),
    'jidloObjednatelneDo'             => $shop->jidloObjednatelneDoHtml(),
    'predmety'                        => $shop->predmetyHtml(),
    'trickaObjednatelnaDo'            => $shop->trickaObjednatelnaDoHtml(),
    'predmetyBezTricekObjednatelneDo' => $shop->predmetyBezTricekObjednatelneDoHtml(),
    'rok'                             => ROCNIK,
    'ubytovani'                       => $shop->ubytovaniHtml(),
    'ubytovaniObjednatelneDo'         => $shop->ubytovaniObjednatelneDoHtml(),
    'covidSekce'                      => VYZADOVANO_COVID_POTVRZENI
        ? $covidSekceFunkce($shop)
        : '',
    'ulozitNeboPrihlasit'             => $u->gcPrihlasen()
        ? 'Uložit změny'
        : 'Přihlásit na GameCon',
    'vstupne'                         => $shop->vstupneHtml(),
    'pomoc'                           => $pomoc->html(),
    'zaplatitNejpozdejiDo'            => $systemoveNastaveni->nejpozdejiZaplatitDo()->format(DateTimeCz::FORMAT_DATUM_LETOS),
]);

if ($u->gcPrihlasen()) {
    if ($u->vekKDatu($systemoveNastaveni->gcBeziOd()) < 15 &&
        ((!$u->potvrzeniZakonnehoZastupceOd()) ||
            $u->potvrzeniZakonnehoZastupceOd()->format('Y') != $systemoveNastaveni->rocnik())) {
        if ($u->potvrzeniZakonnehoZastupceSouborOd() && ((!$u->potvrzeniZakonnehoZastupceOd()) || $u->potvrzeniZakonnehoZastupceSouborOd() > ($u->potvrzeniZakonnehoZastupceOd()))) {
            $t->parse('prihlaska.prihlasen.potvrzeniZakonnyZastupce.nahrano');
        }
        $t->assign('urlWebu', URL_WEBU);
        $t->assign('rocnik', $systemoveNastaveni->rocnik());
        $t->parse('prihlaska.prihlasen.potvrzeniZakonnyZastupce');
    }
    $t->parse('prihlaska.prihlasen');
    if (pred($systemoveNastaveni->gcBeziOd())) {
        $t->parse('prihlaska.odhlasit');
    }
} else {
    $t->parse('prihlaska.neprihlasen');
}
