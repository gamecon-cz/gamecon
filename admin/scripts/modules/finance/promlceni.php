<?php

use Gamecon\XTemplate\XTemplate;

/**
 * nazev: Promlčení zůstatků
 * pravo: 108
 * submenu_group: 5
 * TODO
 */

/** @var Uzivatel $u */

$p = new XTemplate('promlceni.xtpl');

$p->assign([
    'castka' => 0,
    'pocetLet' => 3,
]);

// provede promlčení zůstatku
if (post('promlcet')) {
    $idAdm = $u->id();
    $idsArray = post('ids');
    $ids = explode(",", $idsArray);

    $pocet = count($ids);
    $suma = 0;

    foreach ($ids as $id) {
        $odpoved = dbOneLine('
      SELECT id_uzivatele, zustatek
      FROM uzivatele_hodnoty
      WHERE id_uzivatele = $0
    ', [$id]);

        $zustatek = $odpoved['zustatek'];
        $suma += $zustatek;

        try {
            dbQuery('UPDATE uzivatele_hodnoty SET zustatek = 0 WHERE id_uzivatele = $0', [$id]);
        } catch (Exception $exc) {
            chyba('Nepodařilo se aktualizovat údaje v databázi kontaktuj ihned IT tým.');
        }

        $soubor = SPEC . '/promlceni.log';
        $cas = date('Y-m-d H:i:s');
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
    // kontrola hodnot ve formuláři
    if (!is_numeric(post('castka'))) {
        chyba('Zadejte hraniční částku jako celé číslo větší nebo rovno 0');
    }

    if (post('castka') < 0) {
        chyba('Částka musí být větší nebo rovna 0');
    }

    if (!is_numeric(post('pocetLet')) || post('pocetLet') <= 0) {
        chyba('Zadejte hranici let jako celé číslo větší než 0');
    }

    $castka = post('castka');
    $pocetLet = post('pocetLet') * (-1);  // v sql dotazu se odečítá počet let

    $p->assign([
        'castka' => $castka,
        'pocetLet' => $pocetLet * (-1),  // pevedení na kladné číslo do formuláře
    ]);

    $o = dbQuery(
        "SELECT
    u.id_uzivatele AS uzivatel,
    jmeno_uzivatele AS jmeno,
    prijmeni_uzivatele AS prijmeni,
    zustatek,
    ucast.roky AS ucast,
    pohyb.datum AS pohyb
  FROM uzivatele_hodnoty u
  LEFT JOIN (
    SELECT id_uzivatele, group_concat(2000-(id_zidle div 100)
    ORDER BY id_zidle DESC) AS roky,
    COUNT(id_zidle) AS pocet
    FROM r_uzivatele_zidle
    WHERE id_zidle < 0 AND id_zidle % 100 = -2
    GROUP BY id_uzivatele
  ) ucast ON ucast.id_uzivatele = u.id_uzivatele
  LEFT JOIN (
    SELECT id_uzivatele, MAX(provedeno) AS datum
    FROM platby
    WHERE castka > 0
    GROUP BY id_uzivatele
  ) pohyb ON pohyb.id_uzivatele = u.id_uzivatele
  WHERE
    zustatek > $1 AND
    pohyb.datum < DATE_ADD(CURRENT_DATE, INTERVAL $2 YEAR)", [$castka, $pocetLet]
    );

    $ids = [];
    while ($r = mysqli_fetch_assoc($o)) {
        $p->assign([
            'id' => $r['uzivatel'],
            'jmeno' => $r['jmeno'],
            'prijmeni' => $r['prijmeni'],
            'stav' => $r['zustatek'],
            'ucast' => $r['ucast'],
            'pohyb' => $r['pohyb'],
        ]);
        $p->parse('promlceni.detaily');
        $ids[] = $r['uzivatel'];
    }

    if (count($ids) == 0) {
        $p->parse('promlceni.nikdo');
    } else {
        $p->assign(['ids' => implode(',', $ids)]);
        $p->parse('promlceni.nekdo');
    }
}

$p->parse('promlceni');
$p->out('promlceni');
