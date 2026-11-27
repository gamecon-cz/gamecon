<?php

declare(strict_types=1);

use Gamecon\PushNotifikace\ChybaPushNotifikace;
use Gamecon\PushNotifikace\KontextZarizeni;
use Gamecon\PushNotifikace\PushNotifikace;

/**
 * Notifikace na zařízení přihlášeného uživatele: zapnutí, vypnutí, stav a výměna subscription. Volá ho skript
 * soubory/push-notifikace.js a service worker (sw.js), tělo požadavku i odpověď jsou JSON.
 *
 * Kdo se ptá, říká jen session a cookie trvalého přihlášení ({@see KontextZarizeni}), nikdy tělo požadavku.
 * Proti podvrženému požadavku z cizí stránky stojí `omezCsrf()` ve web/index.php (hostitel v Refereru u každého
 * POSTu) a cookie session se SameSite=Lax; typ obsahu application/json navíc cizí formulář neodešle.
 *
 * @var Uzivatel|null                                    $u
 * @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$odpovez = static function (int $httpKod, array $odpoved): void {
    http_response_code($httpKod);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($odpoved, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};

$pushNotifikace = PushNotifikace::zNastaveni($systemoveNastaveni);
if (! $pushNotifikace->isZapnuto()) {
    // vypnutá funkce se netváří, že existuje
    $odpovez(404, ['chyba' => 'Notifikace nejsou zapnuté.']);

    return;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    $odpovez(405, ['chyba' => 'Jen POST.']);

    return;
}
if (! $u) {
    $odpovez(401, ['chyba' => 'Nejsi přihlášen(a).']);

    return;
}
$typObsahu = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
$pozadavek = $typObsahu === 'application/json'
    ? json_decode((string) file_get_contents('php://input'), true, 8)
    : null;
if (! is_array($pozadavek)) {
    $odpovez(415, ['chyba' => 'Požadavek musí být JSON.']);

    return;
}

$kontext = KontextZarizeni::zPrihlaseneho($u);
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
try {
    $spravaZarizeni = $pushNotifikace->getSpravaZarizeni();
    switch ($pozadavek['akce'] ?? null) {
        case 'stav':
            break;
        case 'zapnout':
            $spravaZarizeni->zapni($kontext, $pozadavek['subscription'] ?? null, $userAgent);
            $pozadavek['endpoint'] = $pozadavek['subscription']['endpoint'] ?? null;
            break;
        case 'vypnout':
            $spravaZarizeni->vypni($kontext, $pozadavek['endpoint'] ?? null);
            $pozadavek['endpoint'] = null;
            break;
        case 'zmena':
            $spravaZarizeni->zmen(
                $kontext,
                $pozadavek['staryEndpoint'] ?? null,
                $pozadavek['subscription'] ?? null,
                $userAgent,
            );
            $pozadavek['endpoint'] = $pozadavek['subscription']['endpoint'] ?? null;
            break;
        default:
            throw new ChybaPushNotifikace('Neznámá akce.');
    }
    $stav = $spravaZarizeni->zjistiStav($kontext, $pozadavek['endpoint'] ?? null);
    $odpovez(200, [
        'stav'              => $stav['stav']->value,
        'zrusitVProhlizeci' => $stav['zrusitVProhlizeci'],
    ]);
} catch (ChybaPushNotifikace $chyba) {
    $odpovez($chyba->getHttpKod(), ['chyba' => $chyba->getMessage()]);
}
