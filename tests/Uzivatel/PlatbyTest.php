<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Finance\FioPlatba;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Platby;

class PlatbyTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function Nastaveni_prahu_podezrele_vysoke_platby_existuje_a_ma_spravnou_hodnotu()
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $prah = $systemoveNastaveni->dejHodnotu(
            SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA
        );

        self::assertNotNull($prah, 'Nastavení prahu podezřele vysoké platby není dostupné');
        self::assertIsNumeric($prah, 'Prah není číselná hodnota');
        self::assertEquals(10000, (int)$prah, 'Výchozí hodnota prahu není 10000 CZK');
    }

    /**
     * @test
     */
    public function Konstanta_pro_podezrele_vysokou_platbu_existuje()
    {
        self::assertTrue(
            defined('Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA'),
            'Konstanta PODEZRELE_VYSOKA_PLATBA_UCASTNIKA neexistuje'
        );

        self::assertEquals(
            'PODEZRELE_VYSOKA_PLATBA_UCASTNIKA',
            SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA,
            'Konstanta má nesprávnou hodnotu'
        );
    }

    /**
     * @test
     */
    public function Nastaveni_je_v_databazi_s_spravnymi_parametry()
    {
        $nastaveni = dbFetchAll(
            "SELECT * FROM systemove_nastaveni WHERE klic = 'PODEZRELE_VYSOKA_PLATBA_UCASTNIKA'"
        );

        self::assertCount(1, $nastaveni, 'Nastavení není v databázi nebo je duplicitní');

        $nastaveni = $nastaveni[0];
        self::assertEquals('PODEZRELE_VYSOKA_PLATBA_UCASTNIKA', $nastaveni['klic']);
        self::assertEquals('10000', $nastaveni['hodnota']);
        self::assertEquals('integer', $nastaveni['datovy_typ']);
        self::assertEquals('Finance', $nastaveni['skupina']);
        self::assertEquals('Podezřele vysoká platba účastníka', $nastaveni['nazev']);
        self::assertEquals(0, $nastaveni['pouze_pro_cteni']);
        self::assertEquals(-1, $nastaveni['rocnik_nastaveni']);
    }

    /**
     * @test
     */
    public function Metoda_odesliUpozorneniOPodezreleVysokePlatbe_muze_byt_zavolana_bez_chyby()
    {
        // This test verifies that the email alert method exists and can be called
        // We can't easily test actual email sending in unit tests because MAILY_DO_SOUBORU is /dev/null
        // But we can verify the method completes without exceptions

        $systemoveNastaveni = SystemoveNastaveni::zGlobals();

        // Create test user
        $userId = $this->vytvorTestovacihoUzivatele();

        // Create CFO user to receive the email
        $cfoUserId = $this->vytvorCfoUzivatele();

        // Verify CFO user exists and has email
        $cfoEmails = \Uzivatel::cfosEmaily();
        self::assertNotEmpty($cfoEmails, 'CFO uživatel musí mít email adresu');

        // Create mock FioPlatba with high amount
        $fioPlatbaData = [
            'ID pohybu' => '12345678901234567',
            'Datum' => date('Y-m-d') . '+0200',
            'Objem' => 10000.00,
            'VS' => (string)$userId,
            'Zpráva pro příjemce' => 'Test vysoká platba',
            'Název protiúčtu' => 'Test Plátce',
        ];
        $fioPlatba = FioPlatba::zeZaznamu($fioPlatbaData);

        $uzivatel = \Uzivatel::zId($userId);

        // Call the private method via reflection
        $platby = new Platby($systemoveNastaveni);
        $reflection = new \ReflectionClass($platby);
        $method = $reflection->getMethod('odesliUpozorneniOPodezreleVysokePlatbe');
        $method->setAccessible(true);

        // This should complete without throwing an exception
        try {
            $method->invoke($platby, $fioPlatba, $uzivatel);
            $methodExecutedSuccessfully = true;
        } catch (\Throwable $e) {
            $methodExecutedSuccessfully = false;
            $exceptionMessage = $e->getMessage();
        }

        self::assertTrue(
            $methodExecutedSuccessfully ?? false,
            'Metoda odesliUpozorneniOPodezreleVysokePlatbe selhala: ' . ($exceptionMessage ?? 'unknown error')
        );
    }

    private function vytvorTestovacihoUzivatele(): int
    {
        \dbInsert('uzivatele_hodnoty', [
            'login_uzivatele' => 'test_user_' . time() . '_' . random_int(1000, 9999),
            'jmeno_uzivatele' => 'Test',
            'prijmeni_uzivatele' => 'User',
            'email1_uzivatele' => 'test' . time() . '@example.com',
        ]);

        return (int)\dbInsertId();
    }

    private function vytvorCfoUzivatele(): int
    {
        \dbInsert('uzivatele_hodnoty', [
            'login_uzivatele' => 'cfo_user_' . time() . '_' . random_int(1000, 9999),
            'jmeno_uzivatele' => 'CFO',
            'prijmeni_uzivatele' => 'Test',
            'email1_uzivatele' => 'cfo' . time() . '@example.com',
        ]);
        $cfoUserId = (int)\dbInsertId();

        // Assign CFO role
        \dbInsert('uzivatele_role', [
            'id_uzivatele' => $cfoUserId,
            'id_role' => Role::CFO,
            'posazen' => date('Y-m-d H:i:s'),
        ]);

        return $cfoUserId;
    }
}
