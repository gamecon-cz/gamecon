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
use Gamecon\Pravo;

/** @var Uzivatel|null $uPracovni */
/** @var Uzivatel $u */

$role = $podstranka ?? null;

function zaloguj($zprava) {
    $cas = (new DateTimeCz())->formatDb();
    file_put_contents(SPEC . '/role.log', "$cas $zprava\n", FILE_APPEND);
}

if ($u->maPravo(Pravo::ZMENA_PRAV)) {
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
}

if (!$role) {
    $t = new XTemplate(__DIR__ . '/prava.xtpl');
    // výpis seznamu židlí
    $o            = dbQuery(
        'SELECT role.*, platne_role_uzivatelu.id_role IS NOT NULL AS sedi, platne_role_uzivatelu.posadil, platne_role_uzivatelu.posazen
    FROM role_seznam AS role
    LEFT JOIN platne_role_uzivatelu
        ON platne_role_uzivatelu.id_role = role.id_role AND platne_role_uzivatelu.id_uzivatele = $0
    WHERE role.rocnik_role IN ($1, $2)
        AND role.skryta = 0
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
            if ($u->maPravo(Pravo::ZMENA_PRAV)) {
                if ($uPracovni && $r['sedi']) {
                    if ($r['posadil']) {
                        $posazenKym = Uzivatel::zId($r['posadil']);
                        if ($posazenKym) {
                            $t->assign('posazenKym', $posazenKym->jmenoNick());
                            $t->assign('posazenKdy', DateTimeCz::createFromMysql($r['posazen'])->relativni());
                            $t->parse('prava.jedenTypRoli.prava.akce.sesad.posazenKym');
                        }
                    }
                    $t->parse('prava.jedenTypRoli.prava.akce.sesad');
                } elseif ($uPracovni && !$r['sedi']) {
                    $t->parse('prava.jedenTypRoli.prava.akce.posad');
                }
                $t->parse('prava.jedenTypRoli.prava.akce');
            }
            $t->parse('prava.jedenTypRoli.prava');
        }
        $predchoziTyp = $r[RoleSqlStruktura::TYP_ROLE];
    }
    $t->parse('prava.jedenTypRoli');
    $t->parse('prava');
    $t->out('prava');
} else {
    include __DIR__ . '/_prava_jedne_role.php';
}
