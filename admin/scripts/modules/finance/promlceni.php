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

$p = new XTemplate(__DIR__ . '/promlceni.xtpl');

$p->assign([
    'jednaHraniceZustatku'   => 0,
    'druhaHraniceZustatku'   => null,
    'jednaHraniceStariRoku'  => 3,
    'druhaHraniceStariRoku'  => null,
    'checkedVcetneInternich' => '',
]);

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

    oznameni('Zůstatek promlčen pro ' . $pocet . ' uživatelů. V celkové výši ' . $suma . ' Kč');
}

// připraví seznam uživatelů pro promlčení zůstatku
if (post('pripravit')) {
    $jednaHraniceZustatku = post('jednaHraniceZustatku');
    $druhaHraniceZustatku = post('druhaHraniceZustatku');

    $jednaHraniceStariRoku = post('jednaHraniceStariRoku');
    $druhaHraniceStariRoku = post('druhaHraniceStariRoku');

    $vcetneInternich = (bool)post('vcetneInternich');

    // kontrola hodnot ve formuláři
    if (!is_numeric($jednaHraniceZustatku)) {
        chyba('Zadej první hraniční částku jako celé číslo');
    }
    if ($druhaHraniceZustatku && !is_numeric($druhaHraniceZustatku)) {
        chyba('Druhou hraniční částku vynech, nebo ji zadej jako celé číslo');
    }

    if (!is_numeric($jednaHraniceStariRoku)) {
        chyba('Zadej první hraniční stáří poslední platby jako celé kladné číslo');
    }
    if ($druhaHraniceStariRoku && !is_numeric($druhaHraniceStariRoku)) {
        chyba('Druhé hraniční stáří poslední platby vynech, nebo ho zadej jako celé kladné číslo');
    }

    if ($jednaHraniceStariRoku < 0 || ($druhaHraniceStariRoku && $druhaHraniceStariRoku < 0)) {
        chyba('Stáří plateb musí být kladné.');
    }

    $p->assign([
        'jednaHraniceZustatku'   => $jednaHraniceZustatku,
        'druhaHraniceZustatku'   => $druhaHraniceZustatku,
        'jednaHraniceStariRoku'  => $jednaHraniceStariRoku,
        'druhaHraniceStariRoku'  => $druhaHraniceStariRoku,
        'checkedVcetneInternich' => $vcetneInternich
            ? 'checked'
            : '',
    ]);

    $ucast    = Role::TYP_UCAST;
    $pritomen = Role::VYZNAM_PRITOMEN;

    $o = dbQuery(<<<SQL
SELECT
    u.id_uzivatele AS uzivatel,
    u.jmeno_uzivatele AS jmeno,
    u.prijmeni_uzivatele AS prijmeni,
    u.zustatek,
    ucast.roky AS ucast,
    pohyb.datum AS pohyb
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
    SELECT id_uzivatele, MAX(provedeno) AS datum
    FROM platby
    WHERE castka > 0
    GROUP BY id_uzivatele
) AS pohyb ON pohyb.id_uzivatele = u.id_uzivatele
WHERE
    IF ($1 IS NOT NULL, zustatek BETWEEN $0 AND $1, zustatek > $0)
    AND IF (
        $3 IS NOT NULL,
        pohyb.datum BETWEEN DATE_SUB(CURRENT_DATE, INTERVAL GREATEST($2, $3) YEAR) AND DATE_SUB(CURRENT_DATE, INTERVAL LEAST($2, $3) YEAR),
        pohyb.datum >= DATE_SUB(CURRENT_DATE, INTERVAL $2 YEAR)
    )
    AND IF ($4, TRUE, NOT EXISTS(SELECT * FROM uzivatele_role WHERE id_role IN ($5) AND u.id_uzivatele = uzivatele_role.id_uzivatele))
SQL,
        [
            0 => $jednaHraniceZustatku,
            1 => (string)$druhaHraniceZustatku !== ''
                ? $druhaHraniceZustatku
                : null,
            2 => $jednaHraniceStariRoku,
            3 => (string)$druhaHraniceStariRoku !== ''
                ? $druhaHraniceStariRoku
                : null,
            4 => $vcetneInternich,
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
    while ($r = mysqli_fetch_assoc($o)) {
        $p->assign([
            'id'       => $r['uzivatel'],
            'jmeno'    => $r['jmeno'],
            'prijmeni' => $r['prijmeni'],
            'stav'     => $r['zustatek'],
            'ucast'    => $r['ucast'],
            'pohyb'    => $r['pohyb'],
        ]);
        $p->parse('promlceni.detaily');
        $ids[] = $r['uzivatel'];
    }

    if (count($ids) == 0) {
        $p->parse('promlceni.nikdo');
    } else {
        $p->assign([
            'ids'    => implode(',', $ids),
            'celkem' => count($ids),
        ]);
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

$p->parse('promlceni');
$p->out('promlceni');
