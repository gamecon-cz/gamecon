<?php

use Gamecon\Role\Zidle;
use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;

/**
 * Rychlé finanční transakce (obsolete) (starý kód)
 *
 * nazev: Finance
 * pravo: 108
 * submenu_group: 5
 */

/**
 * @var Uzivatel $u
 * @var Uzivatel|null $uPracovni
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

if (post('uzivatelProPripsaniSlevy')) {
    $uzivatel = Uzivatel::zId(post('uzivatelProPripsaniSlevy'));
    if (!$uzivatel) {
        chyba(sprintf('Uživatel %d neexistuje.', post('uzivatelProPripsaniSlevy')));
    }
    if (!post('sleva')) {
        chyba('Zadej slevu.');
    }
    if (!$uzivatel->gcPrihlasen()) {
        chyba(sprintf('Uživatel %s není přihlášen na GameCon.', $uzivatel->jmenoNick()));
    }
    $uzivatel->finance()->pripisSlevu(
        post('sleva'),
        post('poznamkaKUzivateliProPripsaniSlevy'),
        $u
    );
    $numberFormatter = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
    oznameni(
        sprintf(
            'Sleva %s připsána k uživateli %s.',
            $numberFormatter->formatCurrency(
                prevedNaFloat(post('sleva')),
                'CZK'
            ),
            $uzivatel->jmenoNick()
        )
    );
}

if (post('uzivatelKVyplaceniAktivity')) {
    $uzivatel = Uzivatel::zId(post('uzivatelKVyplaceniAktivity'));
    if (!$uzivatel) {
        chyba(sprintf('Uživatel %d neexistuje.', post('uzivatelKVyplaceniAktivity')));
    }
    if (!$uzivatel->gcPrihlasen()) {
        chyba(sprintf('Uživatel %s není přihlášen na GameCon.', $uzivatel->jmenoNick()));
    }
    $shop            = new Shop($uzivatel, null, $systemoveNastaveni);
    $prevedenaCastka = $shop->kupPrevodBonusuNaPenize();
    if (!$prevedenaCastka) {
        chyba(sprintf('Uživatel %s nemá žádný bonus k převodu.', $uzivatel->jmenoNick()));
    }
    $uzivatel->finance()->pripis(
        $prevedenaCastka,
        $u,
        post('poznamkaKVyplaceniBonusu')
    );
    $numberFormatter = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
    oznameni(sprintf('Bonus %s vyplacen uživateli %s.', $numberFormatter->formatCurrency($prevedenaCastka, 'CZK'), $uzivatel->jmenoNick()));
}

if (get('ajax') === 'uzivatel-k-vyplaceni-aktivity') {
    $organizatoriAkciQuery = dbQuery(<<<SQL
SELECT uzivatele_hodnoty.*
FROM uzivatele_hodnoty
JOIN platne_zidle_uzivatelu AS zidle_uzivatelu
    ON zidle_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND zidle_uzivatelu.id_zidle IN($0, $1)
GROUP BY uzivatele_hodnoty.id_uzivatele
SQL
        , [0 => Zidle::LETOSNI_VYPRAVEC, 1 => Zidle::PRIHLASEN_NA_LETOSNI_GC] // při změně změň hint v šabloně finance.xtpl
    );
    $numberFormatter       = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
    $organizatorAkciData   = [];
    while ($organizatorAkciRadek = mysqli_fetch_assoc($organizatoriAkciQuery)) {
        $organizatorAkci          = new Uzivatel($organizatorAkciRadek);
        $nevyuzityBonusZaAktivity = $organizatorAkci->finance()->nevyuzityBonusZaAktivity();
        if (!$nevyuzityBonusZaAktivity) {
            continue;
        }
        $organizatorAkciData[] = [
            'id'                       => $organizatorAkci->id(),
            'jmeno'                    => $organizatorAkci->jmenoNick(),
            'nevyuzityBonusZaAktivity' => $numberFormatter->formatCurrency($nevyuzityBonusZaAktivity, 'CZK'),
        ];
    }

    header('Content-type: application/json');
    echo json_encode(
        $organizatorAkciData,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if (post('pokojeImport')) {
    $f = fopen($_FILES['pokojeSoubor']['tmp_name'], 'rb');
    if (!$f) throw new Exception('Soubor se nepodařilo načíst');

    $hlavicka = array_flip(fgetcsv($f, 512, ";"));
    if (!array_key_exists('id_uzivatele', $hlavicka)) throw new Exception('Nepodařilo se zpracovat soubor');
    $uid   = $hlavicka['id_uzivatele'];
    $od    = $hlavicka['prvni_noc'];
    $do    = $hlavicka['posledni_noc'];
    $pokoj = $hlavicka['pokoj'];

    dbDelete('ubytovani', ['rok' => ROK]);

    while ($r = fgetcsv($f, 512, ";")) {
        if ($r[$pokoj]) {
            for ($den = $r[$od]; $den <= $r[$do]; $den++) {
                dbInsert('ubytovani', [
                    'id_uzivatele' => $r[$uid],
                    'den'          => $den,
                    'pokoj'        => $r[$pokoj],
                    'rok'          => ROK,
                ]);
            }
        }
    }

    oznameni('Import dokončen');
}

$x = new XTemplate('finance.xtpl');
if (isset($_GET['minimum'])) {
    $min = (int)$_GET['minimum'];
    $o   = dbQuery(<<<SQL
SELECT uzivatele_hodnoty.*
FROM uzivatele_hodnoty
JOIN platne_zidle_uzivatelu AS zidle_uzivatelu
    ON(zidle_uzivatelu.id_uzivatele=uzivatele_hodnoty.id_uzivatele AND zidle_uzivatelu.id_zidle=$0)
SQL,
        [Zidle::PRIHLASEN_NA_LETOSNI_GC]
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
    $ids ? $x->parse('finance.uzivatele') : $x->parse('finance.nikdo');
}

$x->assign([
    'id'  => $uPracovni ? $uPracovni->id() : null,
    'org' => $u->jmenoNick(),
]);
$x->parse('finance.pripsatSlevu');
$x->parse('finance.vyplatitBonusZaVedeniAktivity');

$x->assign('rok', ROK);

$x->assign('ubytovani', basename(__DIR__ . '/../../zvlastni/reporty/finance-report-ubytovani.php', '.php'));
$x->assign('bfgr', basename(__DIR__ . '/../../zvlastni/reporty/celkovy-report.php', '.php'));
$x->parse('finance.reporty');

$x->parse('finance');
$x->out('finance');

require __DIR__ . '/../_ubytovani-a-dalsi-obcasne-infopultakoviny-import-ubytovani.php';
require __DIR__ . '/../_ubytovani-a-dalsi-obcasne-infopultakoviny-import-balicku.php';
