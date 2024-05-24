<?php

use Gamecon\Role\Role;
use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Uzivatel\Platby;

/**
 * nazev: Peníze
 * pravo: 111
 */

/**
 * @var Uzivatel $u
 * @var Uzivatel|null $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

require __DIR__ . '/_postUzivatelProPripsaniSlevy.php';

require __DIR__ . '/_postUzivatelKVyplaceniAktivity.php';

require __DIR__ . '/_ajaxGetUzivatelKVyplaceniAktivity.php';

$x = new XTemplate(__DIR__ . '/penize.xtpl');

$x->assign([
    'id'  => $uPracovni
        ? $uPracovni->id()
        : null,
    'org' => $u->jmenoNick(),
]);
$x->parse('penize.pripsatSlevu');
$x->parse('penize.vyplatitBonusZaVedeniAktivity');

$x->parse('penize');
$x->out('penize');

require __DIR__ . '/_odkazNaReportUbytovani.php';
