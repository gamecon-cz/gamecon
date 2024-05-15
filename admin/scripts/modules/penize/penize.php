<?php

use Gamecon\Role\Role;
use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Uzivatel\Platby;

/**
 * nazev: Peníze
 * pravo: 110
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

require __DIR__ . '/_varovaniOZasekleSynchronizaciFio.php';

$x->assign([
    'id'  => $uPracovni
        ? $uPracovni->id()
        : null,
    'org' => $u->jmenoNick(),
]);
$x->parse("{$x->root()}.pripsatSlevu");
$x->parse("{$x->root()}.vyplatitBonusZaVedeniAktivity");

$x->assign('rok', $systemoveNastaveni->rocnik());

require __DIR__ . '/_kfcMrizkovyProdej.php';

$x->parse($x->root());
$x->out($x->root());

require __DIR__ . '/../_ubytovani-a-dalsi-obcasne-infopultakoviny-import-ubytovani.php';
require __DIR__ . '/../_ubytovani-a-dalsi-obcasne-infopultakoviny-import-balicku.php';
