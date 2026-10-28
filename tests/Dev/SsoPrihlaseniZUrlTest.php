<?php

declare(strict_types=1);

namespace Gamecon\Tests\Dev;

use Gamecon\Dev\SsoPrihlaseniZUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SsoPrihlaseniZUrlTest extends TestCase
{
    private const string KLIC_ROCNIKU = 'klic-rocniku';
    private const string MASTER = 'master';
    private const string NOUZOVA_URL = 'https://gamecon.example';

    #[DataProvider('provideOverovaciKlic')]
    public function testGetOverovaciKlic(
        string $klicRocniku,
        string $master,
        bool $jsmeNaPreview,
        string $ocekavanyKlic,
    ): void {
        self::assertSame(
            $ocekavanyKlic,
            SsoPrihlaseniZUrl::getOverovaciKlic($klicRocniku, $master, $jsmeNaPreview),
        );
    }

    public static function provideOverovaciKlic(): iterable
    {
        yield 'archiv ověřuje klíčem svého ročníku' => [self::KLIC_ROCNIKU, '', false, self::KLIC_ROCNIKU];
        yield 'klíč ročníku má přednost před masterem' => [self::KLIC_ROCNIKU, self::MASTER, true, self::KLIC_ROCNIKU];
        yield 'preview ověřuje masterem' => ['', self::MASTER, true, self::MASTER];
        yield 'ostrá a beta master k ověření nepoužijí' => ['', self::MASTER, false, ''];
        yield 'preview bez masteru neověří nic' => ['', '', true, ''];
        yield 'lokál bez klíčů neověří nic' => ['', '', false, ''];
    }

    #[DataProvider('provideTokenZUrl')]
    public function testIsTokenZUrl(mixed $hodnota, bool $ocekavano): void
    {
        self::assertSame($ocekavano, SsoPrihlaseniZUrl::isTokenZUrl($hodnota));
    }

    public static function provideTokenZUrl(): iterable
    {
        yield 'chybějící parametr' => [null, false];
        yield 'prázdný parametr' => ['', false];
        yield 'pole z ?gcsso[]=x' => [['x'], false];
        yield 'text' => ['eyJ0.podpis', true];
    }

    #[DataProvider('provideCil')]
    public function testGetBezpecnyCil(string $cil, string $ocekavano): void
    {
        self::assertSame($ocekavano, SsoPrihlaseniZUrl::getBezpecnyCil($cil, self::NOUZOVA_URL));
    }

    public static function provideCil(): iterable
    {
        yield 'cesta' => ['/web/program', '/web/program'];
        yield 'cesta s ostatními parametry' => ['/?gate=g&a=1', '/?gate=g&a=1'];
        yield 'kořen' => ['/', '/'];
        yield 'prázdný cíl z požadavku na ///host' => ['', self::NOUZOVA_URL];
        yield 'cesta bez počátečního lomítka' => ['*', self::NOUZOVA_URL];
        yield 'URL jiného hostu bez schématu' => ['//jiny-host.example/', self::NOUZOVA_URL];
        yield 'zpětné lomítko, které prohlížeč bere jako lomítko' => ['/\\jiny-host.example/', self::NOUZOVA_URL];
        yield 'absolutní URL' => ['https://jiny-host.example/', self::NOUZOVA_URL];
    }
}
