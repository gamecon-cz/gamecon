<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Prodej na pultu se doteď zapisoval na uživatele SYSTEM, který je jinde v aplikaci
// *vykonavatelem* operace (import plateb, promlčení, hromadné odhlášení). Jedna identita
// ve dvou rolích: ve finančním přehledu SYSTEMu se pak míchají řádky „SYSTEM zaplatil"
// s řádky „SYSTEM to provedl", a role udělená SYSTEMu by pultu tiše zapnula slevy.
// Kupující dostává vlastní účet — sdílený přes všechny ročníky, záměrně bez rolí.
//
// Sloupce kopírují způsob, jakým je vyplněný SYSTEM: zástupné řetězce, prázdné heslo,
// pod kterým se nelze přihlásit. Login i e-mail mají unikátní index.
// Id 0 — „kdo to koupil? nikdo". Stejnou konvenci má anonym v Drupalu a root v Unixu:
// nula je mimo běžný prostor uživatelů. Musí být pevné, ne auto_increment: na prázdné
// databázi by účet sedl na id 2, které si testy vkládají natvrdo.
//
// NO_AUTO_VALUE_ON_ZERO je nutné, jinak AUTO_INCREMENT chápe vloženou nulu jako „přiděl
// další v pořadí" a řádek tiše dostane id 1.
$this->q('SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ",NO_AUTO_VALUE_ON_ZERO")');

$this->q(<<<SQL
INSERT IGNORE INTO uzivatele_hodnoty (
    id_uzivatele,
    login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele,
    ulice_a_cp_uzivatele, mesto_uzivatele, stat_uzivatele, psc_uzivatele,
    telefon_uzivatele, datum_narozeni, heslo_md5, email1_uzivatele,
    forum_razeni, random, pohlavi, registrovan, poznamka,
    pomoc_typ, pomoc_vice, op, infopult_poznamka, typ_dokladu_totoznosti
) VALUES (
    0,
    'ANONYM', 'ANONYM', 'ANONYM',
    'ANONYM', 'ANONYM', 1, 'ANONYM',
    'ANONYM', '2026-01-01', '', 'anonym@gamecon.cz',
    'a', '', 'm', NOW(), 'Kupující bez účtu — prodej na infopultu.',
    '', '', '', '', ''
)
SQL);

// Historie se přesouvá na nový účet: nákupy všech ročníků a k nim ty platby, které jsou
// jejich protizápisem. Platby se poznávají podle textu, který k nim píše legacy prodej —
// bankovní ani ručně zadané pohyby SYSTEMu se tím nedotknou.
$this->q(<<<SQL
UPDATE shop_nakupy
JOIN uzivatele_hodnoty AS anonym ON anonym.login_uzivatele = 'ANONYM'
SET shop_nakupy.id_uzivatele = anonym.id_uzivatele
WHERE shop_nakupy.id_uzivatele = 1
SQL);

$this->q(<<<SQL
UPDATE platby
JOIN uzivatele_hodnoty AS anonym ON anonym.login_uzivatele = 'ANONYM'
SET platby.id_uzivatele = anonym.id_uzivatele
WHERE platby.id_uzivatele = 1
  AND platby.poznamka = 'anonymní prodej'
SQL);

$this->q(<<<SQL
UPDATE shop_order
JOIN uzivatele_hodnoty AS anonym ON anonym.login_uzivatele = 'ANONYM'
SET shop_order.customer_id = anonym.id_uzivatele
WHERE shop_order.customer_id = 1
SQL);
