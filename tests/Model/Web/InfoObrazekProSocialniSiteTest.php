<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\ImagickAvif;
use Gamecon\Web\Info;
use Symfony\Component\Filesystem\Filesystem;

class InfoObrazekProSocialniSiteTest extends AbstractTestDb
{
    use TestovaciDokumenty;

    private const string ADRESAR = 'soubory/obsah/test-info';

    protected function setUp(): void
    {
        parent::setUp();
        (new Filesystem())->mkdir(WWW . '/' . self::ADRESAR);
        file_put_contents(WWW . '/' . self::ADRESAR . '/foto.avif', self::avifBajty());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(WWW . '/' . self::ADRESAR);
        ImagickAvif::setZkouskaProTesty(null);
        parent::tearDown();
    }

    /**
     * Náhled odkazu na sociální síti čte jejich robot a AVIF nezobrazí, proto se do `og:image` dá JPEG.
     *
     * @test
     */
    public function obrazekVAvifSeProSocialniSiteNahradiJpeg(): void
    {
        $og = $this->ogImage(self::ADRESAR . '/foto.avif?v=123');

        self::assertMatchesRegularExpression('~^https?://.+/cache/public/img/[0-9a-f]{32}\.jpg\?m=\d+$~', $og);
        $soubor = CACHE . '/img/' . basename((string) strtok($og, '?'));
        self::assertFileExists($soubor);
        // stejný obrázek, jen v JPEG: bez ořezu a zvětšení, ze zdroje 16 × 12 px
        self::assertSame(['image/jpeg', 16, 12], [getimagesize($soubor)['mime'], getimagesize($soubor)[0], getimagesize($soubor)[1]]);
    }

    /**
     * Robot sociální sítě větší obrázek nenačte, proto má JPEG nejvýš 1200 px na delší straně a poměr stran zůstane.
     *
     * @test
     */
    public function velkyObrazekVAvifMaVOgImageJpegNejvyse1200PxNaDelsiStrane(): void
    {
        $velky = new \Imagick();
        $velky->newImage(2400, 1200, new \ImagickPixel('rgb(0,0,255)'));
        ImagickAvif::nastavZapis($velky, 70);
        file_put_contents(WWW . '/' . self::ADRESAR . '/velky.avif', $velky->getImageBlob());

        $og = $this->ogImage(self::ADRESAR . '/velky.avif');

        $soubor = CACHE . '/img/' . basename((string) strtok($og, '?'));
        self::assertSame(['image/jpeg', 1200, 600], [getimagesize($soubor)['mime'], getimagesize($soubor)[0], getimagesize($soubor)[1]]);
    }

    /**
     * @test
     */
    public function obrazekVAvifZAbsolutniAdresyNaseHoWebuSeTakeNahradi(): void
    {
        $og = $this->ogImage(URL_WEBU . '/' . self::ADRESAR . '/foto.avif');

        self::assertStringEndsWith('.jpg', (string) strtok($og, '?'));
        self::assertStringContainsString('/cache/public/img/', $og);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideObrazkyKtereSeNemeni(): iterable
    {
        yield 'JPEG' => ['soubory/obsah/test-info/foto.jpg'];
        yield 'AVIF, který neexistuje' => ['soubory/obsah/test-info/chybi.avif'];
        yield 'AVIF z cizího webu' => ['https://example.com/foto.avif'];
        yield 'cesta ven z webu' => ['soubory/obsah/test-info/../../../nastaveni/foto.avif'];
    }

    /**
     * @test
     *
     * @dataProvider provideObrazkyKtereSeNemeni
     */
    public function jinyObrazekNezAvifZNasehoWebuZustaneJakJe(string $obrazek): void
    {
        $ocekavany = str_starts_with($obrazek, 'http')
            ? $obrazek
            : URL_WEBU . '/' . $obrazek;

        self::assertSame($ocekavany, $this->ogImage($obrazek));
    }

    private function ogImage(string $obrazek): string
    {
        $info = new Info(SystemoveNastaveni::zGlobals());
        $html = $info->obrazek($obrazek)->html();
        self::assertSame(1, preg_match('~<meta property="og:image" content="([^"]+)">~', $html, $shody), $html);

        return $shody[1];
    }
}
