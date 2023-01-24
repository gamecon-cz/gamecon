<?php

use Gamecon\Pravo;
use Gamecon\Cas\DateTimeCz;

/**
 * @var Uzivatel $u
 * @var string $podstranka
 */
if (!$u->maPravo(Pravo::ADMINISTRACE_REPORTY)) {
    die('Nemáš právo ' . Pravo::ADMINISTRACE_REPORTY . ' nutné k zobrazení reportů.');
}

$CSV_SEP       = ';'; // separátor pro csv soubory
$NAZEV_SKRIPTU = $podstranka; // převzato z index.php

$skript = $NAZEV_SKRIPTU;
if ($skript === 'quick') {
    $skript .= '-' . get('id');
}
$format = get('format') ?: 'html';

$nyni = new DateTimeCz();

try {
    dbQuery(<<<SQL
INSERT INTO reporty_log_pouziti(id_reportu, id_uzivatele, format, cas_pouziti, casova_zona)
VALUES ((SELECT id FROM reporty WHERE skript = $0), $1, $2, $3, $4)
SQL,
        [
            0 => $skript,
            1 => $u->id(),
            2 => $format,
            3 => $nyni->formatDb(),
            4 => $nyni->getTimezone()->getName(),
        ]
    );
} catch (DbException $exception) {
    trigger_error($exception->getMessage() . PHP_EOL . $GLOBALS['dbLastQ'] . PHP_EOL . $exception->getTraceAsString(), E_USER_WARNING);
}
