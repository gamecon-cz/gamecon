<?php

use Gamecon\Cas\DateTimeCz;

$cas = null;

$casString = get('cas');
if (!$casString) {
    throw new RuntimeException("Chybí čas předaný GET parametrem 'cas'");
}

global $systemoveNastaveni;
try {
    $cas = $casString === 'now'
        ? $systemoveNastaveni->ted()
        : new DateTimeImmutable($casString);
} catch (Exception $exception) {
    throw new RuntimeException("Chybný čas CRONu '$casString'");
}

$ted = $systemoveNastaveni->ted();
if (!empty($casovaTolerance) && $casovaTolerance instanceof DateInterval) {
    $ted = $ted->sub($casovaTolerance);
}

if ($cas < $ted) {
    throw new RuntimeException("Na spuštění CRONu v '$casString' už je pozdě. Teď je '{$ted->format(DateTimeCz::FORMAT_DB)}'");
}

return $cas;
