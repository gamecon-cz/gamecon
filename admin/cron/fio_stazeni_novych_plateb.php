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
$fioPlatby = $platbyService->nactiZPoslednichDni();
$platbyService->nastavPosledniAktulizaciPlatebBehemSessionKdy(new DateTimeImmutableStrict());

if (!$fioPlatby) {
    logsText('...žádné zaúčtovatelné platby.', false);

    return;
}

foreach ($fioPlatby as $fioPlatba) {
    logs(' - platba ' . $fioPlatba->id()
         . ' (' . $fioPlatba->castka() . 'Kč, VS: ' . $fioPlatba->variabilniSymbol()
         . ($fioPlatba->zpravaProPrijemce()
            ? ', zpráva: ' . $fioPlatba->zpravaProPrijemce()
            : '')
         . ($fioPlatba->skrytaPoznamka()
            ? ', poznámka: ' . $fioPlatba->skrytaPoznamka()
            : '')
         . ')',
        false,
    );
}
