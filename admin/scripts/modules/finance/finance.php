<?php

use Gamecon\Role\Role;
use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Uzivatel\Platby;

/**
 * nazev: Finance
 * pravo: 108
 * submenu_group: 5
 */

/**
 * @var Uzivatel $u
 * @var Uzivatel|null $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

require __DIR__ . '/../penize/_postUzivatelProPripsaniSlevy.php';

require __DIR__ . '/../penize/_postUzivatelKVyplaceniAktivity.php';

require __DIR__ . '/../penize/_ajaxGetUzivatelKVyplaceniAktivity.php';

$x = new XTemplate(__DIR__ . '/finance.xtpl');

require __DIR__ . '/../penize/_varovaniOZasekleSynchronizaciFio.php';

if (isset($_GET['minimum'])) {
    $min = (int)$_GET['minimum'];
    $o   = dbQuery(<<<SQL
SELECT uzivatele_hodnoty.*
FROM uzivatele_hodnoty
JOIN platne_role_uzivatelu
    ON(platne_role_uzivatelu.id_uzivatele=uzivatele_hodnoty.id_uzivatele AND platne_role_uzivatelu.id_role=$0)
SQL,
        [Role::PRIHLASEN_NA_LETOSNI_GC],
    );
    $ids = '';
    while ($r = mysqli_fetch_assoc($o)) {
        $un = new Uzivatel($r);
        $un->nactiPrava();
        if (($stav = $un->finance()->stav()) >= $min) {
            $x->assign([
                'login'     => $un->prezdivka(),
                'stav'      => $stav,
                'aktivity'  => $un->finance()->cenaAktivit(),
                'ubytovani' => $un->finance()->cenaUbytovani(),
                'predmety'  => $un->finance()->cenaPredmetyAStrava(),
            ]);
            $x->parse('finance.uzivatele.uzivatel');
            $ids .= $un->id() . ',';
        }
    }
    $x->assign('minimum', $min);
    $x->assign('ids', substr($ids, 0, -1));
    $ids
        ? $x->parse('finance.uzivatele')
        : $x->parse('finance.nikdo');
}

$x->assign([
    'id'  => $uPracovni
        ? $uPracovni->id()
        : null,
    'org' => $u->jmenoNick(),
]);
$x->parse('finance.pripsatSlevu');

$x->assign('rok', $systemoveNastaveni->rocnik());

$x->assign('bfgr', basename(__DIR__ . '/../../zvlastni/reporty/celkovy-report.php', '.php'));
$x->parse('finance.reporty');

$x->parse('finance');
$x->out('finance');
