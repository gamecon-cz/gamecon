<?php

/**
 * @var Uzivatel $u
 */

use Gamecon\Pravo;
use Gamecon\Role\SqlStruktura\PravoSqlStruktura;

if (!$u->maPravo(Pravo::ADMINISTRACE_FINANCE))
{
    echo 'Sem nemůžeš, sem můžou jenom lidi s právem ' . Pravo::zId(Pravo::ADMINISTRACE_FINANCE)->raw()[PravoSqlStruktura::JMENO_PRAVA];
    exit;
}

$sleva_charset = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
$kod = '';
for ($j = 0; $j < 10; $j++) {
    $kod .= $sleva_charset[random_int(0, strlen($sleva_charset) - 1)];
}
dbQuery("INSERT INTO slevove_kody(kod, createdBy, createdAt, usedBy, usedAt, invalidated) VALUE ($0, $1, NOW(), NULL, NULL, 0)", [$kod, $u->id()]);
?>
<html>
<head><title>Nový slevový kód</title></head>
<body><h1><?= $kod ?></h1></body>
</html>
