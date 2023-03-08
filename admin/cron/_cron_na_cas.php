<?php

try {
    $casovaTolerance = new DateInterval('PT1S');
    /** @var DateTimeImmutable $cas */
    $cas = require __DIR__ . '/_odlozeny_cas.php';
} catch (RuntimeException $runtimeException) {
    logs($runtimeException->getMessage());
    return false;
}

global $systemoveNastaveni;
$ted = $systemoveNastaveni->ted();

$sleep = $cas->getTimestamp() - $ted->getTimestamp();

if ($sleep >= 300) {
    logs("Čas spuštění bude až za $sleep sekund. Necháme to až na příští běh CRONu.");
    return false;
}

set_time_limit($sleep + 1);

sleep($sleep);

return true;
