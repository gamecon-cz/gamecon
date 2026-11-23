<?php

declare(strict_types=1);

/**
 * Texty medailonků, které vypravěči napsali v Nastavení na webu a na web je pustí až organizátor,
 * viz Gamecon\Uzivatel\MedailonekKeSchvaleni
 *
 * nazev: Schvalování medailonků
 * ikona: user-check
 * pravo: 118
 * submenu_order: 1
 *
 * @var Uzivatel                                      $u
 * @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Uzivatel\MedailonekKeSchvaleni;
use Gamecon\Uzivatel\SchvaleniMedailonku;
use Gamecon\XTemplate\XTemplate;

$urlModulu = 'web/schvalovani-medailonku';
// orgové a loňští vypravěči, kteří letos nic nevedou
$filtrBezAktivity = 'bez';

/** @var array<int, TypAktivity> $linie sekce podle ID, v pořadí z menu webu */
$linie = [];
foreach (TypAktivity::zVsech() as $typ) {
    if (! $typ->jeInterni()) {
        $linie[$typ->id()] = $typ;
    }
}
uasort(
    $linie,
    static fn (TypAktivity $a, TypAktivity $b): int => [(int) $a->poradi(), $a->id()] <=> [(int) $b->poradi(), $b->id()],
);

$pozadovanyFiltr = get('linie');
$jeZnamaLinie = is_string($pozadovanyFiltr) && ctype_digit($pozadovanyFiltr) && isset($linie[(int) $pozadovanyFiltr]);
// neznámý filtr ukáže všechny
$filtr = match (true) {
    $pozadovanyFiltr === $filtrBezAktivity => $filtrBezAktivity,
    $jeZnamaLinie                          => (string) (int) $pozadovanyFiltr,
    default                                => null,
};
// relativně vůči kořeni adminu (<base href>)
$urlSFiltrem = static fn (?string $filtrOdkazu): string => $filtrOdkazu === null
    ? $urlModulu
    : $urlModulu . '?' . http_build_query([
        'linie' => $filtrOdkazu,
    ]);

$schvaleni = new SchvaleniMedailonku($u);
$schvaleni->zpracujPost(navrat: URL_ADMIN . '/' . $urlSFiltrem($filtr));

$template = new XTemplate(__DIR__ . '/schvalovani-medailonku.xtpl');

// stránku otevře i správce webu s právem celé kartičky Web, texty ale schvalují jen orgové
if (! $schvaleni->smiSchvalovat()) {
    http_response_code(403);
    $template->parse('schvalovani.bezPrava');
    $template->parse('schvalovani');
    $template->out('schvalovani');

    return;
}

$template->parse('schvalovani.uvod');

$cekajici = MedailonekKeSchvaleni::cekajici();
$idVypravecu = array_map(
    static fn (MedailonekKeSchvaleni $keSchvaleni): int => $keSchvaleni->idUzivatele,
    $cekajici,
);
$linieVypravecu = MedailonekKeSchvaleni::linieVypravecu($idVypravecu, $systemoveNastaveni->rocnik());
/** @var array<int, Uzivatel> $vypraveci */
$vypraveci = [];
foreach (Uzivatel::zIds($idVypravecu) as $vypravec) {
    $vypraveci[$vypravec->id()] = $vypravec;
}

$pocetVLinii = [];
$pocetBezAktivity = 0;
foreach ($cekajici as $keSchvaleni) {
    $linieVypravece = $linieVypravecu[$keSchvaleni->idUzivatele] ?? [];
    if ($linieVypravece === []) {
        ++$pocetBezAktivity;
    }
    foreach ($linieVypravece as $idLinie) {
        $pocetVLinii[$idLinie] = ($pocetVLinii[$idLinie] ?? 0) + 1;
    }
}

// kdo vede ve dvou sekcích, je v obou
$zobrazeni = array_filter(
    $cekajici,
    static fn (MedailonekKeSchvaleni $keSchvaleni): bool => match ($filtr) {
        null              => true,
        $filtrBezAktivity => ($linieVypravecu[$keSchvaleni->idUzivatele] ?? []) === [],
        default           => in_array((int) $filtr, $linieVypravecu[$keSchvaleni->idUzivatele] ?? [], true),
    },
);

if ($cekajici !== []) {
    $volbyFiltru = [[null, 'Všechny', count($cekajici)]];
    foreach ($linie as $idLinie => $typ) {
        // vybraná sekce zůstane vidět, i když v ní po schválení nic nezbylo
        if (($pocetVLinii[$idLinie] ?? 0) > 0 || $filtr === (string) $idLinie) {
            $volbyFiltru[] = [(string) $idLinie, (string) $typ->nazev(), $pocetVLinii[$idLinie] ?? 0];
        }
    }
    if ($pocetBezAktivity > 0 || $filtr === $filtrBezAktivity) {
        $volbyFiltru[] = [$filtrBezAktivity, 'Bez letošní aktivity', $pocetBezAktivity];
    }
    foreach ($volbyFiltru as [$filtrVolby, $nazevVolby, $pocet]) {
        $template->assign([
            'url'     => htmlspecialchars($urlSFiltrem($filtrVolby)),
            'nazev'   => htmlspecialchars($nazevVolby),
            'pocet'   => $pocet,
            'aktivni' => $filtrVolby === $filtr
                ? ' class="aktivni" aria-current="true"'
                : '',
        ]);
        $template->parse('schvalovani.filtr.volba');
    }
    $template->parse('schvalovani.filtr');
}

// detail medailonku jen pro toho, kdo smí na Aktivity → Medailonky
$smiDetailMedailonku = AdminMenu::isPodstrankaPristupna($u, 'aktivity', 'medailonky');
foreach ($zobrazeni as $keSchvaleni) {
    $idUzivatele = $keSchvaleni->idUzivatele;
    $template->assign([
        'idUzivatele' => $idUzivatele,
        // jméno i přezdívku si uživatel píše sám a XTemplate neescapuje
        'jmeno'     => htmlspecialchars((string) (($vypraveci[$idUzivatele] ?? null)?->jmenoNick() ?? "ID {$idUzivatele}")),
        'urlNaWebu' => htmlspecialchars(URL_WEBU . '/' . $idUzivatele),
        'formular'  => $schvaleni->getHtml($keSchvaleni, $urlSFiltrem($filtr)),
    ]);
    $linieVypravece = array_filter(
        $linieVypravecu[$idUzivatele] ?? [],
        static fn (int $idLinie): bool => isset($linie[$idLinie]),
    );
    foreach ($linieVypravece as $idLinie) {
        $template->assign('nazevLinie', htmlspecialchars((string) $linie[$idLinie]->nazev()));
        $template->parse('schvalovani.vypravec.linie');
    }
    if ($linieVypravece === []) {
        $template->parse('schvalovani.vypravec.bezLinie');
    }
    if ($smiDetailMedailonku) {
        $template->parse('schvalovani.vypravec.detail');
    }
    $template->parse('schvalovani.vypravec');
}

if ($zobrazeni === []) {
    $template->assign('prazdno', match (true) {
        $cekajici === []             => 'Žádný text nečeká na schválení.',
        $filtr === $filtrBezAktivity => 'Od vypravěčů bez letošní aktivity žádný text nečeká na schválení.',
        default                      => sprintf(
            'V sekci %s žádný text nečeká na schválení.',
            htmlspecialchars((string) $linie[(int) $filtr]->nazev()),
        ),
    });
    $template->parse('schvalovani.prazdno');
}

$template->parse('schvalovani');
$template->out('schvalovani');
