<?php

use Gamecon\Role\Role;
use Gamecon\Shop\Shop;
use Gamecon\XTemplate\XTemplate;

/**
 * Koutek pro šéfa financí GC
 *
 * nazev: Peníze
 * pravo: 110
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
    $pripsanaSleva   = $uzivatel->finance()->pripisSlevu(
        post('sleva'),
        post('poznamkaKUzivateliProPripsaniSlevy'),
        $u,
    );
    $numberFormatter = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
    oznameni(
        sprintf(
            'Sleva %s připsána k uživateli %s.',
            $numberFormatter->formatCurrency(
                $pripsanaSleva,
                'CZK',
            ),
            $uzivatel->jmenoNick(),
        ),
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
    $shop            = new Shop($uzivatel, $u, $systemoveNastaveni);
    $prevedenaCastka = $shop->kupPrevodBonusuNaPenize();
    if (!$prevedenaCastka) {
        chyba(sprintf('Uživatel %s nemá žádný bonus k převodu.', $uzivatel->jmenoNick()));
    }
    $uzivatel->finance()->pripis(
        $prevedenaCastka,
        $u,
        post('poznamkaKVyplaceniBonusu'),
    );
    $numberFormatter = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
    oznameni(sprintf('Bonus %s vyplacen uživateli %s.', $numberFormatter->formatCurrency($prevedenaCastka, 'CZK'), $uzivatel->jmenoNick()));
}

if (get('ajax') === 'uzivatel-k-vyplaceni-aktivity') {
    $organizatoriAkciQuery = dbQuery(<<<SQL
SELECT uzivatele_hodnoty.*
FROM uzivatele_hodnoty
JOIN platne_role_uzivatelu
    ON platne_role_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND platne_role_uzivatelu.id_role IN($0, $1)
GROUP BY uzivatele_hodnoty.id_uzivatele
SQL
        , [0 => Role::LETOSNI_VYPRAVEC, 1 => Role::PRIHLASEN_NA_LETOSNI_GC], // při změně změň hint v šabloně finance.xtpl
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

// zpracování POST požadavků
if(post('zrusit')) {
    $uzivatel = Uzivatel::zId(post('uzivatelId'));
    $aktivita = Aktivita::zId(post('aktivitaId'));
    dbDelete('akce_prihlaseni_spec', [
        'id_akce'       =>  $aktivita->id(),
        'id_uzivatele'  =>  $uzivatel->id(),
    ]);
    oznameni(
        'Zrušeno storno pro ' . $uzivatel->jmenoNick() .
        ' za ' . $aktivita->nazev() .
        ' (' . $aktivita->denCas() . ')'
    );
}

$x = new XTemplate(__DIR__ . '/penize.xtpl');

$x->parse('penize.pripsatSlevu');
$x->parse('penize.vyplatitBonusZaVedeniAktivity');

$x->assign('rok', $systemoveNastaveni->rocnik());

$x->assign('ubytovani', basename(__DIR__ . '/../../zvlastni/reporty/finance-report-ubytovani.php', '.php'));
//$x->assign('bfgr', basename(__DIR__ . '/../../zvlastni/reporty/bfgr-report.php', '.php'));
$x->parse('penize.reporty');

$o = dbQuery('
  SELECT ap.id_akce, ap.id_uzivatele, a.nazev_akce, a.zacatek, aps.nazev AS nazev_stavu
  FROM akce_prihlaseni_spec ap
  JOIN akce_seznam a ON a.id_akce = ap.id_akce AND a.rok = $0
  JOIN akce_prihlaseni_stavy aps ON aps.id_stavu_prihlaseni = ap.id_stavu_prihlaseni
  WHERE aps.id_stavu_prihlaseni IN (3, 4)
  ORDER BY a.zacatek, a.nazev_akce
', [ROCNIK]);

$x->parse('penize');
$x->out('penize');

require __DIR__ . '/../_ubytovani-a-dalsi-obcasne-infopultakoviny-import-balicku.php';
