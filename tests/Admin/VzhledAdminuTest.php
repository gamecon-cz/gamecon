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
        dbExecute(<<<'SQL'
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
        self::assertFalse(
            (new VzhledAdminu($session))->isNabidnoutVyber($idUzivatele),
            'Odložení se musí propsat do předané session, platí i pro další požadavky',
        );
    }

    public function testOdlozeniPlatiProCelouRelaci(): void
    {
        $idAdmina = $this->vytvorUzivatele('odlozeni_admin');
        $idVypravece = $this->vytvorUzivatele('odlozeni_vypravec');
        $session = [];

        (new VzhledAdminu($session))->zpracujVolbu($idAdmina, VzhledAdminu::VOLBA_POZDEJI);

        self::assertFalse(
            (new VzhledAdminu($session))->isNabidnoutVyber($idVypravece),
            'Po „Přihlásit se jako“ u zařízení sedí pořád ten, kdo odložil, dialog ho nesmí nutit volit za vypravěče',
        );
    }

    public function testVolbaZJinehoZarizeniPlatiOdDalsihoPozadavku(): void
    {
        $idUzivatele = $this->vytvorUzivatele('jine_zarizeni');
        $session = [];
        (new VzhledAdminu($session))->setVzhled($idUzivatele, VzhledAdminuEnum::KLASICKY);

        $jinaRelace = [];
        (new VzhledAdminu($jinaRelace))->setVzhled($idUzivatele, VzhledAdminuEnum::MODERNI);

        self::assertSame(VzhledAdminuEnum::MODERNI, (new VzhledAdminu($session))->getVzhled($idUzivatele));
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
        dbExecute(<<<'SQL'
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

    public function testZobrazeniStranekSeSectouPoDnechAVzhledech(): void
    {
        $idUzivatele = $this->vytvorUzivatele('pouziti');
        $session = [];
        $vzhledAdminu = new VzhledAdminu($session);
        $prvniDen = new \DateTimeImmutable('2026-11-02 09:00:00');

        $vzhledAdminu->zaznamenejZobrazeniStranky($idUzivatele, $prvniDen);
        $vzhledAdminu->zaznamenejZobrazeniStranky($idUzivatele, $prvniDen->modify('+3 hours'));
        $vzhledAdminu->setVzhled($idUzivatele, VzhledAdminuEnum::MODERNI);
        $vzhledAdminu->zaznamenejZobrazeniStranky($idUzivatele, $prvniDen->modify('+4 hours'));
        $vzhledAdminu->zaznamenejZobrazeniStranky($idUzivatele, $prvniDen->modify('+1 day'));

        self::assertSame(
            [
                [
                    'den'             => '2026-11-02',
                    'vzhled'          => 'klasicky',
                    'zvoleny'         => '0',
                    'pocet_zobrazeni' => '2',
                ],
                [
                    'den'             => '2026-11-02',
                    'vzhled'          => 'moderni',
                    'zvoleny'         => '1',
                    'pocet_zobrazeni' => '1',
                ],
                [
                    'den'             => '2026-11-03',
                    'vzhled'          => 'moderni',
                    'zvoleny'         => '1',
                    'pocet_zobrazeni' => '1',
                ],
            ],
            dbFetchAll(<<<'SQL'
SELECT den, vzhled, zvoleny, pocet_zobrazeni
FROM uzivatele_vzhled_adminu_pouziti
WHERE id_uzivatele = $0
ORDER BY den, vzhled
SQL,
                [
                    0 => $idUzivatele,
                ],
            ),
            'Dokud si uživatel nevybral, počítá se mu klasický vzhled jako nezvolený',
        );
    }

    public function testZvolenyKlasickyOdlisiOdVychoziho(): void
    {
        $idUzivatele = $this->vytvorUzivatele('pouziti_klasicky');
        $session = [];
        $vzhledAdminu = new VzhledAdminu($session);
        $den = new \DateTimeImmutable('2026-11-02 09:00:00');

        $vzhledAdminu->zaznamenejZobrazeniStranky($idUzivatele, $den);
        $vzhledAdminu->setVzhled($idUzivatele, VzhledAdminuEnum::KLASICKY);
        $vzhledAdminu->zaznamenejZobrazeniStranky($idUzivatele, $den);

        self::assertSame(
            [
                'zvoleny'         => '1',
                'pocet_zobrazeni' => '2',
            ],
            dbOneLine(<<<'SQL'
SELECT zvoleny, pocet_zobrazeni FROM uzivatele_vzhled_adminu_pouziti WHERE id_uzivatele = $0
SQL,
                [
                    0 => $idUzivatele,
                ],
            ),
            'Den, kdy si klasický vybral, už je zvolený',
        );
    }

    public function testZobrazeniStrankyJeJenOtevreniStranky(): void
    {
        self::assertTrue(VzhledAdminu::isZobrazeniStranky([
            'REQUEST_METHOD'      => 'GET',
            'HTTP_SEC_FETCH_DEST' => 'document',
        ]));
        self::assertTrue(VzhledAdminu::isZobrazeniStranky([
            'REQUEST_METHOD' => 'GET',
        ]), 'Prohlížeč bez Sec-Fetch-Dest');
        self::assertFalse(
            VzhledAdminu::isZobrazeniStranky([
                'REQUEST_METHOD'      => 'POST',
                'HTTP_SEC_FETCH_DEST' => 'document',
            ]),
            'Odeslaný formulář přesměruje na stránku, ta se započítá sama',
        );
        self::assertFalse(VzhledAdminu::isZobrazeniStranky([
            'REQUEST_METHOD'      => 'GET',
            'HTTP_SEC_FETCH_DEST' => 'empty',
        ]), 'fetch()');
        self::assertFalse(VzhledAdminu::isZobrazeniStranky([
            'REQUEST_METHOD'        => 'GET',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]), 'jQuery ajax');
        self::assertFalse(VzhledAdminu::isZobrazeniStranky([
            'REQUEST_METHOD'      => 'GET',
            'HTTP_SEC_FETCH_DEST' => 'iframe',
        ]));
    }

    public function testPouzitiMaCiziKlicNaUzivatele(): void
    {
        self::assertSame(
            '1',
            dbOneCol(<<<'SQL'
SELECT COUNT(*)
FROM information_schema.KEY_COLUMN_USAGE
WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'uzivatele_vzhled_adminu_pouziti'
    AND REFERENCED_TABLE_NAME = 'uzivatele_hodnoty'
    AND REFERENCED_COLUMN_NAME = 'id_uzivatele'
SQL,
            ),
            'Podle cizího klíče převádí UzivatelSlucovani záznamy na sloučeného uživatele a smazání účtu je smaže',
        );
    }

    public function testKazdyVzhledMaSablonu(): void
    {
        foreach (VzhledAdminuEnum::cases() as $vzhled) {
            self::assertFileExists(__DIR__ . '/../../admin/' . $vzhled->getSablona());
        }
    }
}
