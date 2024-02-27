<?php

/**
 * Správa uživatelských práv a židlí (starý kód)
 *
 * nazev: Práva
 * pravo: 106
 */

use Gamecon\Cas\DateTimeCz;
use Gamecon\Role\Role;
use Gamecon\Role\SqlStruktura\RoleSqlStruktura;
use Gamecon\XTemplate\XTemplate;

/** @var Uzivatel|null $uPracovni */
/** @var Uzivatel $u */

$role = $podstranka ?? null;

$roleObjekt = $role
    ? Role::zId($role, true)
    : null;
if (!$roleObjekt || ($roleObjekt->kategorieRole() == Role::KATEGORIE_OMEZENA && !$u->maRoliClenRady())) {
    $role = null;
}
unset($roleObjekt);

function zaloguj($zprava)
{
    $cas = (new DateTimeCz())->formatDb();
    file_put_contents(SPEC . '/role.log', "$cas $zprava\n", FILE_APPEND);
}

if ($idRoleNaPrirazeni = (int)get('posad')) {
    if ($uPracovni && $u->maPravoNaPrirazeniRole($idRoleNaPrirazeni)) {
        $uPracovni->pridejRoli($idRoleNaPrirazeni, $u);
        zaloguj('Uživatel ' . $u->jmenoNick() . " posadil na roli $idRoleNaPrirazeni uživatele " . $uPracovni->jmenoNick());
    }
    back();
}

if ($idRoleNaOdebrani = (int)get('sesad')) {
    if ($uPracovni && $u->maPravoNaPrirazeniRole($idRoleNaOdebrani)) {
        $uPracovni->odeberRoli($idRoleNaOdebrani, $u);
        zaloguj('Uživatel ' . $u->jmenoNick() . " sesadil z role $idRoleNaOdebrani uživatele " . $uPracovni->jmenoNick());
    }
    back();
}

if (!$role) {
    $t                 = new XTemplate(__DIR__ . '/prava.xtpl');
    $povoleneKategorie = [Role::KATEGORIE_BEZNA];
    if ($u->maRoliClenRady()) {
        $povoleneKategorie[] = Role::KATEGORIE_OMEZENA;
    }
    // výpis seznamu židlí
    $o               = dbQuery(<<<SQL
SELECT role.id_role, role.kod_role, role.nazev_role,
       COALESCE(role_texty_podle_uzivatele.popis_role, role.popis_role) AS popis_role,
       role.rocnik_role, role.typ_role, role.vyznam_role, role.skryta, role.kategorie_role,
       platne_role_uzivatelu.id_role IS NOT NULL AS sedi,
       platne_role_uzivatelu.posadil,
       platne_role_uzivatelu.posazen
FROM role_seznam AS role
LEFT JOIN platne_role_uzivatelu
    ON platne_role_uzivatelu.id_role = role.id_role AND platne_role_uzivatelu.id_uzivatele = $0
LEFT JOIN role_texty_podle_uzivatele
    ON role_texty_podle_uzivatele.id_uzivatele = $3
    AND role_texty_podle_uzivatele.vyznam_role = role.vyznam_role
WHERE role.rocnik_role IN ($1)
    AND role.skryta = 0
    AND (role.kategorie_role IN ($2))
GROUP BY role.id_role, role.typ_role, role.nazev_role
ORDER BY role.typ_role, role.nazev_role
SQL,
        [
            0 => $uPracovni?->id(),
            1 => [ROCNIK, Role::JAKYKOLI_ROCNIK],
            2 => $povoleneKategorie,
            3 => $u->id(),
        ],
    );
    $predchoziTyp    = null;
    $vidiRoleBezPrav = false;
    while ($r = mysqli_fetch_assoc($o)) {
        $r['sedi'] = $r['sedi'] ? '<span style="color:#0d0;font-weight:bold">&bull;</span>' : '';
        $t->assign($r);
        if ($r[RoleSqlStruktura::TYP_ROLE] === Role::TYP_UCAST) {
            if (Role::platiPouzeProRocnik($r[RoleSqlStruktura::ROCNIK_ROLE], ROCNIK)) {
                $t->parse('prava.roleBezPrav.roleUcast');
                $vidiRoleBezPrav = true;
            } // 'else' jde o starou účast jako "GC2019 přijel" a ji nechceme ukazovat
        } else if (Role::platiProRocnik($r[RoleSqlStruktura::ROCNIK_ROLE], ROCNIK)) {
            if ($predchoziTyp !== $r[RoleSqlStruktura::TYP_ROLE]) {
                if ($predchoziTyp !== null) {
                    $t->parse('prava.jedenTypRoli');
                }
                if ($r[RoleSqlStruktura::TYP_ROLE] === Role::TYP_TRVALA) {
                    $t->parse('prava.jedenTypRoli.roleTrvaleNadpis');
                } else if ($r[RoleSqlStruktura::TYP_ROLE] === Role::TYP_ROCNIKOVA) {
                    $t->assign('rocnik', ROCNIK);
                    $t->parse('prava.jedenTypRoli.roleRocnikoveNadpis');
                }
            }
            if ($u->maPravoNaPrirazeniRole($r[RoleSqlStruktura::ID_ROLE])) {
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
                } else if ($uPracovni && !$r['sedi']) {
                    $t->parse('prava.jedenTypRoli.prava.akce.posad');
                }
                $t->parse('prava.jedenTypRoli.prava.akce');
            }
            $t->parse('prava.jedenTypRoli.prava');
        }
        $predchoziTyp = $r[RoleSqlStruktura::TYP_ROLE];
    }
    $t->parse('prava.jedenTypRoli');
    if ($vidiRoleBezPrav) {
        $t->parse('prava.roleBezPrav');
    }
    $t->parse('prava');
    $t->out('prava');
} else {
    include __DIR__ . '/_prava_jedne_role.php';
}
