<?php

/**
 * nazev: Promlčení zůstatků
 * pravo: 110
 * TODO
 */

$p = new XTemplate('promlceni.xtpl');

$p->assign([
  'castka' => 0,
  'rok'    => 3,
]);

// provede promlčení zůstatku
if(post('promlcet')) {
  $idAdm = $u->id();
  $ids = post('ids');
  $arrayId = explode(",", $ids);

  $pocet = count($arrayId);
  $suma = 0;
  foreach($arrayId as $id) {
    $uzp = Uzivatel::zId($id);
    $suma += $uzp->zustatek();
    $uzp->promlc($idAdm);
  }

  oznameni('Zůstatek promlčen pro ' . $pocet . ' uživatelů. V celkové výši ' . $suma . ' Kč');
}

// připraví seznam uživatelů pro promlčení zůstatku
if(post('pripravit')) {
  // kontrola hodnot ve formuláři
  if(post('castka') == null || is_numeric(post('castka')) == false) {
    chyba('Zadejte hraniční částku jako celé číslo větší nebo rovno 0');
  }

  if(post('castka') < 0) {
    chyba('Částka musí být větší nebo rovna 0');
  }

  if(post('rok') == null || is_numeric(post('rok')) == false) {
    chyba('Zadejte hranici let jako celé číslo větší než 0');
  }

  if(post('rok') <= 0) {
    chyba('Rok musí být větší než 0');
  }

  $castka = post('castka');
  $rok = post('rok') * (-1);  // v sql dotazu se odečítají roky

  $p->assign([
    'castka' => $castka,
    'rok'    => $rok * (-1),  // pevedení na kladné číslo do formuláře
  ]);

  $o = dbQuery(
    "SELECT u.id_uzivatele AS uzivatel,
      jmeno_uzivatele AS jmeno,
      prijmeni_uzivatele AS prijmeni,
      zustatek,
      ucast.roky AS ucast,
      pohyb.datum AS pohyb
    FROM uzivatele_hodnoty u LEFT JOIN
      (SELECT id_uzivatele,
        group_concat(2000-(id_zidle div 100) ORDER BY id_zidle DESC) AS roky,
        COUNT(id_zidle) AS pocet
      FROM r_uzivatele_zidle
        WHERE id_zidle < 0 AND id_zidle % 100 = -2
        GROUP BY id_uzivatele) ucast
        ON ucast.id_uzivatele = u.id_uzivatele
      LEFT JOIN
        (SELECT id_uzivatele, MAX(provedeno) AS datum
        FROM platby
        WHERE castka > 0
        GROUP BY id_uzivatele) pohyb
      ON pohyb.id_uzivatele = u.id_uzivatele
        WHERE zustatek > $1 AND pohyb.datum < DATE_ADD(CURRENT_DATE, INTERVAL $2 YEAR)", [$castka, $rok]
    );

  $ids = '';
  while($r = mysqli_fetch_assoc($o)) {
    $p->assign([
      'id'       => $r['uzivatel'],
      'jmeno'    => $r['jmeno'],
      'prijmeni' => $r['prijmeni'],
      'stav'     => $r['zustatek'],
      'ucast'    => $r['ucast'],
      'pohyb'    => $r['pohyb']
    ]);
    $p->parse('promlceni.detaily');
    $ids .= $r['uzivatel'] . ',';
  }

  if($ids == '') {
    $p->parse('promlceni.nikdo');
  } else {
    $p->assign(['ids' => substr($ids, 0, -1)]);
    $p->parse('promlceni.nekdo');
  }
}

$p->parse('promlceni');
$p->out('promlceni');
