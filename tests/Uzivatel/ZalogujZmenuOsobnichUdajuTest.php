<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;

/**
 * Audit změn osobních údajů uživatele do tabulky uzivatele_hodnoty_log
 * (Uzivatel::zalogujZmenuOsobnichUdaju / zalogujZmenuOp).
 */
class ZalogujZmenuOsobnichUdajuTest extends AbstractTestDb
{
    // vytvorUzivatele() vkládá do uzivatele_hodnoty jen pár sloupců; ostatní NOT NULL
    // sloupce (ulice_a_cp_uzivatele, …) nemají default → bez tohoto by STRICT_TRANS_TABLES
    // shodil INSERT na "Field '…' doesn't have a default value".
    protected static bool $disableStrictTransTables = true;

    private function vytvorUzivatele(string $suffix): \Uzivatel
    {
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'AuditLog'
SQL,
            [
                0 => 'test_audit_' . $suffix,
                1 => 'test.audit.' . $suffix . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function logProUzivatele(int $idUzivatele): array
    {
        return dbFetchAll(
            'SELECT sloupec, stara_hodnota, nova_hodnota, id_zmenil, zdroj_zmeny
             FROM uzivatele_hodnoty_log WHERE id_uzivatele = $0 ORDER BY id_log',
            [$idUzivatele],
        );
    }

    public function testZalogujeZmenuSloupceSeStarouANovouHodnotou(): void
    {
        $uzivatel = $this->vytvorUzivatele('zmena');

        \Uzivatel::zalogujZmenuOsobnichUdaju(
            $uzivatel->id(),
            [
                Sql::JMENO_UZIVATELE => 'Nové jméno',
            ],
            [
                Sql::JMENO_UZIVATELE => 'Test',
            ],
            123,
            'test',
        );

        $log = $this->logProUzivatele($uzivatel->id());
        self::assertCount(1, $log, 'Změna jednoho sloupce = jeden řádek logu');
        self::assertSame(Sql::JMENO_UZIVATELE, $log[0]['sloupec']);
        self::assertSame('Test', $log[0]['stara_hodnota']);
        self::assertSame('Nové jméno', $log[0]['nova_hodnota']);
        self::assertSame(123, (int) $log[0]['id_zmenil']);
        self::assertSame('test', $log[0]['zdroj_zmeny']);
    }

    public function testNezalogujeKdyzSeHodnotaNezmenila(): void
    {
        $uzivatel = $this->vytvorUzivatele('bez_zmeny');

        \Uzivatel::zalogujZmenuOsobnichUdaju(
            $uzivatel->id(),
            [
                Sql::JMENO_UZIVATELE => 'Test',
            ],
            [
                Sql::JMENO_UZIVATELE => 'Test',
            ],
            123,
            'test',
        );

        self::assertCount(0, $this->logProUzivatele($uzivatel->id()));
    }

    public function testIgnorujeSloupceMimoWhitelist(): void
    {
        $uzivatel = $this->vytvorUzivatele('mimo_whitelist');

        \Uzivatel::zalogujZmenuOsobnichUdaju(
            $uzivatel->id(),
            [
                Sql::ZUSTATEK        => '999',
                Sql::JMENO_UZIVATELE => 'Jiné',
            ],
            [
                Sql::ZUSTATEK        => '0',
                Sql::JMENO_UZIVATELE => 'Test',
            ],
            123,
            'test',
        );

        $log = $this->logProUzivatele($uzivatel->id());
        self::assertCount(1, $log, 'Zůstatek se neauditovaně neloguje, jméno ano');
        self::assertSame(Sql::JMENO_UZIVATELE, $log[0]['sloupec']);
    }

    public function testDoplniStareHodnotyZDbKdyzNejsouZadany(): void
    {
        $uzivatel = $this->vytvorUzivatele('stare_z_db');

        \Uzivatel::zalogujZmenuOsobnichUdaju(
            $uzivatel->id(),
            [
                Sql::JMENO_UZIVATELE => 'Přejmenováno',
            ],
            null, // stará hodnota se má načíst z DB
            123,
            'test',
        );

        $log = $this->logProUzivatele($uzivatel->id());
        self::assertCount(1, $log);
        self::assertSame('Test', $log[0]['stara_hodnota'], 'Stará hodnota se doplnila z DB');
        self::assertSame('Přejmenováno', $log[0]['nova_hodnota']);
    }

    public function testZalogujeZmenuOpZasifrovane(): void
    {
        $uzivatel = $this->vytvorUzivatele('op');
        $staraZasifrovana = \Sifrovatko::zasifruj('OLD12345');

        \Uzivatel::zalogujZmenuOp(
            $uzivatel->id(),
            $staraZasifrovana,
            'NEW67890',
            123,
            'test',
        );

        $log = $this->logProUzivatele($uzivatel->id());
        self::assertCount(1, $log);
        self::assertSame(Sql::OP, $log[0]['sloupec']);
        // v logu nesmí být plaintext
        self::assertNotSame('NEW67890', $log[0]['nova_hodnota']);
        self::assertNotSame('OLD12345', $log[0]['stara_hodnota']);
        // po dešifrování ale musí sedět
        self::assertSame('OLD12345', \Sifrovatko::desifruj($log[0]['stara_hodnota']));
        self::assertSame('NEW67890', \Sifrovatko::desifruj($log[0]['nova_hodnota']));
    }

    public function testZalogujeZmenuOpNezalogujeStejnyPlaintext(): void
    {
        $uzivatel = $this->vytvorUzivatele('op_bez_zmeny');
        // dvakrát zašifrovaná stejná hodnota dá jiný ciphertext (nedeterministické šifrování)
        $staraZasifrovana = \Sifrovatko::zasifruj('SAME111');

        \Uzivatel::zalogujZmenuOp(
            $uzivatel->id(),
            $staraZasifrovana,
            'SAME111',
            123,
            'test',
        );

        self::assertCount(
            0,
            $this->logProUzivatele($uzivatel->id()),
            'Stejný plaintext OP se nesmí logovat i přes odlišný ciphertext',
        );
    }
}
