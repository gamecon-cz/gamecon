<?php

/**
 * Správa uživatelských práv a židlí (starý kód)
 *
 * nazev: Práva
 * pravo: 106
 */

use \Gamecon\Cas\DateTimeCz;

$zidle = @$req[1];

function zaloguj($zprava) {
  $cas = (new DateTimeCz())->formatDb();
  file_put_contents(SPEC . '/zidle.log', "$cas $zprava\n", FILE_APPEND);
}

if($z = get('posad')) {
  $uPracovni->dejZidli($z);
  zaloguj('Uživatel '.$u->jmenoNick()." posadil na židli $z uživatele ".$uPracovni->jmenoNick());
  back();
}

if($z = get('sesad')) {
  $uPracovni->vemZidli($z);
  zaloguj('Uživatel '.$u->jmenoNick()." sesadil ze židle $z uživatele ".$uPracovni->jmenoNick());
  back();
}

if($p = get('odeberPravo')) {
  dbQueryS('DELETE FROM r_prava_zidle WHERE id_prava = $1 AND id_zidle = $2', [$p, $zidle]);
  zaloguj('Uživatel '.$u->jmenoNick()." odebral židli $zidle právo $p");
  back();
}

if($p = get('dejPravo')) {
  dbInsert('r_prava_zidle', [ 'id_prava'=>$p, 'id_zidle'=>$zidle ]);
  zaloguj('Uživatel '.$u->jmenoNick()." přidal židli $zidle právo $p");
  back();
}

if($uid = get('sesadUzivatele')) {
  $u2 = Uzivatel::zId($uid);
  $u2->vemZidli($zidle);
  zaloguj('Uživatel '.$u->jmenoNick()." sesadil ze židle $zidle uživatele ".$u2->jmenoNick());
  back();
}


$t = new XTemplate('prava.xtpl');

if(!$zidle) {
  // výpis seznamu židlí
  $o = dbQueryS('
    SELECT z.*, uz.id_zidle IS NOT NULL as sedi
    FROM r_zidle_soupis z
    LEFT JOIN r_uzivatele_zidle uz ON(uz.id_zidle = z.id_zidle AND uz.id_uzivatele = $1)
    GROUP BY z.id_zidle
    ORDER BY z.id_zidle
    ', [$uPracovni ? $uPracovni->id() : null]
  );
  while($r = mysqli_fetch_assoc($o)) {
    $r['sedi'] = $r['sedi'] ? '<span style="color:#0d0;font-weight:bold">&bull;</span>' : '';
    $t->assign($r);
    if($r['id_zidle'] < 0 && floor(-$r['id_zidle'] / 100) == ROK - 2000) { //dočasná, letos
      $t->parse('prava.zidleDocasna');
    } elseif($r['id_zidle'] > 0) { //trvalá
      if($uPracovni && $r['sedi'])      $t->parse('prava.zidle.sesad');
      elseif($uPracovni && !$r['sedi']) $t->parse('prava.zidle.posad');
      $t->parse('prava.zidle');
    }
  }
  $t->parse('prava');
  $t->out('prava');
} else {
  // výpis detailu židle
  $o = dbQueryS('
    SELECT z.*, p.*
    FROM r_zidle_soupis z
    LEFT JOIN r_prava_zidle pz USING(id_zidle)
    LEFT JOIN r_prava_soupis p USING(id_prava)
    WHERE z.id_zidle = $1
    ', [$zidle]
  );
  while(($r = mysqli_fetch_assoc($o)) && $r['id_prava']) {
    $t->assign($r);
    $t->parse('zidle.pravo');
  }
  $t->assign('id_zidle', $zidle); // bugfix pro židle s 0 právy
  // nabídka židlí
  $o = dbQueryS('
    SELECT p.*
    FROM r_prava_soupis p
    LEFT JOIN r_prava_zidle pz ON(pz.id_prava = p.id_prava AND pz.id_zidle = $1)
    WHERE p.id_prava > 0 AND pz.id_prava IS NULL
    ORDER BY p.jmeno_prava
    ', [$zidle]
  );
  while($r = mysqli_fetch_assoc($o)) {
    $t->assign($r);
    $t->parse('zidle.pravoVyber');
  }
  // sedící uživatelé
  foreach(Uzivatel::zZidle($zidle) as $uz) {
    $t->assign('id', $uz->id());
    $t->assign('jmeno', $uz->jmeno());
    $t->assign('nick', $uz->nick());
    $t->parse('zidle.uzivatel');
  }
  // posazování
  if($uPracovni && !$uPracovni->maZidli($zidle))
    $t->parse('zidle.posad');
  elseif($uPracovni)
    $t->parse('zidle.sesad');
  $t->parse('zidle');
  $t->out('zidle');
}
