<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\UserPermissions;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * Legacy grants a right through the platne_role_uzivatelu view: roles of this year, roles of
 * no year (-1) and participation roles of any year. The new layer must answer the same.
 */
class UserPermissionsTest extends AbstractDatabaseKernelTestCase
{
    private const PRAVO_LETOSNI_ROLE = 990101;
    private const PRAVO_LONSKE_ROLE = 990102;
    private const PRAVO_TRVALE_ROLE = 990103;
    private const PRAVO_LONSKE_UCASTI = 990104;

    private function role(int $idRole, int $rocnik, string $typ, int $idPrava): void
    {
        $this->connection()->executeStatement(
            "INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
             VALUES (:role, :kod, :kod, '', :rocnik, :typ, '')",
            [
                'role'   => $idRole,
                'kod'    => 'TEST_PRAVA_' . $idRole,
                'rocnik' => $rocnik,
                'typ'    => $typ,
            ],
        );
        $this->connection()->executeStatement(
            "INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava) VALUES (:pravo, :jmeno, '')",
            [
                'pravo' => $idPrava,
                'jmeno' => 'Test právo ' . $idPrava,
            ],
        );
        $this->connection()->executeStatement(
            'INSERT INTO prava_role (id_role, id_prava) VALUES (:role, :pravo)',
            [
                'role'  => $idRole,
                'pravo' => $idPrava,
            ],
        );
    }

    private function prirad(int $idUzivatele, int $idRole): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO uzivatele_role (id_uzivatele, id_role) VALUES (:uzivatel, :role)',
            [
                'uzivatel' => $idUzivatele,
                'role'     => $idRole,
            ],
        );
    }

    public function testRightsMatchLegacyForEveryKindOfRole(): void
    {
        $this->role(990001, ROCNIK, 'rocnikova', self::PRAVO_LETOSNI_ROLE);
        $this->role(990002, ROCNIK - 1, 'rocnikova', self::PRAVO_LONSKE_ROLE);
        $this->role(990003, -1, 'trvala', self::PRAVO_TRVALE_ROLE);
        $this->role(990004, ROCNIK - 1, 'ucast', self::PRAVO_LONSKE_UCASTI);
        $idUzivatele = $this->ucastnikVSql('prava_');
        foreach ([990001, 990002, 990003, 990004] as $idRole) {
            $this->prirad($idUzivatele, $idRole);
        }
        $uzivatel = $this->entityManager()->find(User::class, $idUzivatele);
        self::assertNotNull($uzivatel);
        $legacy = \Uzivatel::zId($idUzivatele);
        self::assertNotNull($legacy);
        $permissions = static::getContainer()->get(UserPermissions::class);

        $ocekavano = [
            self::PRAVO_LETOSNI_ROLE  => true,
            self::PRAVO_LONSKE_ROLE   => false,
            self::PRAVO_TRVALE_ROLE   => true,
            self::PRAVO_LONSKE_UCASTI => true,
        ];
        foreach ($ocekavano as $pravo => $ma) {
            self::assertSame($ma, $legacy->maPravo($pravo), "Legacy u práva {$pravo}");
            self::assertSame($ma, $permissions->has($uzivatel, $pravo, ROCNIK), "Nová vrstva u práva {$pravo}");
        }
    }

    /**
     * A role change reprices the cart within the request that made it, so the answer must not
     * come from anything loaded before the change.
     */
    public function testRoleGrantedWithinTheRequestCounts(): void
    {
        $this->role(990001, ROCNIK, 'rocnikova', self::PRAVO_LETOSNI_ROLE);
        $idUzivatele = $this->ucastnikVSql('prava_');
        $uzivatel = $this->entityManager()->find(User::class, $idUzivatele);
        self::assertNotNull($uzivatel);
        $permissions = static::getContainer()->get(UserPermissions::class);
        self::assertFalse($permissions->has($uzivatel, self::PRAVO_LETOSNI_ROLE, ROCNIK));

        $this->prirad($idUzivatele, 990001);

        self::assertTrue($permissions->has($uzivatel, self::PRAVO_LETOSNI_ROLE, ROCNIK));
    }
}
