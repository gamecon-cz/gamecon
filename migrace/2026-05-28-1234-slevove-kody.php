<?php

declare(strict_types=1);

use Gamecon\Pravo;
use Gamecon\Role\Role;

/** @var Godric\DbMigrations\Migration $this */

$idPrava = Pravo::JEDNA_AKTIVITA_ZDARMA;
$this->q("
INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES ({$idPrava}, 'Jedna aktivita zdarma', 'Má jednu (nejdražší) aktivitu zdarma')
ON DUPLICATE KEY UPDATE
jmeno_prava = VALUES(jmeno_prava),
popis_prava = VALUES(popis_prava)
");

// ---------- ročníková role pro LETOŠNÍ ročník ----------
// (budoucí ročníky zakládá endless migrace; tady řešíme jen ten současný)
$idRole = Role::LETOSNI_JEDNA_AKTIVITA_ZDARMA(ROCNIK);
$nazevRole = Role::nazevRolePodleId($idRole);
$kodRole = Role::prefixRocniku(ROCNIK) . '_' . Role::VYZNAM_JEDNA_AKTIVITA_ZDARMA;
$vyznam = Role::VYZNAM_JEDNA_AKTIVITA_ZDARMA;
$kategorie = Role::kategoriePodleVyznamu($vyznam); // KATEGORIE_OMEZENA – přiděluje jen rada
$typ = Role::TYP_ROCNIKOVA;

$this->q("
INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role, skryta, kategorie_role)
VALUES ({$idRole}, '{$kodRole}', '{$nazevRole}', 'Má letos jednu (nejdražší) aktivitu zdarma.', " . ROCNIK . ", '{$typ}', '{$vyznam}', 0, {$kategorie})
ON DUPLICATE KEY UPDATE
kod_role = VALUES(kod_role),
nazev_role = VALUES(nazev_role),
popis_role = VALUES(popis_role),
typ_role = VALUES(typ_role),
vyznam_role = VALUES(vyznam_role),
kategorie_role = VALUES(kategorie_role)
");

// ---------- vazba role -> právo ----------
$this->q("
INSERT INTO prava_role (id_role, id_prava)
VALUES ({$idRole}, {$idPrava})
ON DUPLICATE KEY UPDATE id_role = id_role
");

$this->q("
CREATE TABLE slevove_kody (
    id bigint auto_increment PRIMARY KEY,
    kod VARCHAR(255) not null unique,
    createdBy bigint unsigned NOT NULL references uzivatele_hodnoty(id_uzivatele),
    createdAt datetime NOT NULL,
    usedBy bigint unsigned NULL references uzivatele_hodnoty(id_uzivatele),
    usedAt datetime NULL,
    invalidated boolean not null default false
);
");
