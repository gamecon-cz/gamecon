<?php
require_once('sdilene-hlavicky.php');

$t = new XTemplate('stravenky.xtpl');

if(!isset($_GET['ciste'])) {

  $o = dbQuery('
    SELECT
      u.id_uzivatele, u.login_uzivatele,
      p.nazev
    FROM uzivatele_hodnoty u
    JOIN r_uzivatele_zidle z ON(z.id_uzivatele = u.id_uzivatele AND z.id_zidle = '.Z_PRIHLASEN.')
    JOIN shop_nakupy n ON(n.id_uzivatele = u.id_uzivatele AND n.rok = '.ROK.')
    JOIN shop_predmety p ON(p.id_predmetu = n.id_predmetu AND p.typ = '.Shop::JIDLO.')
    ORDER BY u.id_uzivatele, p.ubytovani_den DESC, p.nazev DESC
  ');

  $curr = mysqli_fetch_assoc($o);
  $next = mysqli_fetch_assoc($o);
  while($curr) {
    $t->assign($curr);
    $t->parse('stravenky.uzivatel.jidlo');
    if(!$next || $curr['id_uzivatele'] != $next['id_uzivatele']) $t->parse('stravenky.uzivatel');
    $curr = $next;
    $next = mysqli_fetch_assoc($o);
  }

  $t->parse('stravenky.upozorneni');

} else {

  $o = dbQuery('SELECT nazev FROM shop_predmety WHERE model_rok = '.ROK.' AND typ = '.Shop::JIDLO);
  while($r = mysqli_fetch_assoc($o)) $jidla[] = $r['nazev'];

  for($i = 0; $i < 24; $i++) {
    foreach($jidla as $jidlo) {
      $t->assign('nazev', $jidlo);
      $t->parse('stravenky.uzivatel.jidlo');
    }
    $t->parse('stravenky.uzivatel');
  }

  // Netisknout upozornění, protože se tiskne 1 list. Zabíralo by místo.

}

$t->parse('stravenky');
$t->out('stravenky');
