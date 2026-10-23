<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model;

use Gamecon\Tests\Model\Web\TestovaciObrazky;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ObrazekTest extends TestCase
{
    use TestovaciObrazky;

    private string $zdroje;

    protected function setUp(): void
    {
        $this->zdroje = sys_get_temp_dir() . '/gamecon-obrazek-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->zdroje);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->zdroje);
    }

    /**
     * @test
     */
    public function podporovanyObrazekPoznaZHlavickyBezDekodovani(): void
    {
        // přihláška se ptá na každý obrázek galerie při každém zobrazení, celé dekódování 48 Mpx by zabralo 190 MB
        $obri = $this->zdroje . '/obri.jpg';
        file_put_contents($obri, self::jpgSRozmeryVHlavicce(8000, 6000));
        memory_reset_peak_usage();
        $pametPred = memory_get_peak_usage();

        self::assertTrue(\Obrazek::jeToPodporovanyObrazek($obri));

        self::assertLessThan(16 * 1024 * 1024, memory_get_peak_usage() - $pametPred, 'Obrázek se celý dekódoval');
    }

    /**
     * @test
     */
    public function poskozenySouborSPlatnouHlavickouProjde(): void
    {
        $poskozeny = $this->zdroje . '/poskozeny.jpg';
        file_put_contents($poskozeny, substr(self::jpgSOrientaci(1), 0, 300));

        self::assertTrue(\Obrazek::jeToPodporovanyObrazek($poskozeny));
    }

    /**
     * @test
     */
    public function podporovanyJsouJenJpgPngAGif(): void
    {
        $jpg = $this->zdroje . '/a.jpg';
        file_put_contents($jpg, self::jpgSOrientaci(1));
        $png = $this->zdroje . '/a.png';
        imagepng(imagecreatetruecolor(4, 4), $png);
        $gif = $this->zdroje . '/a.gif';
        imagegif(imagecreatetruecolor(4, 4), $gif);
        $webp = $this->zdroje . '/a.webp';
        file_put_contents($webp, base64_decode('UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==', true));
        $text = $this->zdroje . '/a.jpg.txt';
        file_put_contents($text, 'není obrázek');

        self::assertTrue(\Obrazek::jeToPodporovanyObrazek($jpg));
        self::assertTrue(\Obrazek::jeToPodporovanyObrazek($png));
        self::assertTrue(\Obrazek::jeToPodporovanyObrazek($gif));
        self::assertFalse(\Obrazek::jeToPodporovanyObrazek($webp));
        self::assertFalse(\Obrazek::jeToPodporovanyObrazek($text));
        self::assertFalse(\Obrazek::jeToPodporovanyObrazek($this->zdroje . '/neexistuje.jpg'));
    }
}
