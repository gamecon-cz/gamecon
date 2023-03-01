<?php

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

    if ($cas < $ted) {
        throw new RuntimeException("Na spuštění CRONu v '$casString' už je pozdě");
    }

}

return $cas;
