<?php

use Gamecon\Uzivatel\Platby;
use Gamecon\Cas\DateTimeImmutableStrict;

require_once __DIR__ . '/_cron_zavadec.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */
global $systemoveNastaveni;

if (!defined('FIO_TOKEN') || FIO_TOKEN === '') {
    logs('FIO_TOKEN není definován, přeskakuji aktualizaci plateb.');
    return;
}

$platbyService = new Platby($systemoveNastaveni);
if ($platbyService->platbyBylyAktualizovanyPredChvili()) {
    logs('Platby byly aktualizovány před chvílí, přeskakuji.');
    return;
}

logsText('Zpracovávám platby z Fio API...');
$platby = $platbyService->nactiNove();
foreach ($platby as $platba) {
    logs(' - platba ' . $platba->id()
        . ' (' . $platba->castka() . 'Kč, VS: ' . $platba->vs()
        . ($platba->zpravaProPrijemce() ? ', zpráva: ' . $platba->zpravaProPrijemce() : '')
        . ($platba->zkrytaPoznamka() ? ', poznámka: ' . $platba->zkrytaPoznamka() : '')
        . ')',
        false,
    );
}
if (!$platby) {
    logsText('...žádné zaúčtovatelné platby.', false);
}

$platbyService->nastavPosledniAktulizaciPlatebBehemSessionKdy(new DateTimeImmutableStrict());
