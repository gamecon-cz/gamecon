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
    chyba('Ruční rozesílání upomínek je jen pro CFO.');

    return;
}

$rocnik              = $systemoveNastaveni->rocnik();
$upominkaDluznikaLog = new UpominkaDluznikaLog();
$jobResultLogger     = new JobResultLogger();
$upominaniDluzniku   = new UpominaniDluzniku(
    $systemoveNastaveni,
    $jobResultLogger,
    new FioStazeniNovychPlateb($systemoveNastaveni, $jobResultLogger),
    $upominkaDluznikaLog,
);

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

    $typNahledu = get('typ') === TypUpominky::TYDEN->value
        ? TypUpominky::TYDEN
        : TypUpominky::RUCNI;

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

// Ruční rozesílání smí vyrobit jen ruční záznam - typy automatik by v logu
// smazaly rozdíl mezi tím, co poslal cron, a co člověk.
$zvolenyTyp  = post('typ') ?? get('typ');
$typUpominky = $zvolenyTyp === TypUpominky::TYDEN->value
    ? TypUpominky::TYDEN
    : TypUpominky::RUCNI;

if (post('odeslat')) {
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
        ), ENT_QUOTES),
        'zpravaTyden'        => htmlspecialchars($upominaniDluzniku->dejEmailZpravu(
            TypUpominky::TYDEN,
            (int)round($dluznik->dluh),
            $idUzivatele,
            $dluznik->ucastNaGc,
            $dluznik->rokPosledniUcasti,
            $dluznik->uzivatel->koncovkaDlePohlavi(),
        ), ENT_QUOTES),
        'predmetRucni'       => htmlspecialchars($upominaniDluzniku->dejEmailPredmet(TypUpominky::RUCNI, $rocnik), ENT_QUOTES),
        'predmetTyden'       => htmlspecialchars($upominaniDluzniku->dejEmailPredmet(TypUpominky::TYDEN, $rocnik), ENT_QUOTES),
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
]);
$t->parse('upominky');
$t->out('upominky');
