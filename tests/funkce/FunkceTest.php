<?php declare(strict_types=1);

namespace Gamecon\Tests\funkce;

use PHPUnit\Framework\TestCase;

class FunkceTest extends TestCase
{
    /**
     * @test
     */
    public function Muzu_nahradit_placeholder_za_konstantu() {
        $bezPlaceholderu = 'Jsem bez placeholderu';
        self::assertSame($bezPlaceholderu, nahradPlaceholderZaKonstantu($bezPlaceholderu));

        $sNeznamouKnstantou = 'Jsem s neznámou %konstantou z jiného světa%';
        self::assertSame($sNeznamouKnstantou, nahradPlaceholderZaKonstantu($sNeznamouKnstantou));

        $nahodnaKonstanta = uniqid(__FUNCTION__, true);
        $sKonstantou = "Jsem s konstantou %$nahodnaKonstanta%";

        self::assertFalse(defined($nahodnaKonstanta));
        self::assertSame($sKonstantou, nahradPlaceholderZaKonstantu($sKonstantou));

        define($nahodnaKonstanta, 'To je ale náhodička!');
        self::assertSame('Jsem s konstantou To je ale náhodička!', nahradPlaceholderZaKonstantu($sKonstantou));
    }

    /**
     * @test
     * @dataProvider provideVicerozmernePole
     * @param $data
     * @param array $ocekavanyVysledek
     */
    public function Muzu_ziskat_jednorozmerne_pole_z_vicerozmerneho($data, array $ocekavanyVysledek) {
        self::assertSame($ocekavanyVysledek, flatten($data));
    }

    public function provideVicerozmernePole(): array {
        $jenorozmernePole = ['něco', 1, null];
        return [
            'prázdné pole'                 => [[], []],
            'prázdný ArrayIterator object' => [new \ArrayIterator(), []],
            'jednorozměrné pole'           => [$jenorozmernePole, $jenorozmernePole],
            'jednorozměrný ArrayIterator object' => [new \ArrayIterator($jenorozmernePole), $jenorozmernePole],
            'vícerozměrné pole' => [
                ['něco', 1, null, ['dále' => [1, $datum = new \DateTime()], 'ještě dále' => [false]]],
                ['něco', 1, null, 1, $datum, false],
            ],
        ];
    }
}
