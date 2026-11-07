<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Desk sales were written to SYSTEM, who is elsewhere the *performer* of an operation, so its financial overview
// mixed "SYSTEM paid" with "SYSTEM did this" and a role granted to SYSTEM would switch discounts on at the desk.
// A buyer without an account gets one of their own, shared by all years and without roles, with the fixed id 0.
// That needs NO_AUTO_VALUE_ON_ZERO: otherwise an inserted 0 means "assign the next id" and the row gets id 1.
$sqlMode = (string) $this->q('SELECT @@SESSION.sql_mode')->fetchColumn();
$this->q('SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ",NO_AUTO_VALUE_ON_ZERO")');

$this->q(<<<'SQL'
INSERT INTO uzivatele_hodnoty (
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

$this->q('SET SESSION sql_mode = ' . $this->connection->quote($sqlMode));

// The history moves to the new account: purchases and orders of every year, and the payments that are their
// counter-entry. Those are recognised by the text legacy writes to them, so bank movements and hand-entered
// payments of SYSTEM stay where they are.
$this->q('UPDATE shop_nakupy SET id_uzivatele = 0 WHERE id_uzivatele = 1');
$this->q('UPDATE shop_order SET customer_id = 0 WHERE customer_id = 1');
$this->q("UPDATE platby SET id_uzivatele = 0 WHERE id_uzivatele = 1 AND poznamka = 'anonymní prodej'");

// The payment taken for a desk sale was held together with it only by an equal time and amount, so a sale
// that did not finish left its payment behind unnoticed. History can be paired only where that is
// unambiguous: legacy wrote the payment and the purchase in the same second by the same operator, but only a
// second with exactly one order counts. Orders of that time group a whole day of sales, so they cannot pair them.
$this->q(<<<'SQL'
UPDATE platby
    INNER JOIN (
        SELECT platby.id AS payment_id,
               MIN(shop_nakupy.order_id) AS order_id
        FROM platby
            INNER JOIN shop_nakupy ON shop_nakupy.id_uzivatele = platby.id_uzivatele
                AND shop_nakupy.id_objednatele = platby.provedl
                AND shop_nakupy.datum = platby.provedeno
        WHERE platby.poznamka = 'anonymní prodej'
        GROUP BY platby.id
        HAVING COUNT(DISTINCT shop_nakupy.order_id) = 1
    ) AS pairing ON pairing.payment_id = platby.id
SET platby.order_id = pairing.order_id
SQL);
