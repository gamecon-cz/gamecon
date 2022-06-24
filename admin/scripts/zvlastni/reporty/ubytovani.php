<?php
require __DIR__ . '/sdilene-hlavicky.php';

$o = dbQuery(<<<SQL
  SELECT uzivatele.id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, mesto_uzivatele, ulice_a_cp_uzivatele, op as cislo_op,
    GROUP_CONCAT(DISTINCT IF(
      predmety.nazev LIKE 'Spacák%' COLLATE utf8_czech_ci,
      'Spacák',
      SUBSTR(predmety.nazev,1, LOCATE(' ', predmety.nazev))
    )) as typ,
    GROUP_CONCAT(DISTINCT IFNULL(ubytovani.pokoj,'')) as pokoj,
    MIN(predmety.ubytovani_den) as prvni_noc,
    MAX(predmety.ubytovani_den) as posledni_noc,
    ubytovan_s
  FROM uzivatele_hodnoty uzivatele
  JOIN r_uzivatele_zidle zidle
      ON uzivatele.id_uzivatele=zidle.id_uzivatele AND zidle.id_zidle=$0 -- přihlášení na gc
  JOIN shop_nakupy nakupy
      ON nakupy.id_uzivatele=uzivatele.id_uzivatele AND nakupy.rok=$1 -- nákupy tento rok
  JOIN shop_predmety predmety
      ON predmety.id_predmetu=nakupy.id_predmetu AND predmety.typ=$2 -- info o předmětech k nákupům
  LEFT JOIN ubytovani
      ON ubytovani.id_uzivatele=uzivatele.id_uzivatele AND ubytovani.rok=$1 -- info o číslech pokoje
  GROUP BY uzivatele.id_uzivatele
  ORDER BY id_uzivatele
SQL,
    [
        \Gamecon\Zidle::PRIHLASEN_NA_LETOSNI_GC,
        ROK,
        Shop::UBYTOVANI,
    ]
);

$vystup = [];
while ($r = mysqli_fetch_assoc($o)) {
    $u = Uzivatel::zId($r['id_uzivatele']);
    $r['pozice'] = $u->status();
    $r['cislo_op'] = $u->cisloOp();
    $vystup[] = $r;
}

Report::zPole($vystup)->tFormat(get('format'));
