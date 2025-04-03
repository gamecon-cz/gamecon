<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Role\Role;
use Gamecon\Uzivatel\Platby;
use Gamecon\Uzivatel\PlatbySqlStruktura;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Uzivatel\Platba;

/**
 * nazev: Platby
 * pravo: 108
 * submenu_group: 5
 */

/** @var Uzivatel $u */
/** @var Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

if (post('sprarovatIdUzivatele')) {
    $uzivatel = Uzivatel::zId(post('sprarovatIdUzivatele'));
    if (!$uzivatel) {
        chyba(sprintf('Uživatel %d neexistuje.', post('sprarovatIdUzivatele')));
    }
    $platba = Platba::zId(post('sprarovatIdPlatby'), true);
    if (!$platba) {
        chyba(sprintf('Platbna %d neexistuje.', post('sprarovatIdPlatby')));
    }
    $platba->priradUzivateli($uzivatel);
    $platba->uloz();

    oznameni(sprintf('Platba %s byla sparována s uživatelem %s.', $platba->fioId(), $uzivatel->jmenoNick()));
}

$p = new XTemplate(__DIR__ . '/nesparovane-platby.xtpl');

$platby            = new Platby($systemoveNastaveni);
$nesparovanePlatby = $platby->nesparovanePlatby(
    rocnik: null,
    orderByDesc: PlatbySqlStruktura::PROVEDENO,
);

foreach ($nesparovanePlatby as $nesparovanaPlatba) {
    $p->assign([
        'idPlatby'               => $nesparovanaPlatba->id(),
        'castka'                 => $nesparovanaPlatba->castka(),
        'zpravaProPrijemce'      => $nesparovanaPlatba->poznamka(),
        'skrytaPoznamka'         => $nesparovanaPlatba->skrytaPoznamka(),
        'nazevProtiuctu'         => $nesparovanaPlatba->nazevProtiuctu(),
        'nazevBankyProtiuctu'    => $nesparovanaPlatba->nazevBankyProtiuctu(),
        'cisloProtiuctu'         => $nesparovanaPlatba->cisloProtiuctu(),
        'kodBankyProtiuctu'      => $nesparovanaPlatba->kodBankyProtiuctu(),
        'vs'                     => $nesparovanaPlatba->variabilniSymbol(),
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
