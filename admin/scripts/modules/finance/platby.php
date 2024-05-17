<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Role\Role;
use Gamecon\Uzivatel\Platby;
use Gamecon\Uzivatel\PlatbySqlStruktura;
use Gamecon\Cas\DateTimeCz;

/**
 * nazev: Platby
 * pravo: 108
 * submenu_group: 5
 */

/** @var Uzivatel $u */
/** @var Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$p = new XTemplate(__DIR__ . '/platby.xtpl');

$platby            = new Platby($systemoveNastaveni);
$nesparovanePlatby = $platby->nesparovanePlatby(null, PlatbySqlStruktura::PROVEDENO);

foreach ($nesparovanePlatby as $nesparovanaPlatba) {
    $p->assign([
        'castka'                 => $nesparovanaPlatba->castka(),
        'poznamka'               => $nesparovanaPlatba->poznamka(),
        'fioId'                  => $nesparovanaPlatba->fioId(),
        'kdyPripsanoNaUcetBanky' => $nesparovanaPlatba->pripsanoNaUcetBanky() !== null
            ? DateTimeCz::createFromInterface(new \DateTime($nesparovanaPlatba->pripsanoNaUcetBanky()))->formatCasStandard()
            : '?',
        'kdyPripsanoNaUcetGc'    => $nesparovanaPlatba->provedeno() !== null
            ? DateTimeCz::createFromInterface(new \DateTime($nesparovanaPlatba->provedeno()))->formatCasStandard()
            : '',
    ]);
    $p->parse('nesparovanePlatby.nesparovanaPlatba');
}

$p->parse('nesparovanePlatby');
$p->out('nesparovanePlatby');
