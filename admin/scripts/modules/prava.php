<?php

/**
 * Správa uživatelských práv a židlí (starý kód)
 *
 * nazev: Práva
 * pravo: 106
 */

use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Role\Zidle;

/** @var Uzivatel|null $uPracovni */
/** @var Uzivatel $u */

$zidle = $podstranka ?? null;

function zaloguj($zprava) {
    $cas = (new DateTimeCz())->formatDb();
    file_put_contents(SPEC . '/zidle.log', "$cas $zprava\n", FILE_APPEND);
}

if ($z = get('posad')) {
    if ($uPracovni) {
        $uPracovni->dejZidli($z, $u);
        zaloguj('Uživatel ' . $u->jmenoNick() . " posadil na židli $z uživatele " . $uPracovni->jmenoNick());
    }
    back();
}

if ($z = get('sesad')) {
    if ($uPracovni) {
        $uPracovni->vemZidli((int)$z, $u);
        zaloguj('Uživatel ' . $u->jmenoNick() . " sesadil ze židle $z uživatele " . $uPracovni->jmenoNick());
    }
    back();
}

if ($zidle !== null && ($p = get('odeberPravo')) !== null) {
    dbQuery('DELETE FROM r_prava_zidle WHERE id_prava = $1 AND id_zidle = $2', [$p, $zidle]);
    zaloguj('Uživatel ' . $u->jmenoNick() . " odebral židli $zidle právo $p");
    back();
}

if ($zidle !== null && ($p = get('dejPravo')) !== null) {
    dbInsert('r_prava_zidle', ['id_prava' => $p, 'id_zidle' => $zidle]);
    zaloguj('Uživatel ' . $u->jmenoNick() . " přidal židli $zidle právo $p");
    back();
}

if ($zidle !== null && $uid = get('sesadUzivatele')) {
    $u2 = Uzivatel::zId($uid);
    $u2->vemZidli((int)$zidle, $u);
    zaloguj('Uživatel ' . $u->jmenoNick() . " sesadil ze židle $zidle uživatele " . $u2->jmenoNick());
    back();
}

$t = new XTemplate('prava.xtpl');

if (!$zidle) {
    // výpis seznamu židlí
    $o            = dbQuery(
        'SELECT zidle.*, zidle_uzivatelu.id_zidle IS NOT NULL AS sedi, zidle_uzivatelu.posadil, zidle_uzivatelu.posazen
    FROM r_zidle_soupis AS zidle
    LEFT JOIN letos_platne_zidle_uzivatelu AS zidle_uzivatelu
        ON zidle_uzivatelu.id_zidle = zidle.id_zidle AND zidle_uzivatelu.id_uzivatele = $0
    WHERE zidle.rok IN ($1, $2)
    GROUP BY zidle.id_zidle, zidle.typ, zidle.jmeno_zidle
    ORDER BY zidle.typ, zidle.jmeno_zidle',
        [0 => $uPracovni?->id(), 1 => ROK, 2 => Zidle::JAKYKOLI_ROK]
    );
    $predchoziTyp = null;
    while ($r = mysqli_fetch_assoc($o)) {
        $r['sedi'] = $r['sedi'] ? '<span style="color:#0d0;font-weight:bold">&bull;</span>' : '';
        $t->assign($r);
        if ($r['typ'] === Zidle::TYP_UCAST) {
            if (Zidle::platiPouzeProRocnik($r['rok'], ROK)) {
                $t->parse('prava.zidleUcast');
            } // 'else' jde o starou účast jako "GC2019 přijel" a ji nechceme ukazovat
        } elseif (Zidle::platiProRocnik($r['rok'], ROK)) {
            if ($predchoziTyp !== $r['typ']) {
                if($predchoziTyp !== null) {
                    $t->parse('prava.jedenTypZidli');
                }
                if ($r['typ'] === Zidle::TYP_TRVALA) {
                    $t->parse('prava.jedenTypZidli.zidleTrvaleNadpis');
                } elseif ($r['typ'] === Zidle::TYP_ROCNIKOVA) {
                    $t->parse('prava.jedenTypZidli.zidleRocnikoveNadpis');
                }
            }
            if ($uPracovni && $r['sedi']) {
                if ($r['posadil']) {
                    $posazenKym = Uzivatel::zId($r['posadil']);
                    if ($posazenKym) {
                        $t->assign('posazenKym', $posazenKym->jmenoNick());
                        $t->assign('posazenKdy', DateTimeCz::createFromMysql($r['posazen'])->relativni());
                        $t->parse('prava.jedenTypZidli.zidle.sesad.posazenKym');
                    }
                }
                $t->parse('prava.jedenTypZidli.zidle.sesad');
            } elseif ($uPracovni && !$r['sedi']) {
                $t->parse('prava.jedenTypZidli.zidle.posad');
            }
            $t->parse('prava.jedenTypZidli.zidle');
        }
        $predchoziTyp = $r['typ'];
    }
    $t->parse('prava.jedenTypZidli');
    $t->parse('prava');
    $t->out('prava');
} else {
    // výpis detailu židle
    $o = dbQuery(
        'SELECT r_zidle_soupis.*, r_prava_soupis.*
    FROM r_zidle_soupis
    LEFT JOIN r_prava_zidle USING(id_zidle)
    LEFT JOIN r_prava_soupis USING(id_prava)
    WHERE r_zidle_soupis.id_zidle = $1',
        [$zidle]
    );
    while (($r = mysqli_fetch_assoc($o)) && $r['id_prava']) {
        $r['jmeno_prava'] = nahradPlaceholderZaKonstantu($r['jmeno_prava']);
        $t->assign($r);
        $t->parse('zidle.pravo');
    }
    $t->assign('id_zidle', $zidle); // bugfix pro židle s 0 právy
    // nabídka židlí
    $o = dbQuery(
        'SELECT p.*
    FROM r_prava_soupis p
    LEFT JOIN r_prava_zidle pz ON(pz.id_prava = p.id_prava AND pz.id_zidle = $1)
    WHERE p.id_prava > 0 AND pz.id_prava IS NULL
    ORDER BY p.jmeno_prava',
        [$zidle]
    );
    while ($r = mysqli_fetch_assoc($o)) {
        $t->assign($r);
        $t->parse('zidle.pravoVyber');
    }
    // sedící uživatelé
    foreach (Uzivatel::zZidle($zidle) as $uz) {
        $t->assign('id', $uz->id());
        $t->assign('jmeno', $uz->jmeno());
        $t->assign('nick', $uz->nick());
        $t->parse('zidle.uzivatel');
    }
    // posazování
    if ($uPracovni && !$uPracovni->maZidli($zidle)) {
        $t->parse('zidle.posad');
    } elseif ($uPracovni) {
        $t->parse('zidle.sesad');
    }
    $t->parse('zidle');
    $t->out('zidle');
}
