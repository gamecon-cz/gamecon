<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin;

use Gamecon\Admin\VzhledAdminu;
use Gamecon\Admin\VzhledAdminuEnum;
use Gamecon\Tests\Db\AbstractTestDb;

class VzhledAdminuTest extends AbstractTestDb
{
    private function vytvorUzivatele(string $suffix): int
    {
        dbQuery(<<<'SQL'
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'VzhledAdminu'
SQL,
            [
                0 => 'test_vzhled_' . $suffix,
                1 => 'test.vzhled.' . $suffix . '@example.org',
            ],
        );

        return (int) dbInsertId();
    }

    public function testBezVolbyJeKlasickyANabidneSeVyber(): void
    {
        $idUzivatele = $this->vytvorUzivatele('bez_volby');
        $session = [];
        $vzhledAdminu = new VzhledAdminu($session);

        self::assertNull($vzhledAdminu->getZvolenyVzhled($idUzivatele));
        self::assertSame(VzhledAdminuEnum::KLASICKY, $vzhledAdminu->getVzhled($idUzivatele));
        self::assertTrue($vzhledAdminu->isNabidnoutVyber($idUzivatele));
    }

    public function testZvolenyVzhledSeUloziDoDatabaze(): void
    {
        $idUzivatele = $this->vytvorUzivatele('ulozeni');
        $session = [];

        $zvoleny = (new VzhledAdminu($session))->zpracujVolbu($idUzivatele, VzhledAdminuEnum::MODERNI->value);

        self::assertSame(VzhledAdminuEnum::MODERNI, $zvoleny);
        $jinaRelace = [];
        $vzhledZJineRelace = new VzhledAdminu($jinaRelace);
        self::assertSame(
            VzhledAdminuEnum::MODERNI,
            $vzhledZJineRelace->getVzhled($idUzivatele),
            'Volba patří uživateli, ne relaci, platí i po dalším přihlášení a na jiném zařízení',
        );
        self::assertFalse($vzhledZJineRelace->isNabidnoutVyber($idUzivatele));
    }

    public function testDalsiVolbaPrepiseTuPredchozi(): void
    {
        $idUzivatele = $this->vytvorUzivatele('prepis');
        $session = [];
        $vzhledAdminu = new VzhledAdminu($session);

        $vzhledAdminu->setVzhled($idUzivatele, VzhledAdminuEnum::MODERNI);
        $vzhledAdminu->setVzhled($idUzivatele, VzhledAdminuEnum::KLASICKY);

        $jinaRelace = [];
        self::assertSame(VzhledAdminuEnum::KLASICKY, (new VzhledAdminu($jinaRelace))->getZvolenyVzhled($idUzivatele));
        $pocetRadku = (int) dbOneCol(<<<'SQL'
SELECT COUNT(*) FROM uzivatele_vzhled_adminu WHERE id_uzivatele = $0
SQL,
            [
                0 => $idUzivatele,
            ],
        );
        self::assertSame(1, $pocetRadku);
    }

    public function testOdlozeniSchovaVyberJenProTutoRelaci(): void
    {
        $idUzivatele = $this->vytvorUzivatele('odlozeni');
        $session = [];
        $vzhledAdminu = new VzhledAdminu($session);

        self::assertNull($vzhledAdminu->zpracujVolbu($idUzivatele, VzhledAdminu::VOLBA_POZDEJI));

        self::assertFalse($vzhledAdminu->isNabidnoutVyber($idUzivatele));
        self::assertNull($vzhledAdminu->getZvolenyVzhled($idUzivatele), 'Odložení není volba vzhledu');
        $novaRelace = [];
        self::assertTrue(
            (new VzhledAdminu($novaRelace))->isNabidnoutVyber($idUzivatele),
            'Po dalším přihlášení se výběr nabídne znovu',
        );
        self::assertNotSame([], $session, 'Odložení se musí propsat do předané session');
    }

    public function testNeznamaVolbaNicNezmeni(): void
    {
        $idUzivatele = $this->vytvorUzivatele('neznama');
        $session = [];
        $vzhledAdminu = new VzhledAdminu($session);
        $vzhledAdminu->setVzhled($idUzivatele, VzhledAdminuEnum::MODERNI);

        self::assertNull($vzhledAdminu->zpracujVolbu($idUzivatele, 'tmavy'));

        $jinaRelace = [];
        self::assertSame(VzhledAdminuEnum::MODERNI, (new VzhledAdminu($jinaRelace))->getZvolenyVzhled($idUzivatele));
        self::assertSame([], $session);
    }

    public function testHodnotuZNovejsiVerzeBereJakoNezvolenou(): void
    {
        $idUzivatele = $this->vytvorUzivatele('novejsi');
        dbQuery(<<<'SQL'
INSERT INTO uzivatele_vzhled_adminu (id_uzivatele, vzhled) VALUES ($0, $1)
SQL,
            [
                0 => $idUzivatele,
                1 => 'vzhled-z-budoucnosti',
            ],
        );
        $session = [];
        $vzhledAdminu = new VzhledAdminu($session);

        self::assertNull($vzhledAdminu->getZvolenyVzhled($idUzivatele));
        self::assertSame(VzhledAdminu::VYCHOZI, $vzhledAdminu->getVzhled($idUzivatele));
    }

    public function testVolbaMaCiziKlicNaUzivatele(): void
    {
        $tabulkyOdkazujiciNaUzivatele = dbFetchAll(<<<'SQL'
SELECT TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'uzivatele_vzhled_adminu'
    AND REFERENCED_TABLE_NAME = 'uzivatele_hodnoty'
    AND REFERENCED_COLUMN_NAME = 'id_uzivatele'
SQL,
        );

        self::assertCount(
            1,
            $tabulkyOdkazujiciNaUzivatele,
            'Podle cizího klíče převádí UzivatelSlucovani volbu na sloučeného uživatele a smazání účtu ji smaže',
        );
    }

    public function testKazdyVzhledMaSablonu(): void
    {
        foreach (VzhledAdminuEnum::cases() as $vzhled) {
            self::assertFileExists(__DIR__ . '/../../admin/' . $vzhled->getSablona());
        }
    }
}
