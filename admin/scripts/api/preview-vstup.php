<?php

declare(strict_types=1);

use Gamecon\Dev\PreviewVstup;
use Gamecon\Pravo;

/**
 * Odpověď na dotaz brány preview, jestli je relace přihlášený organizátor. Viz {@see PreviewVstup}.
 *
 * @var Uzivatel|null $u
 */

$vstup = new PreviewVstup(
    (string) (try_constant('PREVIEW_GATE_SECRET') ?? ''),
    (string) (try_constant('GAMECON_SSO_SECRET') ?? ''),
);

// Bez platného tokenu od brány je to adresa jako každá jiná neexistující v api (viz admin/index.php).
if (! $vstup->jeVolaniOdValidatoru((string) ($_SERVER['HTTP_X_GATE_AUTH'] ?? ''))) {
    http_response_code(404);
    echo 'API endpoint not found.';

    return;
}

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['chyba' => 'metoda']);

    return;
}

$nonce = get('nonce');
[$status, $telo] = $vstup->odpovez(
    $u?->id(),
    $u?->jeOrganizator() ?? false,
    $u?->maPravo(Pravo::ADMINISTRACE_WEB) ?? false,
    is_string($nonce) ? $nonce : '',
);

http_response_code($status);
echo json_encode($telo);
