<?php

use Gamecon\Pravo;
use Gamecon\Cas\DateTimeCz;

/**
 * @var Uzivatel $u
 * @var string $podstranka
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */
if (!$u->maPravo(Pravo::ADMINISTRACE_REPORTY)) {
    die('Nemáš právo ' . Pravo::ADMINISTRACE_REPORTY . ' nutné k zobrazení reportů.');
}

$CSV_SEP = ';'; // separátor pro csv soubory
$NAZEV_SKRIPTU = $podstranka; // převzato z index.php

$reportPrava = dbOneArray(<<<SQL
    SELECT reporty_prava.id_prava
    FROM reporty_prava
    JOIN reporty ON reporty.id = reporty_prava.id_reportu
    WHERE reporty.skript = $0
    SQL,
    [0 => $NAZEV_SKRIPTU],
);

if ($reportPrava) {
    $maPravo = false;
    foreach ($reportPrava as $idPrava) {
        if ($u->maPravo((int)$idPrava)) {
            $maPravo = true;
            break;
        }
    }
    if (!$maPravo) {
        die('Nemáš potřebné právo k zobrazení tohoto reportu.');
    }
}

$skript = $NAZEV_SKRIPTU;
if ($skript === 'quick') {
    // někdy se stane že v URL je ID vícekrát, například quick?id=80?id=80?id=80?id=80
    $skript .= '-' . explode('?', get('id'))[0];
}
$format = get('format')
    ?: 'html';

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
        ],
    );
} catch (DbException $exception) {
    trigger_error($exception->getMessage() . PHP_EOL . $GLOBALS['dbLastQ'] . PHP_EOL . $exception->getTraceAsString(), E_USER_WARNING);
}
