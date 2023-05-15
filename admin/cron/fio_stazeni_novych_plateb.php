<?php

use Gamecon\Uzivatel\Platby;

require_once __DIR__ . '/_cron_zavadec.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

if (!defined('FIO_TOKEN') || FIO_TOKEN === '') {
    logs('FIO_TOKEN není definován, přeskakuji nové platby.');
    return;
}

logs('Zpracovávám nové platby přes Fio API.');
$platby = (new Platby($systemoveNastaveni))->nactiNove();
foreach ($platby as $platba) {
    logs('platba ' . $platba->id()
        . ' (' . $platba->castka() . 'Kč, VS: ' . $platba->vs()
        . ($platba->zpravaProPrijemce() ? ', zpráva: ' . $platba->zpravaProPrijemce() : '')
        . ($platba->poznamkaProMne() ? ', poznámka: ' . $platba->poznamkaProMne() : '')
        . ')'
    );
}
if (!$platby) {
    logs('Žádné zaúčtovatelné platby.');
}
