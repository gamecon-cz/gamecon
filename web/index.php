<?php

declare(strict_types=1);

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Dev\SsoPrihlaseniZUrl;
use Gamecon\Vyjimkovac\Vyjimkovac;
use Gamecon\Web\Info;
use Gamecon\Web\Motiv\BarvyWebuRepository;
use Gamecon\Web\Motiv\MotivWebu;
use Gamecon\Web\Motiv\NahledMotivuWebu;
use Gamecon\Web\Motiv\NaplanovaneMotivyWebuRepository;
use Gamecon\Web\OznameniNaWebu\OznameniNaWebuHtml;
use Gamecon\Web\OznameniNaWebu\OznameniNaWebuRepository;
use Gamecon\Web\OznameniNaWebu\SkrytaOznameniNaWebu;
use Gamecon\Web\VzhledWebu;
use Gamecon\Web\VzhledWebuEnum;
use Gamecon\XTemplate\XTemplate;

require __DIR__ . '/../nastaveni/zavadec.php';
require __DIR__ . '/tridy/modul.php';
require __DIR__ . '/tridy/vyjimky.php';

if (HTTPS_ONLY) {
    httpsOnly();
}
omezCsrf();

$u = Uzivatel::zSession();
SsoPrihlaseniZUrl::zpracuj($u);

/** @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

// Postupná migrace na Symfony: vybrané veřejné cesty obsluhuje Symfony
// controller. Kernel bootujeme JEN pro tyhle cesty (jsou pro nepřihlášené
// uživatele), ať neplatíme režii na každém veřejném requestu. Legacy router
// pracuje s $_GET['req'] (Apache rewrite z .htaccess), takže i tady cestu
// bereme odtud, ne z REQUEST_URI (to nese prefix /web).
$symfonyVerejneCesty = ['/zapomenute-heslo', '/obnova-hesla', '/altcha-challenge'];
$aktualniCesta = '/' . trim((string) ($_GET['req'] ?? ''), '/');
if (in_array($aktualniCesta, $symfonyVerejneCesty, true)) {
    require __DIR__ . '/_symfony.php';
}

try {
    $url = Url::zAktualni();
} catch (UrlException $e) {
    $url = null;
}

// vzhled webu (klasický / moderní), zavřená oznámení a náhled motivu pro grafika (jen s právem na web v adminu)
$aktualniStranka = URL_WEBU . '/' . ($url?->cela() ?? '');
$aktualniQuery   = array_diff_key($_GET, ['req' => true]);
$vzhledWebu      = new VzhledWebu($_COOKIE);
$skrytaOznameni  = new SkrytaOznameniNaWebu($_COOKIE);
$nahledMotivu    = NahledMotivuWebu::smiNahlizet($u)
    ? new NahledMotivuWebu($_COOKIE)
    : null;
// volba z odkazu (?vzhled=…, ?skrytOznameni=…, ?nahledMotivu=…) se zapamatuje v cookie a stránka se načte znovu bez ní
$volbyZOdkazu = [VzhledWebu::GET_KLIC => true, SkrytaOznameniNaWebu::GET_KLIC => true, NahledMotivuWebu::GET_KLIC => true];
if (array_intersect_key($_GET, $volbyZOdkazu) !== []) {
    if (isset($_GET[VzhledWebu::GET_KLIC])) {
        $vzhledWebu->zpracujVolbu((string) $_GET[VzhledWebu::GET_KLIC]);
    }
    if (isset($_GET[SkrytaOznameniNaWebu::GET_KLIC])) {
        $skrytaOznameni->zpracujSkryti((string) $_GET[SkrytaOznameniNaWebu::GET_KLIC]);
    }
    if (isset($_GET[NahledMotivuWebu::GET_KLIC])) {
        if ($nahledMotivu !== null) {
            $nahledMotivu->zpracujVolbu((string) $_GET[NahledMotivuWebu::GET_KLIC]);
        } else {
            Chyba::nastav('Náhled barev a motivů webu vidí jen přihlášení s právem na web v adminu.', Chyba::CHYBA);
        }
    }
    back(VzhledWebu::getUrlBezVolby($aktualniStranka, array_diff_key($aktualniQuery, $volbyZOdkazu)));
}
$vzhled = $vzhledWebu->getVzhledStranky($_GET);

// určení modulu, který zpracuje požadavek (router)
$m = $url
    ? Modul::zUrl(null, $systemoveNastaveni)
    : Modul::zNazvu('nenalezeno', null, $systemoveNastaveni);
if (! $m && ($stranka = Stranka::zUrl())) {
    if ($stranka->isRedirect()) {
        header('Location: ' . $stranka->redirectUrl(), true, 301);
        exit;
    }
    $m = Modul::zNazvu('stranka', null, $systemoveNastaveni);
    $m->param('stranka', $stranka);
}
if (! $m && (($typ = TypAktivity::zUrl()) || ($org = Uzivatel::zUrl()))) {
    $m = Modul::zNazvu('aktivity', null, $systemoveNastaveni);
    $m->param('typ', $typ ?: null);
    $m->param('org', ! $typ ? $org : null);
}
if (! $m) {
    $m = Modul::zNazvu('nenalezeno', null, $systemoveNastaveni);
}

// spuštění kódu modulu + buffering výstupu a nastavení
$m->setVzhled($vzhled);
$m->param('u', $u);
$m->param('url', $url);
if (($url?->cast(0) ?? null) === 'api') {
    $m->bezStranky(true);
}
$i = (new Info($systemoveNastaveni))
    ->obrazek('soubory/styl/og-image.jpg')
    ->site('GameCon')
    ->url("http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
$m->info($i);

try {
    $m->spust();
} catch (Nenalezeno $e) {
    $m = Modul::zNazvu('nenalezeno', null, $systemoveNastaveni)->setVzhled($vzhled)->spust();
} catch (Neprihlasen $e) {
    $m = Modul::zNazvu('neprihlasen', null, $systemoveNastaveni)->setVzhled($vzhled)->spust();
}

// sestavení menu
$menu = '';
if (! $m->bezStranky() && ! $m->bezMenu()) {
    $menu = Gamecon\Web\MenuWebu::html($u, $vzhled);
}

// výstup (s ohledem na to co modul nastavil)
if ($m->bezStranky()) {
    echo $m->vystup();

    return;
}

$t = new XTemplate(__DIR__ . '/sablony/' . $vzhled->getAdresarSablon() . '/index.xtpl');
$t->assign([
    'css'          => perfectcache('soubory/blackarrow/*/*.less'),
    'jsVyjimkovac' => Vyjimkovac::js(URL_WEBU),
    'chyba'        => Chyba::vyzvedniHtml(),
    'menu'         => $menu,
    'obsah'        => $m->vystup(),
    'base'         => URL_WEBU . '/',
    'info'         => $m->info() ? $m->info()->html() : '',
    'letosniRok'   => date('Y'),
]);
// moderní vzhled (klasická kostra z toho používá jen odkaz na přepnutí)
$t->assign([
    'moderniCss'     => 'soubory/moderni/moderni.css?version=' . md5_file(__DIR__ . '/soubory/moderni/moderni.css'),
    'moderniJs'      => 'soubory/moderni/moderni.js?version=' . md5_file(__DIR__ . '/soubory/moderni/moderni.js'),
    'urlKlasicky'    => htmlspecialchars(VzhledWebu::getUrlPrepnuti($aktualniStranka, $aktualniQuery, VzhledWebuEnum::KLASICKY)),
    'urlModerni'     => htmlspecialchars(VzhledWebu::getUrlPrepnuti($aktualniStranka, $aktualniQuery, VzhledWebuEnum::MODERNI)),
    'tridaModulu'    => 'mw-modul-' . $m->getNazev(),
    'moderniSablona' => $m->maModerniSablonu() ? 'mw-moderni-obsah' : 'mw-klasicky-obsah',
    'tridaVarianty'  => 'mw-varianta-' . strtolower((string) $vzhled->getPismenoVarianty()),
]);
// vlastní styly a skript varianty moderního vzhledu, když je má (soubory/moderni/varianty/<a>.css|js)
foreach (['css' => 'index.extraCss', 'js' => 'index.extraJs'] as $pripona => $blok) {
    $souborVarianty = $vzhled->getSouborVarianty($pripona);
    if ($souborVarianty !== null && is_file(__DIR__ . '/' . $souborVarianty)) {
        $t->assign('url', $souborVarianty . '?version=' . md5_file(__DIR__ . '/' . $souborVarianty));
        $t->parse($blok);
    }
}
$t->parseEach($m->cssUrls(), 'url', 'index.extraCss');
$t->parseEach($m->jsUrls(), 'url', 'index.extraJs');
if (! $m->bezPaticky()) {
    if ($u?->jeOrganizator()) {
        $t->parse('index.paticka.qrka');
    }
    // přepínač variant A–D, má ho jen moderní kostra
    if ($vzhled->isModerni()) {
        foreach (VzhledWebuEnum::getModerni() as $varianta) {
            $t->assign([
                'url'      => htmlspecialchars(VzhledWebu::getUrlPrepnuti($aktualniStranka, $aktualniQuery, $varianta)),
                'pismeno'  => $varianta->getPismenoVarianty(),
                'nazev'    => htmlspecialchars((string) $varianta->getNazevVarianty()),
                'aktualni' => $varianta === $vzhled ? 'aria-current="true"' : '',
            ]);
            $t->parse('index.paticka.varianta');
        }
    }
    $t->parse('index.paticka');
}

// oznámení z adminu (Web → Oznámení na webu) bez těch, která návštěvník zavřel
$t->assign('oznameni', OznameniNaWebuHtml::html(
    $skrytaOznameni->neskryta((new OznameniNaWebuRepository())->zobrazovana($systemoveNastaveni->ted())),
    $vzhled,
    $aktualniStranka,
    $aktualniQuery,
));

// barvy ročníku a speciální motiv (Web → Barvy a motivy webu), používá je jen moderní kostra
$motivWebu = MotivWebu::zjisti(
    new BarvyWebuRepository(),
    new NaplanovaneMotivyWebuRepository(),
    $systemoveNastaveni->ted(),
    $nahledMotivu,
);
$t->assign([
    'motivWebu'       => $motivWebu->getHtmlDoHlavicky(__DIR__),
    'barvaProhlizece' => $motivWebu->getBarvaProhlizece(),
]);
if ($motivWebu->popisNahledu !== null) {
    $t->assign([
        'popisNahleduMotivu'    => htmlspecialchars($motivWebu->popisNahledu),
        'urlKonceNahleduMotivu' => htmlspecialchars(NahledMotivuWebu::getUrlKonce($aktualniStranka, $aktualniQuery)),
    ]);
    $t->parse('index.nahledMotivu');
}

$ribbonLabel = $systemoveNastaveni->prostredi()->ribbonLabel();
if ($ribbonLabel !== '') {
    $t->assign('ribbonLabel', $ribbonLabel);
    $t->parse('index.ribbon');
}
$t->parse('index');
$t->out('index');
profilInfo();
