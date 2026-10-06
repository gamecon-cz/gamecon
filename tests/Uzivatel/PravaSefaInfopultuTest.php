<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Pravo;
use Gamecon\Role\Role;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Pravomoci šéfa infopultu přešly z role na práva. Role šéfa infopultu je drží všechna, takže pro
 * stávající držitele se nic nezměnilo, ale každé jde přidělit zvlášť.
 */
class PravaSefaInfopultuTest extends AbstractTestDb
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function pravaSefaProvider(): iterable
    {
        yield 'přeplnit ubytování' => [Pravo::MUZE_PRETIZIT_UBYTOVANI];
        yield 'zamykat a odemykat týmy' => [Pravo::MUZE_ZAMYKAT_TYMY];
        yield 'nepotvrzovat na infopultu' => [Pravo::NEMUSI_POTVRZOVAT_NA_INFOPULTU];
    }

    /**
     * @dataProvider pravaSefaProvider
     */
    public function testPravoMaRoleSefaInfopultu(int $idPrava): void
    {
        $roleSPravem = dbOneArray('SELECT id_role FROM prava_role WHERE id_prava = $0', [$idPrava]);

        self::assertContains(Role::SEF_INFOPULTU, array_map('intval', $roleSPravem));
    }

    /**
     * @dataProvider pravaSefaProvider
     */
    public function testPravoMaSefInfopultuAOstatniNe(int $idPrava): void
    {
        $sef = $this->vytvorUzivatele('sef');
        $ostatni = $this->vytvorUzivatele('ostatni');
        dbQuery(
            'INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)',
            [$sef->id(), Role::SEF_INFOPULTU],
        );
        \Uzivatel::smazCache();

        self::assertTrue(\Uzivatel::zIdUrcite($sef->id())->maPravo($idPrava));
        self::assertFalse(\Uzivatel::zIdUrcite($ostatni->id())->maPravo($idPrava));
    }

    private function vytvorUzivatele(string $suffix): \Uzivatel
    {
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'PravaSefa'
SQL,
            [
                0 => 'test_prava_sefa_' . $suffix,
                1 => 'test.prava.sefa.' . $suffix . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }
}
