<?php

use Gamecon\Command\FioStazeniNovychPlateb;
use Gamecon\Logger\JobResultLogger;
use Gamecon\Role\Role;
use Gamecon\Uzivatel\Dto\Dluznik;
use Gamecon\Uzivatel\Dto\OdeslanaUpominka;
use Gamecon\Uzivatel\Enum\TypUpominky;
use Gamecon\Uzivatel\Enum\UcastNaGc;
use Gamecon\Uzivatel\UpominaniDluzniku;
use Gamecon\Uzivatel\UpominkaDluznikaLog;
use Gamecon\Uzivatel\UpominkaVlastniZneni;
use Gamecon\XTemplate\XTemplate;

/**
 * Ruční rozeslání upomínek dlužníkům
 *
 * nazev: Upomínky dlužníkům
 * pravo: 108
 * submenu_group: 5
 */

/**
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 * @var bool $BEZ_DEKORACE
 */

// Právo 108 má i agenda mimo finance; rozesílání peněz vymáhajících mailů
// patří jen CFO.
if (!$u->maRoli(Role::CFO)) {
    chyba('Upomínky dlužníkům jsou jen pro CFO.');

    return;
}

$rocnik              = $systemoveNastaveni->rocnik();
$upominkaDluznikaLog = new UpominkaDluznikaLog();
$jobResultLogger     = new JobResultLogger();
// Jedna instance pro celý request, aby se znění nečetlo znovu pro každého
// z ~200 dlužníků a aby předmět s textem vycházely ze stejného řádku.
$upominkaVlastniZneni = new UpominkaVlastniZneni();
$upominaniDluzniku    = new UpominaniDluzniku(
    $systemoveNastaveni,
    $jobResultLogger,
    new FioStazeniNovychPlateb($systemoveNastaveni, $jobResultLogger),
    $upominkaDluznikaLog,
    upominkaVlastniZneni: $upominkaVlastniZneni,
);

if (get('zneni') !== null) {
    if (post('ulozit')) {
        $upominkaVlastniZneni->uloz(
            $rocnik,
            trim((string) post('predmet')),
            trim((string) post('text')),
            $u->id(),
        );
        oznameniPresmeruj('Vlastní znění uloženo', URL_ADMIN . '/finance/upominky-dluzniku?zneni');
    }

    $zneni = $upominkaVlastniZneni->dejZneni($rocnik);

    // Náhled na sobě samém - CFO si chce ověřit, jak text vypadá po dosazení,
    // a vlastní účet je jediný, u kterého má reálné jméno, VS i pohlaví.
    if (get('nahled') !== null) {
        // Náhled se dělá na tom, co je právě v editoru, ne na uloženém znění -
        // jinak by nešlo zkusit text, než ho CFO uloží. Prázdné pole z editoru
        // dorazí jako '', takže se na uložené znění spadne jen při GET bez těla.
        $nahlizenyPredmet = trim((string) (post('predmet') ?? $zneni?->predmet ?? ''));
        $nahlizenyText    = trim((string) (post('text') ?? $zneni?->text ?? ''));

        $nahled = new XTemplate(__DIR__ . '/upominky-dluzniku-nahled.xtpl');
        $nahled->assign('jmenoNick', htmlspecialchars((string) $u->jmenoNick(), ENT_QUOTES));
        $nahled->assign('mail', htmlspecialchars((string) $u->mail(), ENT_QUOTES));

        if ($nahlizenyPredmet === '' || $nahlizenyText === '') {
            $nahled->assign('predmet', '(nevyplněno)');
            $nahled->assign('zprava', 'Vyplň předmět i text, pak bude co ukázat.');
        } else {
            // Dluh je jediná hodnota, kterou si CFO nemůže vzít ze svého účtu -
            // ukázkou je proto částka, od které se vůbec upomíná. QR kód se sem
            // nedává: generuje se z reálného zůstatku, takže by u nezadluženého
            // CFO ukazoval 0,10 Kč pod textem o téhle částce.
            $ukazkovyDluh = (int) round($systemoveNastaveni->upominkaMinimalniCastka());

            $nahled->assign('predmet', htmlspecialchars(
                UpominkaVlastniZneni::dosadPovoleneKonstanty($nahlizenyPredmet),
                ENT_QUOTES,
            ));
            $nahled->assign('zprava', htmlspecialchars(
                $upominkaVlastniZneni->dosadSymboly(
                    $nahlizenyText,
                    (string) $u->jmenoNick(),
                    $u->id(),
                    $ukazkovyDluh,
                    $u->koncovkaDlePohlavi(),
                ),
                ENT_QUOTES,
            ));
        }

        $nahled->parse('nahled');
        $nahled->out('nahled');

        // exit, ne $BEZ_DEKORACE - útržek pro modál nesmí dostat ani patičku
        // sekce Finance, kterou index.php přidává až za modulem.
        exit;
    }

    $editor = new XTemplate(__DIR__ . '/upominky-dluzniku-zneni.xtpl');
    $editor->assign([
        'rocnik'    => $rocnik,
        'predmet'   => htmlspecialchars($zneni?->predmet ?? '', ENT_QUOTES),
        'text'      => htmlspecialchars($zneni?->text ?? '', ENT_QUOTES),
        'zpetUrl'   => 'finance/upominky-dluzniku',
        'nahledUrl' => 'finance/upominky-dluzniku?zneni&amp;nahled',
    ]);
    foreach (UpominkaVlastniZneni::dejPopisSymbolu() as $symbol => $popis) {
        $editor->assign('symbol', htmlspecialchars($symbol, ENT_QUOTES));
        $editor->assign('popisSymbolu', htmlspecialchars($popis, ENT_QUOTES));
        $editor->parse('zneni.symbol');
    }
    foreach (UpominkaVlastniZneni::POVOLENE_KONSTANTY as $nazevKonstanty) {
        if (!defined($nazevKonstanty)) {
            continue;
        }

        $editor->assign('symbol', htmlspecialchars("%$nazevKonstanty%", ENT_QUOTES));
        $editor->assign('popisSymbolu', htmlspecialchars((string) constant($nazevKonstanty), ENT_QUOTES));
        $editor->parse('zneni.symbol');
    }
    $editor->parse('zneni');
    $editor->out('zneni');

    return;
}

// Jen pro jednoho uživatele - náhled ani odeslání pár lidem nemá přepočítávat
// finance všem v databázi (6000+ uživatelů, desítky sekund a desítky tisíc dotazů).
$dejDluznika = static function (int $idUzivatele) use ($upominaniDluzniku, $rocnik): ?Dluznik {
    $uzivatel = Uzivatel::zId($idUzivatele);

    return $uzivatel
        ? $upominaniDluzniku->dejDluznika($uzivatel, $rocnik)
        : null;
};

// Náhled e-mailu konkrétního dlužníka - nic neodesílá ani neloguje.
$nahledParametr = get('nahled');
$idNahledu      = is_scalar($nahledParametr)
    ? (int)$nahledParametr
    : 0;
if ($idNahledu) {
    $dluznik = $dejDluznika($idNahledu);
    if (!$dluznik) {
        chyba('Tenhle uživatel mezi dlužníky není.');

        return;
    }

    // Modál si text vykreslí z předgenerovaných dat, dotahuje jen QR kódy.
    if (get('jenPrilohy')) {
        $BEZ_DEKORACE = true;

        $prilohy = new XTemplate(__DIR__ . '/upominky-dluzniku-prilohy.xtpl');
        foreach ($upominaniDluzniku->dejQrKodyProUpominku($dluznik->uzivatel) as $nazevPrilohy => $qrKod) {
            if (!$qrKod) {
                continue;
            }

            $prilohy->assign('nazevPrilohy', htmlspecialchars((string)$nazevPrilohy, ENT_QUOTES));
            $prilohy->assign('qrKodDataUri', 'data:image/png;base64,' . base64_encode($qrKod->getString()));
            $prilohy->parse('prilohy.priloha');
        }
        $prilohy->parse('prilohy');
        $prilohy->out('prilohy');

        // Za modulem by se jinak přidala ještě patička sekce Finance, která do
        // útržku pro modál nepatří.
        exit;
    }

    $typNahledu = match (get('typ')) {
        TypUpominky::TYDEN->value => TypUpominky::TYDEN,
        TypUpominky::RUCNI->value => TypUpominky::RUCNI,
        default                   => TypUpominky::VLASTNI,
    };

    // Bez $back: náhled si tahá i fetch z modálu, kterému by se přesměrování
    // na seznam vykreslilo jako celá admin stránka v útržku.
    if ($typNahledu->maVlastniZneni() && !$upominkaVlastniZneni->dejZneni($rocnik)?->jeVyplnene()) {
        chyba('Vlastní znění nemá vyplněný předmět nebo text, není co zobrazit.', false);

        return;
    }

    $nahled = new XTemplate(__DIR__ . '/upominky-dluzniku-nahled.xtpl');
    $nahled->assign('jmenoNick', htmlspecialchars((string)$dluznik->uzivatel->jmenoNick(), ENT_QUOTES));
    $nahled->assign('mail', htmlspecialchars((string)$dluznik->uzivatel->mail(), ENT_QUOTES));
    $nahled->assign('predmet', htmlspecialchars($upominaniDluzniku->dejEmailPredmet($typNahledu, $rocnik), ENT_QUOTES));
    $nahled->assign('zprava', htmlspecialchars($upominaniDluzniku->dejEmailZpravu(
        $typNahledu,
        (int)round($dluznik->dluh),
        $dluznik->uzivatel->id(),
        $dluznik->ucastNaGc,
        $dluznik->rokPosledniUcasti,
        $dluznik->uzivatel->koncovkaDlePohlavi(),
        (string)$dluznik->uzivatel->jmenoNick(),
        $rocnik,
    ), ENT_QUOTES));
    // QR kódy rovnou jako data URI, ať je v náhledu vidět i to, co reálně
    // odejde v příloze, a ne jen její název.
    foreach ($upominaniDluzniku->dejQrKodyProUpominku($dluznik->uzivatel) as $nazevPrilohy => $qrKod) {
        if (!$qrKod) {
            continue;
        }

        $nahled->assign('nazevPrilohy', htmlspecialchars((string)$nazevPrilohy, ENT_QUOTES));
        $nahled->assign('qrKodDataUri', 'data:image/png;base64,' . base64_encode($qrKod->getString()));
        $nahled->parse('nahled.priloha');
    }

    // Náhled si vytahuje modál, celá admin stránka okolo by byla jen balast
    // (31 kB místo 4 kB).
    $BEZ_DEKORACE = true;

    $nahled->parse('nahled');
    $nahled->out('nahled');

    return;
}

$maVyplneneVlastniZneni = (bool) $upominkaVlastniZneni->dejZneni($rocnik)?->jeVyplnene();

// MESIC tu chybí schválně: ruční rozesílání smí vyrobit jen ruční záznam, typy
// automatik by v logu smazaly rozdíl mezi tím, co poslal cron, a co člověk.
$zvolenyTyp  = post('typ') ?? get('typ');
$typUpominky = match ($zvolenyTyp) {
    TypUpominky::TYDEN->value => TypUpominky::TYDEN,
    TypUpominky::RUCNI->value => TypUpominky::RUCNI,
    default                   => TypUpominky::VLASTNI,
};

if (post('odeslat')) {
    // Radši neodešleme nic, než abychom u části lidí zjistili až v půlce,
    // že vlastní znění není čím vyplnit.
    if ($typUpominky->maVlastniZneni() && !$maVyplneneVlastniZneni) {
        oznameniPresmeruj(
            'Vlastní znění nemá vyplněný předmět nebo text, upomínky neodešly.',
            URL_ADMIN . '/finance/upominky-dluzniku?typ=' . $typUpominky->value,
        );
    }

    $idsKOdeslani = array_unique(array_map('intval', (array)post('id')));

    $odeslano = 0;
    $potize   = [];
    foreach ($idsKOdeslani as $idUzivatele) {
        $dluznik = $dejDluznika($idUzivatele);
        if (!$dluznik) {
            $potize[] = "Uživatel $idUzivatele už mezi dlužníky není, upomínka mu neodešla.";
            continue;
        }
        if (!$dluznik->uzivatel->mail()) {
            $potize[] = sprintf(
                'Uživatel %d nemá e-mail, upomínka mu neodešla.',
                $idUzivatele,
            );
            continue;
        }

        try {
            $upominaniDluzniku->odesliUpominkuJednomu(
                $dluznik->uzivatel,
                $typUpominky,
                (int)round($dluznik->dluh),
                $rocnik,
                $u,
                $dluznik->ucastNaGc,
                $dluznik->rokPosledniUcasti,
            );
            $odeslano++;
        } catch (Throwable $throwable) {
            $potize[] = sprintf('Upomínka pro uživatele %d neodešla.', $idUzivatele);
            $jobResultLogger->logs(sprintf(
                'Ruční upomínka pro uživatele %d neodešla: %s',
                $idUzivatele,
                $throwable->getMessage(),
            ));
        }
        set_time_limit(10);
    }

    if ($potize) {
        // Hlášky se ukládají do cookie, takže se dlouhý výpis celý zahodí
        // a CFO by neviděl vůbec nic. Podrobnosti jsou v logu jobu.
        $zobrazenePotize = array_slice($potize, 0, 5);
        if (count($potize) > count($zobrazenePotize)) {
            $zobrazenePotize[] = sprintf('… a dalších %d problémů, podrobnosti v logu.', count($potize) - count($zobrazenePotize));
        }
        chyba(implode(' ', $zobrazenePotize), false);
        $jobResultLogger->logs(sprintf('Ruční rozesílání upomínek: %d problémů.', count($potize)));
    }
    oznameniPresmeruj(
        "Odesláno upomínek: $odeslano",
        URL_ADMIN . '/finance/upominky-dluzniku?typ=' . $typUpominky->value,
    );
}

// Texty upomínek i hláška o nevyplněném znění jsou předgenerované do stránky,
// takže po úpravě znění v jiné záložce je celá stránka zastaralá. Bez tohohle
// by CFO po tlačítku Zpět viděl a odeslal starý text.
header('Cache-Control: no-store');

$t = new XTemplate(__DIR__ . '/upominky-dluzniku.xtpl');

// Přepočet financí všech uživatelů v DB je drahý na čas i na paměť.
set_time_limit(300);
ini_set('memory_limit', '512M');
$dluznici = $upominaniDluzniku->najdiDluzniky($rocnik);
$historie = $upominkaDluznikaLog->historiePodleUzivatelu($rocnik);

usort(
    $dluznici,
    static fn (Dluznik $prvni, Dluznik $druhy) => $druhy->dluh <=> $prvni->dluh,
);

$popisUcasti = [
    UcastNaGc::PRITOMEN->name      => 'byl na GC',
    UcastNaGc::JEN_PRIHLASEN->name => 'přihlášen, nedorazil',
    UcastNaGc::NEDORAZIL->name     => 'letos nebyl',
];

// Admin má <base href=".../admin/">, takže samotné "?nahled=" by spadlo na /admin/.
$t->assign('urlModulu', 'finance/upominky-dluzniku');

$celkovyDluh = 0;
foreach ($dluznici as $dluznik) {
    $idUzivatele      = $dluznik->uzivatel->id();
    $upominky         = $historie[$idUzivatele] ?? [];
    $posledniUpominka = $upominky[0] ?? null;

    $celkovyDluh += (int)round($dluznik->dluh);

    $t->assign([
        'id'                 => $idUzivatele,
        // Text je zadarmo (měřeno: 0 ms/kus), takže se předgeneruje rovnou sem
        // a modál ho ukáže okamžitě. QR se dotahuje až na vyžádání, ten stojí
        // ~50 ms na dlužníka a na 218 lidech by přidal přes 10 s k načtení.
        'zpravaRucni'        => htmlspecialchars($upominaniDluzniku->dejEmailZpravu(
            TypUpominky::RUCNI,
            (int)round($dluznik->dluh),
            $idUzivatele,
            $dluznik->ucastNaGc,
            $dluznik->rokPosledniUcasti,
            $dluznik->uzivatel->koncovkaDlePohlavi(),
            (string)$dluznik->uzivatel->jmenoNick(),
            $rocnik,
        ), ENT_QUOTES),
        'zpravaTyden'        => htmlspecialchars($upominaniDluzniku->dejEmailZpravu(
            TypUpominky::TYDEN,
            (int)round($dluznik->dluh),
            $idUzivatele,
            $dluznik->ucastNaGc,
            $dluznik->rokPosledniUcasti,
            $dluznik->uzivatel->koncovkaDlePohlavi(),
            (string)$dluznik->uzivatel->jmenoNick(),
            $rocnik,
        ), ENT_QUOTES),
        // Bez vyplněného znění se vlastní varianta nedá vyrobit, takže se
        // nepředgeneruje a modál na ni ani nepustí.
        'zpravaVlastni'      => $maVyplneneVlastniZneni
            ? htmlspecialchars($upominaniDluzniku->dejEmailZpravu(
                TypUpominky::VLASTNI,
                (int)round($dluznik->dluh),
                $idUzivatele,
                $dluznik->ucastNaGc,
                $dluznik->rokPosledniUcasti,
                $dluznik->uzivatel->koncovkaDlePohlavi(),
                (string)$dluznik->uzivatel->jmenoNick(),
                $rocnik,
            ), ENT_QUOTES)
            : '',
        'predmetRucni'       => htmlspecialchars($upominaniDluzniku->dejEmailPredmet(TypUpominky::RUCNI, $rocnik), ENT_QUOTES),
        'predmetTyden'       => htmlspecialchars($upominaniDluzniku->dejEmailPredmet(TypUpominky::TYDEN, $rocnik), ENT_QUOTES),
        'predmetVlastni'     => $maVyplneneVlastniZneni
            ? htmlspecialchars($upominaniDluzniku->dejEmailPredmet(TypUpominky::VLASTNI, $rocnik), ENT_QUOTES)
            : '',
        'jmenoNick'          => htmlspecialchars((string)$dluznik->uzivatel->jmenoNick(), ENT_QUOTES),
        'mail'               => htmlspecialchars((string)($dluznik->uzivatel->mail() ?: '(bez e-mailu)'), ENT_QUOTES),
        'dluh'               => (int)round($dluznik->dluh),
        'ucast'              => $popisUcasti[$dluznik->ucastNaGc->name],
        'rokPosledniUcasti'  => $dluznik->rokPosledniUcasti ?? 'nikdy',
        // Řazení potřebuje číslo. „Nikdy“ = 0, takže vzestupně vyjde jako
        // nejdávnější účast, což odpovídá tomu, co znamená.
        'rokPosledniUcastiSeradit' => $dluznik->rokPosledniUcasti ?? 0,
        'pocetUpominek'      => count($upominky),
        'typyUpominek'       => htmlspecialchars(implode(', ', array_map(
            static fn (OdeslanaUpominka $upominka) => $upominka->typUpominky->value,
            $upominky,
        )), ENT_QUOTES),
        'posledniUpominka'   => $posledniUpominka
            ? $posledniUpominka->kdy->format('j. n. Y H:i')
            : '–',
        'posledniUpominkaSeradit' => $posledniUpominka
            ? $posledniUpominka->kdy->getTimestamp()
            : 0,
        'zaskrtnuto'         => $dluznik->uzivatel->mail()
            ? 'checked'
            : '',
        'lzeOdeslat'         => $dluznik->uzivatel->mail()
            ? ''
            : 'disabled',
    ]);
    $t->parse('upominky.dluznik');
}

$t->assign([
    'pocetDluzniku' => count($dluznici),
    'celkovyDluh'   => $celkovyDluh,
    'rocnik'        => $rocnik,
    'vybranoRucni'  => $typUpominky === TypUpominky::RUCNI
        ? 'selected'
        : '',
    'vybranoTyden'  => $typUpominky === TypUpominky::TYDEN
        ? 'selected'
        : '',
    'vybranoVlastni' => $typUpominky === TypUpominky::VLASTNI
        ? 'selected'
        : '',
    'zneniChybi'    => $maVyplneneVlastniZneni
        ? 'ne'
        : 'ano',
]);
$t->parse('upominky');
$t->out('upominky');
