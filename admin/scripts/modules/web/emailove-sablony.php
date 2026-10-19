<?php

use Gamecon\Admin\PosledniZmenaHtml;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Kanaly\EmailovaSablona\EditorSablonyHtml;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaKontrola;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaRepository;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaValidator;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaVykreslovac;
use Gamecon\Kanaly\EmailovaSablona\EmailoveSablonyKatalog;
use Gamecon\Kanaly\EmailovaSablona\ProblemSablony;
use Gamecon\Kanaly\EmailovaSablona\ProblemySablonHtml;
use Gamecon\Kanaly\EmailovaSablona\RozkladSymbolu;
use Gamecon\Kanaly\EmailovaSablona\SystemoveHodnotyProSablony;
use Gamecon\Kanaly\EmailovaSablona\UlozenaEmailovaSablona;
use Gamecon\XTemplate\XTemplate;

/**
 * Předmět a text e-mailů, které GameCon posílá
 *
 * nazev: Texty e-mailů
 * pravo: 105
 */

/**
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$urlModulu        = 'web/emailove-sablony';
$repository       = new EmailovaSablonaRepository();
$systemoveHodnoty = new SystemoveHodnotyProSablony($systemoveNastaveni);
$validator        = new EmailovaSablonaValidator($systemoveHodnoty);

$kdoAKdyZmenil = static function (UlozenaEmailovaSablona $sablona): string {
    $kdy = $sablona->zmenenoKdy
        ? DateTimeCz::createFromInterface($sablona->zmenenoKdy)->formatCasNaMinutyStandard()
        : '';
    $kdo = Uzivatel::zId($sablona->zmenil ?? Uzivatel::SYSTEM)?->jmenoNick();

    return trim($kdy . ($kdo
            ? " ($kdo)"
            : ''));
};

$zmenaProSeznam = static function (?UlozenaEmailovaSablona $sablona): array {
    if ($sablona === null || $sablona->zmenenoKdy === null) {
        return ['trida' => '', 'obsah' => ''];
    }

    return [
        'trida' => 'hinted',
        'obsah' => PosledniZmenaHtml::bunka($sablona->zmenenoKdy, Uzivatel::zId($sablona->zmenil)),
    ];
};

$kod = get('kod');
if ($kod !== null) {
    $kod = (string)$kod;
    if (!EmailoveSablonyKatalog::existuje($kod)) {
        oznameniPresmeruj('Takový e-mail neznáme.', URL_ADMIN . '/' . $urlModulu, Chyba::CHYBA);
    }
    $definice = EmailoveSablonyKatalog::dej($kod);

    // The editor asks for a preview of text it is still typing, not yet saved
    if (get('nahled') !== null) {
        $nahlizenyPredmet = trim((string)post('predmet'));
        $nahlizenyText    = trim((string)post('text'));

        $nahled = new XTemplate(__DIR__ . '/emailove-sablony-nahled.xtpl');
        if (RozkladSymbolu::jePrazdny($nahlizenyPredmet) || RozkladSymbolu::jePrazdny($nahlizenyText)) {
            $nahled->assign('predmet', '(nevyplněno)');
            $nahled->assign('telo', '<p>Vyplň předmět i text, pak bude co ukázat.</p>');
        } else {
            $ukazky = [];
            foreach ($definice->promenne as $promenna) {
                $ukazky[$promenna->nazev] = $promenna->ukazka;
            }
            $vykresleno = (new EmailovaSablonaVykreslovac($systemoveHodnoty))
                ->vykresli($definice, $nahlizenyPredmet, $nahlizenyText, $ukazky);

            $nahled->assign('predmet', htmlspecialchars($vykresleno->predmet, ENT_QUOTES));
            // An HTML e-mail is shown in a sandboxed frame so its styles and scripts cannot touch the admin.
            $nahled->assign(
                'telo',
                $definice->jeHtml()
                    ? '<iframe sandbox srcdoc="' . htmlspecialchars('<html><body>' . $vykresleno->text . '</body></html>', ENT_QUOTES) . '"></iframe>'
                    : '<pre>' . htmlspecialchars($vykresleno->text, ENT_QUOTES) . '</pre>',
            );
        }
        $nahled->parse('nahled');
        $nahled->out('nahled');

        // exit, not $BEZ_DEKORACE: the preview fragment is returned on its own, with nothing of the admin page around it
        exit;
    }

    $problemy = EmailovaSablonaKontrola::zNastaveni($systemoveNastaveni)->najdiProblemy();
    $ulozena  = $repository->dej($kod);
    $predmet = $ulozena?->predmet ?? '';
    $text    = $ulozena?->text ?? '';
    $chyby   = [];

    if (post('ulozit')) {
        $predmet = (string)post('predmet');
        $text    = (string)post('text');
        $chyby   = $validator->validuj($definice, $predmet, $text);
        if ($chyby === []) {
            $repository->uloz($kod, $predmet, $text, $u->id());
            oznameniPresmeruj('Text e-mailu uložen', URL_ADMIN . '/' . $urlModulu . '?kod=' . rawurlencode($kod));
        }
    }

    // Another tab may have changed the text meanwhile; without this, Back would show the old version.
    header('Cache-Control: no-store');

    $sablonaStranky = new XTemplate(__DIR__ . '/emailove-sablony-detail.xtpl');
    $sablonaStranky->assign([
        'nazevSloupcePosledniZmena' => PosledniZmenaHtml::NAZEV_SLOUPCE,
        'problemy'   => ProblemySablonHtml::vykresli(array_values(array_filter(
            $problemy,
            static fn(ProblemSablony $problem) => $problem->kod === $kod,
        ))),
        'nazev'      => htmlspecialchars($definice->nazev, ENT_QUOTES),
        'skupina'    => htmlspecialchars($definice->skupina, ENT_QUOTES),
        'kdyOdchazi' => htmlspecialchars($definice->kdyOdchazi, ENT_QUOTES),
        'prijemce'   => htmlspecialchars($definice->prijemce, ENT_QUOTES),
        'format'     => $definice->jeHtml()
            ? 'HTML (nové řádky se převedou na odřádkování, odkazy a tučné písmo jde psát značkami)'
            : 'prostý text',
        'zmeneno'    => $ulozena
            ? htmlspecialchars($kdoAKdyZmenil($ulozena), ENT_QUOTES)
            : 'nikdy',
        'editor'     => (new EditorSablonyHtml($systemoveHodnoty))->vykresli(
            $definice,
            $predmet,
            $text,
            $chyby,
            $urlModulu . '?kod=' . rawurlencode($kod) . '&nahled',
            $urlModulu,
            'Zpět na seznam e-mailů',
        ),
    ]);
    $sablonaStranky->parse('detail');
    $sablonaStranky->out('detail');

    return;
}

$problemy       = EmailovaSablonaKontrola::zNastaveni($systemoveNastaveni)->najdiProblemy();
$sablonaStranky = new XTemplate(__DIR__ . '/emailove-sablony.xtpl');
$sablonaStranky->assign('problemy', ProblemySablonHtml::vykresli($problemy));
$sablonaStranky->assign('nazevSloupcePosledniZmena', PosledniZmenaHtml::NAZEV_SLOUPCE);

$ulozene          = $repository->vsechny();
$kodyProblemovych = array_flip(array_map(
    static fn(ProblemSablony $problem) => $problem->kod,
    $problemy,
));

$definicePodleSkupin = [];
foreach (EmailoveSablonyKatalog::vsechny() as $definice) {
    $definicePodleSkupin[$definice->skupina][] = $definice;
}

foreach ($definicePodleSkupin as $skupina => $definiceSkupiny) {
    $sablonaStranky->assign('skupina', htmlspecialchars($skupina, ENT_QUOTES));
    foreach ($definiceSkupiny as $definice) {
        $sablona = $ulozene[$definice->kod] ?? null;
        $zmena   = $zmenaProSeznam($sablona);
        $sablonaStranky->assign([
            'kod'              => htmlspecialchars($definice->kod, ENT_QUOTES),
            'odkaz'            => htmlspecialchars(EmailovaSablonaKontrola::odkazNaEditaci($definice->kod), ENT_QUOTES),
            'nazev'            => htmlspecialchars($definice->nazev, ENT_QUOTES),
            'kdyOdchazi'       => htmlspecialchars($definice->kdyOdchazi, ENT_QUOTES),
            'prijemce'         => htmlspecialchars($definice->prijemce, ENT_QUOTES),
            'predmet'          => htmlspecialchars($sablona?->predmet ?? '(chybí)', ENT_QUOTES),
            'zmenenoTrida'     => $zmena['trida'],
            'zmenenoObsah'     => $zmena['obsah'],
            'problem'          => isset($kodyProblemovych[$definice->kod])
                ? '<span title="Text chybí nebo obsahuje hodnotu, která se nedosadí">⚠️</span> '
                : '',
        ]);
        $sablonaStranky->parse('seznam.skupina.sablona');
    }
    $sablonaStranky->parse('seznam.skupina');
}
$sablonaStranky->parse('seznam');
$sablonaStranky->out('seznam');
