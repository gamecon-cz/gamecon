<?php
/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\Role\Role;
use Granam\RemoveDiacritics\RemoveDiacritics;

require_once __DIR__ . '/pomocne/rocnik_z_promenne_mysql.php';

// jen malý, neškodný hack, aby se tahle migrace pouštěla pořád
$this->setEndless(true);

// ROLE
$roleUcasi       = Role::vsechnyRoleUcastiProRocnik(ROCNIK);
$idRoliUcasti    = array_keys($roleUcasi);
$idRoliUcastiSql = implode(',', $idRoliUcasti);
$resultRoli      = $this->q(<<<SQL
SELECT id_role
FROM role_seznam
WHERE id_role IN ($idRoliUcastiSql)
SQL
);

$chybejiciRoleUcasti = $roleUcasi;
if ($resultRoli) {
    foreach ($resultRoli->fetch_all() as $idExistujiciRoleArray) {
        $idExistujiciRole = (int)(reset($idExistujiciRoleArray));
        unset($chybejiciRoleUcasti[$idExistujiciRole]);
    }
}

if ($chybejiciRoleUcasti) {
    $rocnik = rocnik_z_promenne_mysql();
    $ucast  = Role::TYP_UCAST;
    foreach ($chybejiciRoleUcasti as $idChybejiciRoleUcasti => $nazevChybejiciRoleUcasti) {
        $kodRole = RemoveDiacritics::toConstantLikeName($nazevChybejiciRoleUcasti);
        $vyznam  = Role::vyznamPodleKodu($kodRole);
        $this->q(<<<SQL
INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($idChybejiciRoleUcasti, '$kodRole', '$nazevChybejiciRoleUcasti', '$nazevChybejiciRoleUcasti', $rocnik, '$ucast', '$vyznam')
SQL
        );
    }
}
