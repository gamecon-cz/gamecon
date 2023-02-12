<?php

/**
 * Správa uživatelských práv a židlí (starý kód)
 *
 * nazev: Práva
 * pravo: 106
 */

use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;
use Gamecon\Role\Role;
use Gamecon\Role\RoleSqlStruktura;

/** @var Uzivatel|null $uPracovni */
/** @var Uzivatel $u */

$role = $podstranka ?? null;

function zaloguj($zprava) {
    $cas = (new DateTimeCz())->formatDb();
    file_put_contents(SPEC . '/role.log', "$cas $zprava\n", FILE_APPEND);
}

if ($z = get('posad')) {
    if ($uPracovni) {
        $uPracovni->dejRoli($z, $u);
        zaloguj('Uživatel ' . $u->jmenoNick() . " posadil na roli $z uživatele " . $uPracovni->jmenoNick());
    }
    back();
}

if ($z = get('sesad')) {
    if ($uPracovni) {
        $uPracovni->vemRoli((int)$z, $u);
        zaloguj('Uživatel ' . $u->jmenoNick() . " sesadil ze role $z uživatele " . $uPracovni->jmenoNick());
    }
    back();
}

if ($role !== null && ($p = get('odeberPravo')) !== null) {
    dbQuery('DELETE FROM prava_role WHERE id_prava = $1 AND id_role = $2', [$p, $role]);
    zaloguj('Uživatel ' . $u->jmenoNick() . " odebral roli $role právo $p");
    back();
}

if ($role !== null && ($p = get('dejPravo')) !== null) {
    dbInsert('prava_role', ['id_prava' => $p, 'id_role' => $role]);
    zaloguj('Uživatel ' . $u->jmenoNick() . " přidal roli $role právo $p");
    back();
}

if ($role !== null && $uid = get('sesadUzivatele')) {
    $u2 = Uzivatel::zId($uid);
    $u2->vemRoli((int)$role, $u);
    zaloguj('Uživatel ' . $u->jmenoNick() . " sesadil ze role $role uživatele " . $u2->jmenoNick());
    back();
}

$t = new XTemplate('prava.xtpl');

if (!$role) {
    // výpis seznamu židlí
    $o            = dbQuery(
        'SELECT role.*, platne_role_uzivatelu.id_role IS NOT NULL AS sedi, platne_role_uzivatelu.posadil, platne_role_uzivatelu.posazen
    FROM role_seznam AS role
    LEFT JOIN platne_role_uzivatelu
        ON platne_role_uzivatelu.id_role = role.id_role AND platne_role_uzivatelu.id_uzivatele = $0
    WHERE role.rocnik_role IN ($1, $2)
    GROUP BY role.id_role, role.typ_role, role.nazev_role
    ORDER BY role.typ_role, role.nazev_role',
        [0 => $uPracovni?->id(), 1 => ROCNIK, 2 => Role::JAKYKOLI_ROCNIK]
    );
    $predchoziTyp = null;
    while ($r = mysqli_fetch_assoc($o)) {
        $r['sedi'] = $r['sedi'] ? '<span style="color:#0d0;font-weight:bold">&bull;</span>' : '';
        $t->assign($r);
        if ($r[RoleSqlStruktura::TYP_ROLE] === Role::TYP_UCAST) {
            if (Role::platiPouzeProRocnik($r[RoleSqlStruktura::ROCNIK_ROLE], ROCNIK)) {
                $t->parse('prava.roleUcast');
            } // 'else' jde o starou účast jako "GC2019 přijel" a ji nechceme ukazovat
        } elseif (Role::platiProRocnik($r[RoleSqlStruktura::ROCNIK_ROLE], ROCNIK)) {
            if ($predchoziTyp !== $r[RoleSqlStruktura::TYP_ROLE]) {
                if ($predchoziTyp !== null) {
                    $t->parse('prava.jedenTypRoli');
                }
                if ($r[RoleSqlStruktura::TYP_ROLE] === Role::TYP_TRVALA) {
                    $t->parse('prava.jedenTypRoli.roleTrvaleNadpis');
                } elseif ($r[RoleSqlStruktura::TYP_ROLE] === Role::TYP_ROCNIKOVA) {
                    $t->assign('rocnik', ROCNIK);
                    $t->parse('prava.jedenTypRoli.roleRocnikoveNadpis');
                }
            }
            if ($uPracovni && $r['sedi']) {
                if ($r['posadil']) {
                    $posazenKym = Uzivatel::zId($r['posadil']);
                    if ($posazenKym) {
                        $t->assign('posazenKym', $posazenKym->jmenoNick());
                        $t->assign('posazenKdy', DateTimeCz::createFromMysql($r['posazen'])->relativni());
                        $t->parse('prava.jedenTypRoli.prava.sesad.posazenKym');
                    }
                }
                $t->parse('prava.jedenTypRoli.prava.sesad');
            } elseif ($uPracovni && !$r['sedi']) {
                $t->parse('prava.jedenTypRoli.prava.posad');
            }
            $t->parse('prava.jedenTypRoli.prava');
        }
        $predchoziTyp = $r[RoleSqlStruktura::TYP_ROLE];
    }
    $t->parse('prava.jedenTypRoli');
    $t->parse('prava');
    $t->out('prava');
} else {
    // výpis detailu role
    $o = dbQuery(
        'SELECT role_seznam.*, r_prava_soupis.*
    FROM role_seznam
    LEFT JOIN prava_role USING(id_role)
    LEFT JOIN r_prava_soupis USING(id_prava)
    WHERE role_seznam.id_role = $1',
        [$role]
    );
    while (($r = mysqli_fetch_assoc($o)) && $r['id_prava']) {
        $r['jmeno_prava'] = nahradPlaceholderZaKonstantu($r['jmeno_prava']);
        $t->assign($r);
        $t->parse('prava.pravo');
    }
    $t->assign('id_role', $role); // bugfix pro role s 0 právy
    // nabídka židlí
    $o = dbQuery(
        'SELECT p.*
    FROM r_prava_soupis p
    LEFT JOIN prava_role pz ON(pz.id_prava = p.id_prava AND pz.id_role = $1)
    WHERE p.id_prava > 0 AND pz.id_prava IS NULL
    ORDER BY p.jmeno_prava',
        [$role]
    );
    while ($r = mysqli_fetch_assoc($o)) {
        $t->assign($r);
        $t->parse('prava.pravoVyber');
    }
    // sedící uživatelé
    foreach (Uzivatel::zRole($role) as $uz) {
        $t->assign('id', $uz->id());
        $t->assign('jmeno', $uz->jmeno());
        $t->assign('nick', $uz->nick());
        $t->parse('prava.uzivatel');
    }
    // posazování
    if ($uPracovni && !$uPracovni->maRoli($role)) {
        $t->parse('prava.posad');
    } elseif ($uPracovni) {
        $t->parse('prava.sesad');
    }
    $t->parse('prava');
    $t->out('prava');
}
