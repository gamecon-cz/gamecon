<?php

use Gamecon\Pravo;
use Gamecon\Uzivatel\Platby;
use Gamecon\Uzivatel\Finance;

/** @var Uzivatel $u */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

if (empty($u) || (!$u->maPravo(Pravo::ADMINISTRACE_FINANCE) && !$u->maPravo(Pravo::ADMINISTRACE_PENIZE) && !$u->jeInfopultak() && !$u->jeOrganizator())
) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => '403 Forbidden']);
    exit;
}

$puvodniStav = $_GET['puvodniStav'] ?? null;
if (is_numeric($puvodniStav)) {
    $puvodniStav = Finance::zaokouhli($puvodniStav);
}
$puvodniStav ??= $u->finance()->stav();
$puvodniSuma = $u->finance()->sumaPlateb($systemoveNastaveni->rocnik());

$platby = new Platby($systemoveNastaveni);

$zmenilSeZustatek = false;
foreach ($platby->nactiZPoslednichDni(1) as $platba) {
    if ($platba->idUcastnika() === $u->id()) {
        $zmenilSeZustatek = true;
        break;
    }
}

$novaSuma = $u->finance()->sumaPlateb($systemoveNastaveni->rocnik(), true);

echo json_encode([
    'puvodniStav' => $puvodniStav,
    'novyStav' => Finance::zaokouhli($puvodniStav + ($novaSuma - $puvodniSuma)),
    'zmenilSeZustatek' => $zmenilSeZustatek,
    'novaSuma' => $novaSuma,
    'puvodniSuma' => $puvodniSuma,
]);
exit;
