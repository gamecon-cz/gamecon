<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Pravo;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Právo "přepnutí na uživatele" (dříve hardcoded superadmin) nahradilo
 * konstantu SUPERADMINI. Přihlášení se jako libovolný uživatel v adminu
 * (admin/scripts/prihlaseni.php, admin/index.php) se nově řídí tímto právem
 * místo metody Uzivatel::jeSuperAdmin().
 */
class PrepnutiNaUzivateleTest extends AbstractTestDb
{
    private function vytvorUzivatele(string $suffix): \Uzivatel
    {
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'PrepnutiNaUzivatele'
SQL,
            [
                0 => 'test_prepnuti_' . $suffix,
                1 => 'test.prepnuti.' . $suffix . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    private function pridelPravo(\Uzivatel $uzivatel, int $idPrava): \Uzivatel
    {
        $unique = uniqid('', false);
        $idRole = -random_int(100000, 999999);
        $kodRole = 'TEST_PREPNUTI_' . $idPrava . '_' . $unique;
        dbQuery(<<<SQL
INSERT IGNORE INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
VALUES ($0, $1, 'test')
SQL,
            [
                0 => $idPrava,
                1 => 'test_pravo_' . $idPrava,
            ],
        );
        dbQuery(<<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, $1, $2, '', -1, 'trvala', '')
SQL,
            [
                0 => $idRole,
                1 => $kodRole,
                2 => 'Test role ' . $unique,
            ],
        );
        dbQuery(
            'INSERT INTO prava_role(id_role, id_prava) VALUES ($0, $1)',
            [$idRole, $idPrava],
        );
        dbQuery(
            'INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)',
            [$uzivatel->id(), $idRole],
        );

        \Uzivatel::smazCache();

        return \Uzivatel::zIdUrcite($uzivatel->id());
    }

    public function testUzivatelSPravemSmiPrepnoutNaUzivatele(): void
    {
        $uzivatel = $this->vytvorUzivatele('s_pravem');
        self::assertFalse(
            $uzivatel->maPravo(Pravo::PREPNUTI_NA_UZIVATELE),
            'Čerstvý uživatel bez role nesmí mít právo na přepnutí',
        );

        $uzivatel = $this->pridelPravo($uzivatel, Pravo::PREPNUTI_NA_UZIVATELE);
        self::assertTrue(
            $uzivatel->maPravo(Pravo::PREPNUTI_NA_UZIVATELE),
            'Uživatel s přiděleným právem smí přepnout na uživatele',
        );
    }

    public function testUzivatelBezPravaNesmiPrepnoutNaUzivatele(): void
    {
        $uzivatel = $this->vytvorUzivatele('bez_prava');
        // přidělíme jiné právo, ať má vůbec nějakou roli
        $uzivatel = $this->pridelPravo($uzivatel, Pravo::ADMINISTRACE_INFOPULT);

        self::assertFalse(
            $uzivatel->maPravo(Pravo::PREPNUTI_NA_UZIVATELE),
            'Uživatel bez práva na přepnutí nesmí přepnout na uživatele',
        );
    }
}
