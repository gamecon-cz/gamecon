<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Role\Role;

/**
 * nazev: Promlčení zůstatků
 * pravo: 108
 * submenu_group: 5
 * TODO
 */

/** @var Uzivatel $u */
/** @var Gamecon\Vyjimkovac\Vyjimkovac $vyjimkovac */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

$p = new XTemplate(__DIR__ . '/promlceni.xtpl');

$jednaHraniceZustatku = post('jednaHraniceZustatku');
$druhaHraniceZustatku = post('druhaHraniceZustatku');

$jednaHraniceUcastiRoku = post('jednaHraniceUcastiRoku');
$druhaHraniceUcastiRoku = post('druhaHraniceUcastiRoku');

$vcetneInternich = (bool)post('vcetneInternich');

// provede promlčení zůstatku
if (post('promlcet')) {
    $idAdm = $u->id();
    $ids   = (array)post('id');
    $pocet = count($ids);
    $suma  = 0;

    foreach ($ids as $id) {
        $odpoved = dbOneLine('
      SELECT id_uzivatele, zustatek
      FROM uzivatele_hodnoty
      WHERE id_uzivatele = $0
    ', [$id]);

        $zustatek = $odpoved['zustatek'];
        $suma     += $zustatek;

        try {
            dbQuery('UPDATE uzivatele_hodnoty SET zustatek = 0 WHERE id_uzivatele = $0', [$id]);
        } catch (Throwable $throwable) {
            $vyjimkovac->zaloguj($throwable);
            chyba('Nepodařilo se aktualizovat údaje v databázi. Kontaktuj IT tým.');
        }

        $soubor = SPEC . '/promlceni-' . date('Y-m-d_H-i-s') . '.log';
        $cas    = date('Y-m-d H:i:s');
        $zprava = "Promlčení provedl admin s id:          $idAdm";
        file_put_contents($soubor, "$cas $zprava\n", FILE_APPEND);
        $zprava = "Promlčení zůstatku pro uživatele s id: $id";
        file_put_contents($soubor, "$cas $zprava\n", FILE_APPEND);
        $zprava = "Promlčená částka:                      $zustatek Kč" . "\n";
        file_put_contents($soubor, "$cas $zprava\n", FILE_APPEND);
    }

    oznameni(
        'Zůstatek promlčen pro ' . $pocet . ' uživatelů. V celkové výši ' . $suma . ' Kč',
        false,
    );
}

if (post('pripravit')) {
// kontrola hodnot ve formuláři
    if (!is_numeric($jednaHraniceZustatku)) {
        chyba('Zadej první hraniční částku jako celé číslo');
    }
    if ($druhaHraniceZustatku && !is_numeric($druhaHraniceZustatku)) {
        chyba('Druhou hraniční částku vynech, nebo ji zadej jako celé číslo');
    }

    if (!is_numeric($jednaHraniceUcastiRoku)) {
        chyba('Zadej první hranici účasti na GC jako celé kladné číslo');
    }
    if ($druhaHraniceUcastiRoku && !is_numeric($druhaHraniceUcastiRoku)) {
        chyba('Druhou hranici účasti na GC vynech, nebo ji zadej jako celé kladné číslo');
    }

    if ($jednaHraniceUcastiRoku < 0 || ($druhaHraniceUcastiRoku && $druhaHraniceUcastiRoku < 0)) {
        chyba('Roky účastí musí být kladné.');
    }
}

if (is_numeric($jednaHraniceZustatku) && is_numeric($jednaHraniceUcastiRoku)) {
// připraví seznam uživatelů pro promlčení zůstatku

    $ucast    = Role::TYP_UCAST;
    $pritomen = Role::VYZNAM_PRITOMEN;

    $o = dbQuery(<<<SQL
SELECT
    u.id_uzivatele AS uzivatel,
    u.jmeno_uzivatele AS jmeno,
    u.prijmeni_uzivatele AS prijmeni,
    u.zustatek,
    ucast.roky AS ucast,
    kladny_pohyb.cas_posledni_platby AS kladny_pohyb
FROM uzivatele_hodnoty u
LEFT JOIN (
    SELECT id_uzivatele, GROUP_CONCAT(role.rocnik_role ORDER BY role.rocnik_role ASC) AS roky,
    COUNT(*) AS pocet
    FROM platne_role_uzivatelu
    JOIN role_seznam AS role ON platne_role_uzivatelu.id_role = role.id_role
    WHERE role.typ_role = '$ucast' AND role.vyznam_role = '$pritomen'
    GROUP BY id_uzivatele
) AS ucast ON ucast.id_uzivatele = u.id_uzivatele
LEFT JOIN (
    SELECT id_uzivatele, MAX(provedeno) AS cas_posledni_platby
    FROM platby
    WHERE castka > 0
    GROUP BY id_uzivatele
) AS kladny_pohyb ON kladny_pohyb.id_uzivatele = u.id_uzivatele
WHERE
    IF (
        $1 IS NOT NULL,
        u.zustatek BETWEEN LEAST($0, $1) AND GREATEST($0, $1),
        u.zustatek >= $0
    )
    AND EXISTS(
            SELECT *
            FROM platne_role_uzivatelu
            JOIN role_seznam AS role ON platne_role_uzivatelu.id_role = role.id_role
            WHERE platne_role_uzivatelu.id_uzivatele = u.id_uzivatele
                AND role.typ_role = '$ucast' AND role.vyznam_role = '$pritomen'
                AND IF (
                    $3 IS NOT NULL,
                    role.rocnik_role BETWEEN LEAST($2, $3) AND GREATEST($2, $3),
                    role.rocnik_role >= $2
                )
    )
    AND IF (
        $4,
        TRUE,
        NOT EXISTS(SELECT * FROM uzivatele_role WHERE id_role IN ($5) AND u.id_uzivatele = uzivatele_role.id_uzivatele)
    )
SQL,
        [
            0 => $jednaHraniceZustatku,
            1 => (string)$druhaHraniceZustatku !== ''
                ? $druhaHraniceZustatku
                : null,
            2 => $jednaHraniceUcastiRoku,
            3 => (string)$druhaHraniceUcastiRoku !== ''
                ? $druhaHraniceUcastiRoku
                : null,
            4 => $vcetneInternich
                ? 1
                : 0,
            5 => [
                Role::ORGANIZATOR,
                Role::CESTNY_ORGANIZATOR,
                Role::LETOSNI_VYPRAVEC,
                Role::LETOSNI_PARTNER,
            ],
        ],
    );

    $ids = [];
    $p->assign('adminUrl', URL_ADMIN);
    $maxInputVars = (int)ini_get('max_input_vars'); // omezuje například POST
    $maxUzivatelu = $maxInputVars - 100;
    $poradi       = 1;
    while ($r = mysqli_fetch_assoc($o)) {
        $p->assign([
            'id'           => $r['uzivatel'],
            'jmeno'        => $r['jmeno'],
            'prijmeni'     => $r['prijmeni'],
            'stav'         => $r['zustatek'],
            'ucast'        => $r['ucast'],
            'kladny_pohyb' => $r['kladny_pohyb'],
        ]);
        $p->assign('disabled', $poradi > $maxUzivatelu ? 'disabled' : '');
        $p->parse('promlceni.detaily');
        $ids[] = $r['uzivatel'];
        $poradi++;
    }

    if (count($ids) == 0) {
        $p->parse('promlceni.nikdo');
    } else {
        $p->assign([
            'pocet'  => min($maxUzivatelu, count($ids)),
            'celkem' => count($ids),
        ]);
        if ($maxUzivatelu < count($ids)) {
            $p->parse('promlceni.nekdo.omezeni');
        }
        $p->parse('promlceni.nekdo');
    }
}

$soubory = [
    __DIR__ . '/../../../files/promlceni.js',
];
foreach ($soubory as $soubor) {
    $verze = md5_file($soubor);
    $url   = str_replace(__DIR__ . '/../../..', URL_ADMIN, $soubor);
    $p->assign('jsSoubor', $url);
    $p->parse('promlceni.jsSoubor');
}

$p->assign([
    'jednaHraniceZustatku'   => $jednaHraniceZustatku ?? 0,
    'druhaHraniceZustatku'   => $druhaHraniceZustatku ?? null,
    'checkedVcetneInternich' => $vcetneInternich ?? false
        ? 'checked'
        : '',
]);

$vybranaJednaUcastRoku = isset($jednaHraniceUcastiRoku)
    ? (int)$jednaHraniceUcastiRoku
    : null;
foreach (range($systemoveNastaveni->rocnik(), 2009) as $rocnik) {
    $p->assign('rocnik', $rocnik);
    $p->assign(
        'selected',
        $rocnik === $vybranaJednaUcastRoku
            ? 'selected'
            : '',
    );
    $p->parse('promlceni.jednaUcastRoku');
}

$vybranaDruhaUcastRoku = isset($druhaHraniceUcastiRoku)
    ? (int)$druhaHraniceUcastiRoku
    : null;
foreach (range($systemoveNastaveni->rocnik(), 2009) as $rocnik) {
    $p->assign('rocnik', $rocnik);
    $p->assign('selected', $rocnik === $vybranaDruhaUcastRoku);
    $p->parse('promlceni.druhaUcastRoku');
}

$p->parse('promlceni');
$p->out('promlceni');
