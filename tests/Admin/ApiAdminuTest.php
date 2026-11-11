<?php

declare(strict_types=1);

namespace Gamecon\Tests\Admin;

use Gamecon\Admin\ApiAdminu;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Výjimky z přihlášení do admin API musí odpovídat skutečným endpointům a mít zdůvodnění. Nepřihlášený dostane od
 * ostatních endpointů 401 a účastník bez práv 403, přes administraci to ověřuje @see \Gamecon\Tests\web\PristupDoApiAdminuTest.
 */
class ApiAdminuTest extends TestCase
{
    #[DataProvider('vyjimky')]
    public function testVyjimkaJeExistujiciEndpointSeZduvodnenim(string $endpoint, string $duvod): void
    {
        self::assertFileExists(__DIR__ . '/../../admin/scripts/api/' . $endpoint . '.php');
        self::assertNotSame('', trim($duvod), "Výjimka '{$endpoint}' nemá zdůvodnění");
        self::assertTrue(ApiAdminu::isBezPrihlaseni($endpoint));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function vyjimky(): \Generator
    {
        foreach (ApiAdminu::BEZ_PRIHLASENI as $endpoint => $duvod) {
            yield $endpoint => [$endpoint, $duvod];
        }
    }

    public function testOstatniEndpointyVyzadujiPrihlaseni(): void
    {
        self::assertFalse(ApiAdminu::isBezPrihlaseni('aktivitaTym'));
        self::assertFalse(ApiAdminu::isBezPrihlaseni('neexistujici'));
        self::assertFalse(ApiAdminu::isBezPrihlaseni(''));
    }

    public function testNeprihlasenyDostane401(): void
    {
        self::assertSame(401, ApiAdminu::getKodOdmitnuti('aktivitaTym', false, false));
    }

    public function testPrihlasenyBezPristupuDoAdminuDostane403(): void
    {
        self::assertSame(403, ApiAdminu::getKodOdmitnuti('aktivitaTym', true, false));
    }

    public function testPrihlasenySPristupemDoAdminuProjde(): void
    {
        self::assertNull(ApiAdminu::getKodOdmitnuti('aktivitaTym', true, true));
    }

    public function testVyjimkaProjdeBezPrihlaseniIBezPristupu(): void
    {
        self::assertNull(ApiAdminu::getKodOdmitnuti('programManifest', false, false));
        self::assertNull(ApiAdminu::getKodOdmitnuti('programManifest', true, false));
    }
}
