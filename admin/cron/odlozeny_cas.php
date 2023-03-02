<?php

use Gamecon\Cas\DateTimeCz;

$cas = null;

{ // local scope
    $casString = get('cas');
    if (!$casString) {
        throw new RuntimeException("Chybí čas přes GET parametr 'cas'");
    }

    try {
        $cas = new DateTimeImmutable($casString);
    } catch (Exception $exception) {
        throw new RuntimeException("Chybný čas CRONu '$casString'");
    }

    $ted = new DateTimeImmutable();
    if (!empty($casovaTolerance) && $casovaTolerance instanceof DateInterval) {
        $ted = $ted->sub($casovaTolerance);
    }

    if ($cas < $ted) {
        throw new RuntimeException("Na spuštění CRONu v '$casString' už je pozdě. Teď je {$ted->format(DateTimeCz::FORMAT_DB)}");
    }

}

return $cas;
