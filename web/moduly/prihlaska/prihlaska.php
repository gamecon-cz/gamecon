<?php

use Gamecon\Cas\DateTimeCz;
use Gamecon\Shop\Shop;
use Gamecon\Cas\DateTimeGamecon;

/**
 * @var \Gamecon\XTemplate\XTemplate $t
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$this->pridejJsSoubor('soubory/blackarrow/prihlaska/prihlaska.js');
$this->blackarrowStyl(true);
$this->bezPaticky(true);
$this->info()->nazev('Přihláška');

$covidSekceFunkce = require __DIR__ . '/covid-sekce-funkce.php';

function cestaKObrazkuPredmetu(string $soubor): string {
    return WWW . '/soubory/obsah/materialy/' . ROCNIK . '/' . $soubor;
}

/**
 * @throws \RuntimeException
 * Pomocná funkce pro náhled předmětu pro aktuální ročník
 */
function nahledPredmetu(string $cestaKObrazku) {
    return Nahled::zSouboru($cestaKObrazku)->kvalita(98)->url();
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
            echo json_encode(['covidSekce' => $covidSekceFunkce($u->dejShop())]);
            exit;
        }
        oznameni('Potvrzení bylo uloženo.');
    }
}

if (po(GC_BEZI_DO)) {
    if ($u && $u->gcPritomen()) {
        $t->parse('prihlaskaPo.ucastnilSe');
    } else {
        $t->assign('rok', ROCNIK + 1);
        $t->parse('prihlaskaPo.neucastnilSe');
    }
    $t->parse('prihlaskaPo');
    return;
}

if (VYZADOVANO_COVID_POTVRZENI && $u && (GC_BEZI || $u->gcPritomen())) {
    $t->assign('covidSekce', $covidSekceFunkce(new Shop($u, null, $systemoveNastaveni)));
    $t->parse('prihlaskaUzavrena.covidSekce.doklad');
    $letosniRok = (int)date('Y');
    if (!$u->maNahranyDokladProtiCoviduProRok($letosniRok) && !$u->maOverenePotvrzeniProtiCoviduProRok($letosniRok)) {
        $t->parse('prihlaskaUzavrena.covidSekce.submit');
    }
    $t->parse('prihlaskaUzavrena.covidSekce');
}

if (GC_BEZI) {
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
    $t->parse('prihlaskaUzavrena.gcBezi');
    $t->parse('prihlaskaUzavrena');
    return;
}

if (!$u) {
    // Mimo období kdy GC běží: Situaci uživateli vždy dostatečně vysvětlí
    // registrační stránka. A umožní mu aspoň vytvořit si účet.
    back(URL_WEBU . '/registrace');
}

if (pred(REG_GC_OD)) {
    $t->assign('zacatek', ROCNIK < date('Y')
        ? '(upřesníme)' // ještě jsme nepřeklopili ročník
        : DateTimeGamecon::zacatekRegistraciUcastniku()->formatCasZacatekUdalosti());
    $t->parse('prihlaskaPred');
    return;
}

$shop  = new Shop($u, null, $systemoveNastaveni);
$pomoc = new Pomoc($u);

if (post('odhlasit')) {
    $u->gcOdhlas($u);
    oznameni(hlaska('odhlaseniZGc', $u));
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

$t->assign('ka', $u->koncovkaDlePohlavi() ? 'ka' : '');
if ($u->maPravo(P_UBYTOVANI_ZDARMA)) {
    $t->parse('prihlaska.ubytovaniInfoOrg');
} else if ($u->maPravo(P_ORG_AKTIVIT) && !$u->maPravo(P_NEMA_BONUS_ZA_AKTIVITY)) {
    $t->parse('prihlaska.ubytovaniInfoVypravec');
}

// náhledy
$nahledy = [
    ['obrazek' => 'Triko.jpg', 'miniatura' => 'Triko_detail.jpg', 'nazev' => 'Tričko'],
    ['obrazek' => 'Tilko.jpg', 'miniatura' => 'Tilko_detail.jpg', 'nazev' => 'Tílko'],
    ['obrazek' => 'Kostka_Duna_2022.png', 'miniatura' => 'Kostka_Duna_2022_detail.png', 'nazev' => ROCNIK === 2022 ? 'Kostka' : 'Kostka Duna'],
    ['obrazek' => 'Kostka_Cthulhu_2021.png', 'miniatura' => 'Kostka_Cthulhu_2021_detail.png', 'nazev' => 'Kostka Cthulhu'],
    ['obrazek' => 'Kostka_Fate_2019.png', 'miniatura' => 'Kostka_Fate_2019_detail.png', 'nazev' => 'Fate kostka'],
    ['obrazek' => 'Placka.png', 'miniatura' => 'Placka_detail.png', 'nazev' => 'Placka'],
    ['obrazek' => 'nicknack.jpg', 'miniatura' => 'nicknack_m.jpg', 'nazev' => 'Nicknack'],
    ['obrazek' => 'Ponozky.png', 'miniatura' => 'Ponozky_detail.png', 'nazev' => 'Ponožky'],
    ['obrazek' => 'Taska.jpg', 'miniatura' => 'Taska_detail.jpg', 'nazev' => 'Taška'],
    ['obrazek' => 'Blok.jpg', 'miniatura' => 'Blok_detail.jpg', 'nazev' => 'Taška'],
];
foreach ($nahledy as $nahled) {
    $cestaKObrazku = cestaKObrazkuPredmetu($nahled['obrazek']);
    $chybiObrazek  = false;
    try {
        $obrazek = nahledPredmetu($cestaKObrazku);
    } catch (\RuntimeException $runtimeException) {
        $obrazek      = $cestaKObrazku;
        $chybiObrazek = true;
    }

    $cestaKMiniature = cestaKObrazkuPredmetu($nahled['miniatura']);
    $chybiMiniatura  = false;
    try {
        $miniatura = nahledPredmetu($cestaKMiniature);
    } catch (\RuntimeException $runtimeException) {
        $miniatura      = $cestaKObrazku;
        $chybiMiniatura = true;
    }

    $t->assign([
        'obrazek'   => $obrazek,
        'miniatura' => $miniatura,
        'nazev'     => $nahled['nazev'],
        'display'   => ($chybiObrazek || $chybiMiniatura) && (!$u || !$u->maPravo(\Gamecon\Pravo::ADMINISTRACE_INFOPULT))
            ? 'none'
            : 'inherit',
    ]);
    $t->parse('prihlaska.nahled');
}

$qrObrazekProPlatbu = $u->finance()->dejQrKodProPlatbu();

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
    'covidSekce'                      => VYZADOVANO_COVID_POTVRZENI ? $covidSekceFunkce($shop) : '',
    'qrPlatbaMimeType'                => $qrObrazekProPlatbu->getMimeType(),
    'qrPlatbaBase64'                  => base64_encode($qrObrazekProPlatbu->getString()),
    'ulozitNeboPrihlasit'             => $u->gcPrihlasen()
        ? 'Uložit změny'
        : 'Přihlásit na GameCon',
    'vstupne'                         => $shop->vstupneHtml(),
    'pomoc'                           => $pomoc->html(),
    'zaplatitNejpozdejiDo'            => DateTimeGamecon::zacatekNejblizsiVlnyOdhlasovani()->format('j. n.'),
]);

$t->parse($u->gcPrihlasen()
    ? 'prihlaska.prihlasen'
    : 'prihlaska.neprihlasen');
if ($u->gcPrihlasen()) {
    $t->parse('prihlaska.odhlasit');
}
