<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Shop\KontextZobrazeni;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KontextZobrazeniTest extends TestCase
{
    #[DataProvider('provideAdresyPozadavku')]
    public function testKontextPodleAdresyPozadavku(
        string           $requestUrl,
        string           $urlWebu,
        string           $urlAdmin,
        KontextZobrazeni $ocekavanyKontext,
    ): void {
        self::assertSame($ocekavanyKontext, KontextZobrazeni::vytvorZUrl($requestUrl, $urlWebu, $urlAdmin));
    }

    /**
     * @return array<string, array{string, string, string, KontextZobrazeni}>
     */
    public static function provideAdresyPozadavku(): array
    {
        $ostraWeb    = 'https://gamecon.cz';
        $ostraAdmin  = 'https://admin.gamecon.cz';
        $archivWeb   = 'https://2026.gamecon.cz';
        $archivAdmin = 'https://2026.gamecon.cz/admin';

        return [
            'web ostré'                         => ['https://gamecon.cz/program', $ostraWeb, $ostraAdmin, KontextZobrazeni::WEB],
            'admin ostré'                       => ['https://admin.gamecon.cz/uzivatel', $ostraWeb, $ostraAdmin, KontextZobrazeni::ADMIN],
            'web archivu'                       => ['https://2026.gamecon.cz/program', $archivWeb, $archivAdmin, KontextZobrazeni::WEB],
            'admin archivu pod cestou webu'     => ['https://2026.gamecon.cz/admin/uzivatel', $archivWeb, $archivAdmin, KontextZobrazeni::ADMIN],
            'kořen adminu archivu'              => ['https://2026.gamecon.cz/admin', $archivWeb, $archivAdmin, KontextZobrazeni::ADMIN],
            'kořen adminu archivu s dotazem'    => ['https://2026.gamecon.cz/admin?x=1', $archivWeb, $archivAdmin, KontextZobrazeni::ADMIN],
            'stránka webu začínající na admin'  => ['https://2026.gamecon.cz/administrace', $archivWeb, $archivAdmin, KontextZobrazeni::WEB],
            'adresa nastavená s lomítkem'       => ['https://2026.gamecon.cz/admin/uzivatel', $archivWeb . '/', $archivAdmin . '/', KontextZobrazeni::ADMIN],
        ];
    }

    public function testCiziDomenaZacinajiciAdresouWebuNeniWeb(): void
    {
        $this->expectException(\LogicException::class);

        KontextZobrazeni::vytvorZUrl('https://gamecon.cz.example.com/', 'https://gamecon.cz', 'https://admin.gamecon.cz');
    }
}
