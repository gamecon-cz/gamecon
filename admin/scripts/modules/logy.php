<?php

use Gamecon\Kanaly\MailLogger;
use Gamecon\XTemplate\XTemplate;

/**
 * nazev: Logy
 * pravo: 110
 */

const POCET_NA_STRANKU = 50;

$mailLogger = MailLogger::zGlobals();
$idDetailu  = (int) (get('id') ?? 0);

if ($idDetailu > 0) {
    $detail = $mailLogger->detail($idDetailu);
    if ($detail === null) {
        http_response_code(404);
        echo '<h1>Záznam nenalezen</h1><p><a class="tlacitko" href="logy">Zpět na seznam</a></p>';
        return;
    }
    $adresatiDetail = json_decode((string) $detail['adresati'], true) ?: [];

    $t = new XTemplate(__DIR__ . '/logy.xtpl');
    $t->assign('kdy', htmlspecialchars((string) $detail['kdy']));
    $t->assign('predmet', htmlspecialchars((string) $detail['predmet']));
    $t->assign('format', htmlspecialchars((string) $detail['format']));
    $t->assign('adresati', htmlspecialchars(implode(', ', array_map('strval', $adresatiDetail))));
    $t->assign('prilohy', (int) $detail['prilohy_count']);
    $t->assign('chyba', $detail['chyba'] !== null ? htmlspecialchars((string) $detail['chyba']) : '—');
    $t->assign('telo', htmlspecialchars((string) $detail['telo']));
    $t->parse('detail');
    $t->out('detail');
    return;
}

$filtr          = trim((string) (get('filtr') ?? ''));
$razeniSloupec  = (string) (get('razeni') ?? 'kdy');
$razeniSmer     = strtoupper((string) (get('smer') ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
$aktualniStrana = max(1, (int) (get('strana') ?? 1));

$celkem     = $mailLogger->spocitej($filtr ?: null);
$pocetStran = max(1, (int) ceil($celkem / POCET_NA_STRANKU));
if ($aktualniStrana > $pocetStran) {
    $aktualniStrana = $pocetStran;
}
$offset  = ($aktualniStrana - 1) * POCET_NA_STRANKU;
$zaznamy = $mailLogger->najdi(
    filtr: $filtr ?: null,
    razeniSloupec: $razeniSloupec,
    razeniSmer: $razeniSmer,
    limit: POCET_NA_STRANKU,
    offset: $offset,
);

$sestavUrl = static function (array $parametry) use ($filtr): string {
    $vychozi = array_filter([
        'filtr'  => $filtr,
        'razeni' => $parametry['razeni'] ?? null,
        'smer'   => $parametry['smer']   ?? null,
        'strana' => $parametry['strana'] ?? null,
    ], static fn($hodnota) => $hodnota !== null && $hodnota !== '');
    return 'logy?' . http_build_query($vychozi);
};

$odkazRazeni = static function (string $sloupec) use ($razeniSloupec, $razeniSmer, $sestavUrl): string {
    $novySmer = ($razeniSloupec === $sloupec && $razeniSmer === 'DESC') ? 'ASC' : 'DESC';
    return $sestavUrl(['razeni' => $sloupec, 'smer' => $novySmer]);
};

$indikator = static function (string $sloupec) use ($razeniSloupec, $razeniSmer): string {
    if ($razeniSloupec !== $sloupec) {
        return '';
    }
    return $razeniSmer === 'DESC' ? ' ▼' : ' ▲';
};

$t = new XTemplate(__DIR__ . '/logy.xtpl');
$t->assign('filtr', htmlspecialchars($filtr, ENT_QUOTES));
$t->assign('celkem', $celkem);
$t->assign('aktualniStrana', $aktualniStrana);
$t->assign('pocetStran', $pocetStran);
$t->assign('odkazRazeniKdy', $odkazRazeni('kdy'));
$t->assign('odkazRazeniPrilohy', $odkazRazeni('prilohy_count'));
$t->assign('indikatorKdy', $indikator('kdy'));
$t->assign('indikatorPrilohy', $indikator('prilohy_count'));

if ($filtr !== '') {
    $t->parse('logy.zrusitFiltr');
}

foreach ($zaznamy as $zaznam) {
    $adresati = json_decode((string) $zaznam['adresati'], true) ?: [];
    $t->assign('id', (int) $zaznam['id']);
    $t->assign('kdy', htmlspecialchars((string) $zaznam['kdy']));
    $t->assign('predmet', htmlspecialchars((string) $zaznam['predmet']));
    $t->assign('format', htmlspecialchars((string) $zaznam['format']));
    $t->assign('adresati', htmlspecialchars(implode(', ', array_map('strval', $adresati))));
    $t->assign('prilohy', (int) $zaznam['prilohy_count']);
    $t->assign('chyba', $zaznam['chyba'] !== null ? htmlspecialchars((string) $zaznam['chyba']) : '');
    $t->parse('logy.radek');
}

$odkazPredchozi = $aktualniStrana > 1
    ? $sestavUrl(['razeni' => $razeniSloupec, 'smer' => $razeniSmer, 'strana' => $aktualniStrana - 1])
    : '';
$odkazDalsi = $aktualniStrana < $pocetStran
    ? $sestavUrl(['razeni' => $razeniSloupec, 'smer' => $razeniSmer, 'strana' => $aktualniStrana + 1])
    : '';
$t->assign('odkazPredchozi', $odkazPredchozi);
$t->assign('odkazDalsi', $odkazDalsi);
if ($odkazPredchozi !== '') {
    $t->parse('logy.predchozi');
}
if ($odkazDalsi !== '') {
    $t->parse('logy.dalsi');
}

$t->parse('logy');
$t->out('logy');
