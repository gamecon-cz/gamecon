<?php

use Gamecon\Role\Role;

$id = Role::MINI_ORG;
$vyznam = Role::VYZNAM_MINI_ORG;
$kod = 'MINI_ORG';
$popis = 'TODO: ';
$rocnik = -1;
$typ = 'trvala';
$skryta = 0;
$kategorie = 0;
$nazev = Role::nazevRolePodleId($id);

$this->q(<<<SQL
INSERT INTO role_seznam
SET
    id_role = $id,
    kod_role = '$kod',
    nazev_role = '$nazev',
    popis_role = '$popis',
    rocnik_role = $rocnik,
    typ_role = '$typ',
    vyznam_role = '$vyznam',
    skryta = $skryta,
    kategorie_role = $kategorie
SQL,
);

$sirienId = 102;

$result = $this->q(<<<SQL
INSERT INTO `role_texty_podle_uzivatele` (vyznam_role, id_uzivatele, popis_role)
VALUES (
        '$vyznam',
        $sirienId,
        'TODO: '
    )
SQL,
);


