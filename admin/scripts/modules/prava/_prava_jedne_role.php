<?php

use Gamecon\Pravo;
use Gamecon\Role\SqlStruktura\PravoSqlStruktura;
use Gamecon\Role\SqlStruktura\RoleSqlStruktura;
use Gamecon\XTemplate\XTemplate;

/** @var Uzivatel|null $uPracovni */
/** @var Uzivatel $u */
/** @var int|string $role */

if ($role !== null && $uid = get('sesadUzivatele')) {
    if ($u->maPravoNaPrirazeniRole((int)$role)) {
        $u2 = Uzivatel::zId($uid);
        $u2->odeberRoli((int)$role, $u);
        zaloguj('Uživatel ' . $u->jmenoNick() . " sesadil ze role $role uživatele " . $u2->jmenoNick());
    }
    back();
}

if ($role !== null && ($p = get('odeberPravo')) !== null) {
    if ($u->maPravoNaPrirazeniRole((int)$role) && $u->maPravo(Pravo::ZMENA_PRAV)) {
        dbQuery('DELETE FROM prava_role WHERE id_prava = $1 AND id_role = $2', [$p, $role]);
        zaloguj('Uživatel ' . $u->jmenoNick() . " odebral roli $role právo $p");
    }
    back();
}

if ($role !== null && ($p = get('dejPravo')) !== null) {
    if ($u->maPravo(Pravo::ZMENA_PRAV) && $u->maPravoNaPrirazeniRole((int)$role)) {
        dbInsert('prava_role', ['id_prava' => $p, 'id_role' => $role]);
        zaloguj('Uživatel ' . $u->jmenoNick() . " přidal roli $role právo $p");
    }
    back();
}

$t = new XTemplate(__DIR__ . '/_prava_jedne_role.xtpl');
// výpis detailu role
$o = dbQuery(
    'SELECT prava.id_prava, prava.jmeno_prava, prava.popis_prava, role.id_role
    FROM role_seznam AS role
    LEFT JOIN prava_role ON role.id_role = prava_role.id_role
    LEFT JOIN r_prava_soupis AS prava ON prava_role.id_prava = prava.id_prava
    WHERE role.id_role = $0',
    [0 => $role]
);
while (($r = mysqli_fetch_assoc($o)) && $r[PravoSqlStruktura::ID_PRAVA]) {
    $r[PravoSqlStruktura::JMENO_PRAVA] = nahradPlaceholderyZaNastaveni($r[PravoSqlStruktura::JMENO_PRAVA]);
    $t->assign($r);
    if ($u->maPravoNaPrirazeniRole($r[RoleSqlStruktura::ID_ROLE]) && $u->maPravo(Pravo::ZMENA_PRAV)) {
        $t->parse('pravaJedneRole.pravo.akce');
    }
    $t->parse('pravaJedneRole.pravo');
}
$t->assign('id_role', $role); // bugfix pro role s 0 právy

if ($u->maPravoNaPrirazeniRole((int)$role) && $u->maPravo(Pravo::ZMENA_PRAV)) {
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
        $r[PravoSqlStruktura::JMENO_PRAVA] = nahradNazvyKonstantZaHodnoty($r[PravoSqlStruktura::JMENO_PRAVA]);
        $t->assign($r);
        $t->parse('pravaJedneRole.akce.pravoVyber');
    }
    $t->parse('pravaJedneRole.akce');
}

// sedící uživatelé
foreach (Uzivatel::zRole($role) as $uz) {
    $t->assign('id', $uz->id());
    $t->assign('jmeno', $uz->celeJmeno());
    $t->assign('nick', $uz->nick());
    if ($u->maPravoNaPrirazeniRole((int)$role)) {
        $t->parse('pravaJedneRole.uzivatel.akce');
    }
    $t->parse('pravaJedneRole.uzivatel');
}

// Musí být před parse() tlačítek níž — XTemplate dosazuje hodnoty v okamžiku
// parse, takže později přiřazený nazev_role by v nich zůstal nevyplněný.
$detailyRole = dbFetchRow(<<<SQL
        SELECT nazev_role, IF(popis_role != '', popis_role, nazev_role) AS popis_role
        FROM role_seznam
        WHERE id_role = $0
        SQL,
    [$role]
);
$t->assign($detailyRole);

if ($u->maPravoNaPrirazeniRole((int)$role)) {
// posazování
    if ($uPracovni) {
        // Jméno i název role na tlačítku, ať je při práci s víc rolemi jasné,
        // koho a čeho se týká. Escapujeme kvůli value="…" — obojí je uživatelský
        // vstup a uvozovka v něm by atribut rozbila. Vlastní klíč pro název role,
        // protože {nazev_role} se jinde na stránce vypisuje neescapovaný.
        $t->assign('pracovniUzivatel', htmlspecialchars($uPracovni->jmenoNick(), ENT_QUOTES | ENT_HTML5));
        $t->assign(
            'nazevRoleProTlacitko',
            htmlspecialchars((string)($detailyRole['nazev_role'] ?? ''), ENT_QUOTES | ENT_HTML5),
        );
    }
    if ($uPracovni && !$uPracovni->maRoli($role)) {
        $t->parse('pravaJedneRole.akceUzivatel.posad');
    } elseif ($uPracovni) {
        $t->parse('pravaJedneRole.akceUzivatel.sesad');
    }
    $t->parse('pravaJedneRole.akceUzivatel');
}

$t->parse('pravaJedneRole');
$t->out('pravaJedneRole');
