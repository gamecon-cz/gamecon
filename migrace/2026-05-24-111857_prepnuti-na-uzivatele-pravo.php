<?php

declare(strict_types=1);

use Gamecon\Pravo;
use Gamecon\Role\Role;

/** @var Godric\DbMigrations\Migration $this */

// Nahrazuje hardcoded konstantu SUPERADMINI. Schopnost přihlásit se (přepnout)
// v adminu jako libovolný uživatel se nově řídí právem PREPNUTI_NA_UZIVATELE,
// které visí na ROČNÍKOVÉ roli "Přepínání na uživatele". Role je dočasná –
// každý ročník se zakládá nová (viz 9999_01-letosni-role-krome-ucasti-endless),
// takže se přidělení každý rok resetuje a rada ho musí znovu udělit.

// ---------- právo: PREPNUTI_NA_UZIVATELE (114) ----------
$idPrava = Pravo::PREPNUTI_NA_UZIVATELE;
$this->q("
INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES ({$idPrava}, 'Přepnutí na uživatele', 'Smí se v administraci přihlásit (přepnout) jako libovolný uživatel')
ON DUPLICATE KEY UPDATE
    jmeno_prava = VALUES(jmeno_prava),
    popis_prava = VALUES(popis_prava)
");

// ---------- ročníková role pro LETOŠNÍ ročník ----------
// (budoucí ročníky zakládá endless migrace; tady řešíme jen ten současný)
$idRole = Role::LETOSNI_PREPINANI_UZIVATELE(ROCNIK);
$nazevRole = Role::nazevRolePodleId($idRole);
$kodRole = Role::prefixRocniku(ROCNIK) . '_' . Role::VYZNAM_PREPINANI_UZIVATELE;
$vyznam = Role::VYZNAM_PREPINANI_UZIVATELE;
$kategorie = Role::kategoriePodleVyznamu($vyznam); // KATEGORIE_OMEZENA – přiděluje jen rada
$typ = Role::TYP_ROCNIKOVA;

$this->q("
INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role, skryta, kategorie_role)
VALUES ({$idRole}, '{$kodRole}', '{$nazevRole}', 'Smí se v adminu přepnout na libovolného uživatele (jen letošní ročník)', " . ROCNIK . ", '{$typ}', '{$vyznam}', 0, {$kategorie})
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
