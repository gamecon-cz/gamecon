<?php
require_once __DIR__ . '/_cron_zavadec.php';

if (!defined('FIO_TOKEN') || FIO_TOKEN === '') {
    logs('FIO_TOKEN není definován, přeskakuji nové platby.');
    return;
}

logs('Zpracovávám nové platby přes Fio API.');
$platby = Platby::nactiNove();
foreach ($platby as $p) {
    logs('platba ' . $p->id()
        . ' (' . $p->castka() . 'Kč, VS: ' . $p->vs()
        . ($p->zpravaProPrijemce() ? ', zpráva: ' . $p->zpravaProPrijemce() : '')
        . ($p->poznamkaProMne() ? ', poznámka: ' . $p->poznamkaProMne() : '')
        . ')'
    );
}
if (!$platby) {
    logs('Žádné zaúčtovatelné platby.');
}

