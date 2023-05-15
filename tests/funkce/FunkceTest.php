<?php

declare(strict_types=1);

namespace Gamecon\Tests\funkce;

use PHPUnit\Framework\TestCase;

class FunkceTest extends TestCase
{
    /**
     * @test
     */
    public function Muzu_nahradit_placeholder_za_konstantu()
    {
        $bezPlaceholderu = 'Jsem bez placeholderu';
        self::assertSame($bezPlaceholderu, nahradPlaceholderyZaNastaveni($bezPlaceholderu));

        $sNeznamouKnstantou = 'Jsem s neznámou %konstantou z jiného světa%';
        self::assertSame($sNeznamouKnstantou, nahradPlaceholderyZaNastaveni($sNeznamouKnstantou));

        $nahodnaKonstanta = uniqid(__FUNCTION__, true);
        $sKonstantou      = "Jsem s konstantou %$nahodnaKonstanta%";

        self::assertFalse(defined($nahodnaKonstanta));
        self::assertSame($sKonstantou, nahradPlaceholderyZaNastaveni($sKonstantou));
    }

    /**
     * @test
     */
    public function Nemuzu_vylakat_citlivou_konstantu()
    {
        self::assertTrue(defined('DB_PASS'), 'Konstanta DB_PASS není definována');
        $sCitlovuKonstantou = 'Jsem s konstantou %DB_PASS%';
        self::assertSame($sCitlovuKonstantou, nahradPlaceholderyZaNastaveni($sCitlovuKonstantou));
    }

    /**
     * @test
     * @dataProvider provideVicerozmernePole
     * @param $data
     * @param array $ocekavanyVysledek
     */
    public function Muzu_ziskat_jednorozmerne_pole_z_vicerozmerneho($data, array $ocekavanyVysledek)
    {
        self::assertSame($ocekavanyVysledek, flatten($data));
    }

    public static function provideVicerozmernePole(): array
    {
        $jenorozmernePole = ['něco', 1, null];
        return [
            'prázdné pole'                       => [[], []],
            'prázdný ArrayIterator object'       => [new \ArrayIterator(), []],
            'jednorozměrné pole'                 => [$jenorozmernePole, $jenorozmernePole],
            'jednorozměrný ArrayIterator object' => [new \ArrayIterator($jenorozmernePole), $jenorozmernePole],
            'vícerozměrné pole'                  => [
                ['něco', 1, null, ['dále' => [1, $datum = new \DateTime()], 'ještě dále' => [false]]],
                ['něco', 1, null, 1, $datum, false],
            ],
        ];
    }

    /**
     * @test
     */
    public function Ve_vychozim_nastaveni_dostanu_existujici_sql_pripojeni()
    {
        $nejakeSpojeni = dbConnect();
        self::assertInstanceOf(\mysqli::class, $nejakeSpojeni);
        $dalsiSpojeni = dbConnect();
        self::assertSame($nejakeSpojeni, $dalsiSpojeni);
    }

    /**
     * @test
     */
    public function Muzu_vyzadat_nove_sql_pripojeni()
    {
        $nejakeSpojeni = dbConnect();
        self::assertInstanceOf(\mysqli::class, $nejakeSpojeni);
        $dalsiSpojeni = dbConnect(true, true);
        self::assertInstanceOf(\mysqli::class, $dalsiSpojeni);
        self::assertNotSame($nejakeSpojeni, $dalsiSpojeni);
    }
}
