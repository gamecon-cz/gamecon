<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use PHPUnit\Framework\TestCase;

/**
 * Pod `/symfony/` odpovídá jen front controller. Soubory ze stromu Symfony (konfigurace, zdroje, logy) se nikdy
 * nepodávají přímo a neprovádějí se ani jako skript s cestou za názvem, protože `symfony/` leží uvnitř adresáře
 * webu. Testuje se proti Apache jako na ostré ({@see ApacheJakoNaOstre}).
 */
class SymfonyPodAdresaremTest extends TestCase
{
    private static ApacheJakoNaOstre $apache;

    public static function setUpBeforeClass(): void
    {
        self::$apache = ApacheJakoNaOstre::spust();
    }

    public static function tearDownAfterClass(): void
    {
        self::$apache->zastav();
    }

    /**
     * @test
     */
    public function adresaBezSouboruVedeNaFrontController(): void
    {
        $odpoved = self::$apache->pozadavek(ApacheJakoNaOstre::HOST_WEBU, '/symfony/api/neco');

        self::assertSame(200, $odpoved['status']);
        self::assertSame(ApacheJakoNaOstre::TELO_FRONT_CONTROLLERU_SYMFONY, $odpoved['telo']);
    }

    /**
     * @test
     *
     * @dataProvider provideSouboryVeStromuSymfony
     */
    public function souborZeStromuSymfonyOdpovidaFrontController(string $cesta): void
    {
        $odpoved = self::$apache->pozadavek(ApacheJakoNaOstre::HOST_WEBU, $cesta);

        self::assertSame(200, $odpoved['status'], $cesta);
        self::assertSame(ApacheJakoNaOstre::TELO_FRONT_CONTROLLERU_SYMFONY, $odpoved['telo'], $cesta);
    }

    public static function provideSouboryVeStromuSymfony(): iterable
    {
        yield 'konfigurace' => ['/symfony/config/services.yaml'];
        yield 'front controller přímo' => ['/symfony/public/index.php'];
        yield 'složka' => ['/symfony/config/'];
        yield 'adresa s query stringem' => ['/symfony/api/neco?pole=hodnota'];
        yield 'skript přímo' => ['/symfony/config/bundles.php'];
        yield 'skript s cestou za názvem' => ['/symfony/config/bundles.php/neco'];
        yield 'soubor s cestou za názvem' => ['/symfony/config/services.yaml/neco'];
    }

    /**
     * @test
     */
    public function webPodSymfonyNepadne(): void
    {
        $odpoved = self::$apache->pozadavek(ApacheJakoNaOstre::HOST_WEBU, '/program');

        self::assertSame(200, $odpoved['status']);
        self::assertSame(ApacheJakoNaOstre::TELO_WEBU, $odpoved['telo']);
    }
}
