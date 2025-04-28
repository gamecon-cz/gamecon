<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\XTemplate\XTemplate;

/**
 * Nástroj na zrušení storna za to, když se účastník nedostavil na aktivitu.
 *
 * nazev: Rušení storna
 * pravo: 108
 * submenu_group: 5
 */

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
    ' (' . $aktivita->denCasSkutecny() . ')'
  );
}

// vykreslení šablony
$t = new XTemplate(__DIR__ . '/ruseni-storna.xtpl');

$o = dbQuery('
  SELECT ap.id_akce, ap.id_uzivatele, a.nazev_akce, a.zacatek, aps.nazev AS nazev_stavu
  FROM akce_prihlaseni_spec ap
  JOIN akce_seznam a ON a.id_akce = ap.id_akce AND a.rok = $0
  JOIN akce_prihlaseni_stavy aps ON aps.id_stavu_prihlaseni = ap.id_stavu_prihlaseni
  WHERE aps.id_stavu_prihlaseni IN (3, 4)
  ORDER BY a.zacatek, a.nazev_akce
', [ROCNIK]);

foreach($o as $r) {
  $t->assign($r);
  $t->assign([
    'uzivatel' => Uzivatel::zId($r['id_uzivatele']),
  ]);
  $t->parse('ruseniStorna.storno');
}

if(dbAffectedOrNumRows($o) == 0)
  $t->parse('ruseniStorna.nikdo');

$t->parse('ruseniStorna');
$t->out('ruseniStorna');
