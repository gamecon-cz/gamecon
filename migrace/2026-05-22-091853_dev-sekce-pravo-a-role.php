<?php

/** @var \Godric\DbMigrations\Migration $this */

// ---------- právo: ADMINISTRACE_DEV (113) ----------
$this->q("
INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES (113, 'Administrace - panel Dev', 'Vývojářský koutek (preview prostředí, archivované ročníky, údržbové akce)')
ON DUPLICATE KEY UPDATE
    jmeno_prava = VALUES(jmeno_prava),
    popis_prava = VALUES(popis_prava)
");

// ---------- role: DEV (30) ----------
$this->q("
INSERT INTO role_seznam (
    id_role,
    kod_role,
    nazev_role,
    popis_role,
    rocnik_role,
    typ_role,
    vyznam_role,
    skryta,
    kategorie_role
)
VALUES (
    30,
    'DEV',
    'Dev',
    'Vývojář - přístup do sekce Dev v administraci',
    -1,
    'trvala',
    'DEV',
    0,
    0
)
ON DUPLICATE KEY UPDATE
    kod_role = VALUES(kod_role),
    nazev_role = VALUES(nazev_role),
    popis_role = VALUES(popis_role),
    rocnik_role = VALUES(rocnik_role),
    typ_role = VALUES(typ_role),
    vyznam_role = VALUES(vyznam_role),
    skryta = VALUES(skryta),
    kategorie_role = VALUES(kategorie_role)
");

// ---------- vazba role DEV -> právo ADMINISTRACE_DEV ----------
$this->q("
INSERT INTO prava_role (id_role, id_prava)
VALUES (30, 113)
ON DUPLICATE KEY UPDATE id_role = id_role
");
