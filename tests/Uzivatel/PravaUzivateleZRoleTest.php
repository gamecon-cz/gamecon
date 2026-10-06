<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Pravo;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Uživatelé načtení přes roli nebo URL jdou jinou cestou než ti z `zId()`; práva z ní musí
 * vyjít stejně, jinak se `maPravo()` s celočíselnými konstantami nikdy neshodne.
 */
class PravaUzivateleZRoleTest extends AbstractTestDb
{
    public function testUzivatelZRoleMaPravoSvePrideleneRole(): void
    {
        $idRole = -random_int(100000, 999999);
        dbQuery(
            "INSERT IGNORE INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava) VALUES ($0, 'test_pravo', 'test')",
            [Pravo::PORADANI_AKTIVIT],
        );
        dbQuery(
            "INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, $1, $2, '', -1, 'trvala', '')",
            [$idRole, 'TEST_Z_ROLE_' . uniqid('', false), 'Test role z role ' . uniqid('', false)],
        );
        dbQuery('INSERT INTO prava_role(id_role, id_prava) VALUES ($0, $1)', [$idRole, Pravo::PORADANI_AKTIVIT]);
        dbQuery(
            "INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = 'test_prava_z_role',
    email1_uzivatele = 'test.prava.z.role@example.org',
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'PravaZRole'",
        );
        $idUzivatele = dbInsertId();
        dbQuery(
            'INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)',
            [$idUzivatele, $idRole],
        );
        \Uzivatel::smazCache();

        $uzivatele = \Uzivatel::zRole($idRole);

        self::assertCount(1, $uzivatele);
        self::assertTrue($uzivatele[0]->maPravo(Pravo::PORADANI_AKTIVIT));
        self::assertFalse($uzivatele[0]->maPravo(Pravo::PLACKA_ZDARMA));
    }

    public function testUzivatelZRoleBezPravNemaZadnaPrava(): void
    {
        $idRole = -random_int(100000, 999999);
        dbQuery(
            "INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, $1, $2, '', -1, 'trvala', '')",
            [$idRole, 'TEST_BEZ_PRAV_' . uniqid('', false), 'Test role bez prav ' . uniqid('', false)],
        );
        dbQuery(
            "INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = 'test_bez_prav_z_role',
    email1_uzivatele = 'test.bez.prav.z.role@example.org',
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'BezPravZRole'",
        );
        dbQuery(
            'INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)',
            [dbInsertId(), $idRole],
        );
        \Uzivatel::smazCache();

        $uzivatele = \Uzivatel::zRole($idRole);

        self::assertCount(1, $uzivatele);
        self::assertSame([], $uzivatele[0]->prava());
    }
}
