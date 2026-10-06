<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\QrOdkaz;
use PHPUnit\Framework\TestCase;

class QrOdkazTest extends TestCase
{
    /**
     * @test
     */
    public function pngJeValidniObrazekVTiskoveVelikosti(): void
    {
        $qrOdkaz = new QrOdkaz('https://gamecon.cz/blog/nejaky-clanek', 'qr-blog-nejaky-clanek');

        $dataUri = $qrOdkaz->pngDataUri();
        self::assertStringStartsWith('data:image/png;base64,', $dataUri);

        $info = getimagesizefromstring(base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true));
        self::assertNotFalse($info);
        self::assertSame(IMAGETYPE_PNG, $info[2]);
        self::assertGreaterThanOrEqual(1000, $info[0]);
    }

    /**
     * @test
     */
    public function svgJeValidniSvg(): void
    {
        $dataUri = (new QrOdkaz('https://gamecon.cz/blog/nejaky-clanek', 'qr'))->svgDataUri();

        self::assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
        self::assertStringContainsString(
            '<svg',
            base64_decode(substr($dataUri, strlen('data:image/svg+xml;base64,')), true),
        );
    }

    /**
     * @test
     */
    public function htmlObsahujeEscapovanyOdkazANazvySouboruKeStazeni(): void
    {
        $html = (new QrOdkaz('https://gamecon.cz/blog/a"b', 'qr-blog-ab'))->html('Poznámka');

        self::assertStringContainsString('href="https://gamecon.cz/blog/a&quot;b"', $html);
        self::assertStringContainsString('download="qr-blog-ab.png"', $html);
        self::assertStringContainsString('download="qr-blog-ab.svg"', $html);
        self::assertStringContainsString('Poznámka', $html);
    }

    /**
     * @test
     */
    public function blogOdkazujeNaSvujClanekNovinkaNaVypisNovinek(): void
    {
        self::assertSame(URL_WEBU . '/blog/muj-clanek', $this->novinka(\Novinka::BLOG, 'muj-clanek')->urlNaWebu());
        self::assertSame(URL_WEBU . '/novinky', $this->novinka(\Novinka::NOVINKA, 'nejaka-novinka')->urlNaWebu());
    }

    private function novinka(int $typ, string $url): \Novinka
    {
        $novinka = (new \ReflectionClass(\Novinka::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty(\Novinka::class, 'r'))->setValue($novinka, [
            'typ' => (string) $typ,
            'url' => $url,
        ]);

        return $novinka;
    }
}
