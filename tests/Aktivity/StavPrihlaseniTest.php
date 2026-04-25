<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\StavPrihlaseni;
use PHPUnit\Framework\TestCase;

class StavPrihlaseniTest extends TestCase
{
    /**
     * @test
     *
     * @dataProvider provideFrontendKod
     */
    public function frontendKodVraciSpravnyKodProZnamyStav(int $stav, string $ocekavanyKod): void
    {
        self::assertSame($ocekavanyKod, StavPrihlaseni::frontendKod($stav));
    }

    public static function provideFrontendKod(): array
    {
        return [
            'prihlasen'             => [StavPrihlaseni::PRIHLASEN, 'prihlasen'],
            'prihlasenADorazil'     => [StavPrihlaseni::PRIHLASEN_A_DORAZIL, 'prihlasenADorazil'],
            'dorazilJakoNahradnik'  => [StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK, 'dorazilJakoNahradnik'],
            'prihlasenAleNedorazil' => [StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL, 'prihlasenAleNedorazil'],
            'pozdeZrusil'           => [StavPrihlaseni::POZDE_ZRUSIL, 'pozdeZrusil'],
            'sledujici'             => [StavPrihlaseni::SLEDUJICI, 'sledujici'],
        ];
    }

    /**
     * @test
     */
    public function frontendKodVraciNullProNepřihlášenStav(): void
    {
        self::assertNull(StavPrihlaseni::frontendKod(StavPrihlaseni::NEPRIHLASEN));
    }

    /**
     * @test
     */
    public function frontendKodVraciNullProNeznámyStav(): void
    {
        self::assertNull(StavPrihlaseni::frontendKod(9999));
    }
}
