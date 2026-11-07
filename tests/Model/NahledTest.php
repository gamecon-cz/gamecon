<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model;

use Gamecon\Tests\Model\Web\TestovaciObrazky;
use Gamecon\Web\ImagickAvif;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class NahledTest extends TestCase
{
    use TestovaciObrazky;

    private string $zdroje;

    /**
     * @var list<string>
     */
    private array $nahledyKUklidu = [];

    protected function setUp(): void
    {
        $this->zdroje = sys_get_temp_dir() . '/gamecon-nahled-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->zdroje);
    }

    protected function tearDown(): void
    {
        ImagickAvif::setZkouskaProTesty(null);
        (new Filesystem())->remove([$this->zdroje, ...$this->nahledyKUklidu]);
    }

    /**
     * @test
     */
    public function fotkaZTelefonuSeVNahleduOtociStejneJakoVProhlizeci(): void
    {
        // 40 × 20 px vodorovně, EXIF říká „otočit o 90° po směru hodin“
        $fotka = $this->zdroje . '/IMG_0001.jpg';
        file_put_contents($fotka, self::jpgSOrientaci(6));

        $nahled = $this->vytvorNahled(\Nahled::zeSouboru($fotka)->pokryjOrez(20, 40));

        // levá (červená) strana fotky je po otočení nahoře
        self::assertTrue(self::jeCervena($nahled, 10, 5));
        self::assertTrue(self::jeModra($nahled, 10, 35));
    }

    /**
     * @test
     */
    public function fotkaBezOrientaceSeNeotoci(): void
    {
        $fotka = $this->zdroje . '/IMG_0002.jpg';
        file_put_contents($fotka, self::jpgSOrientaci(1));

        $nahled = $this->vytvorNahled(\Nahled::zeSouboru($fotka)->pokryjOrez(40, 20));

        self::assertTrue(self::jeCervena($nahled, 5, 10));
        self::assertTrue(self::jeModra($nahled, 35, 10));
    }

    /**
     * @test
     */
    public function nahledJeVAvifAMaPozadovanyRozmerABarvy(): void
    {
        $fotka = $this->zdroje . '/fotka.jpg';
        file_put_contents($fotka, self::jpgBajty(400, 200));

        $url = \Nahled::zeSouboru($fotka)->pokryjOrez(100, 50)->url();
        $nahled = $this->vytvorNahled(\Nahled::zeSouboru($fotka)->pokryjOrez(100, 50));

        self::assertSame($url, (string) \Nahled::zeSouboru($fotka)->pokryjOrez(100, 50), 'Stejný náhled má stejnou adresu');
        self::assertMatchesRegularExpression('~/img/[0-9a-f]{32}\.avif\?m=\d+$~', $url);
        self::assertSame(['image/avif', 100, 50], [getimagesize($nahled)['mime'], getimagesize($nahled)[0], getimagesize($nahled)[1]]);
        self::assertTrue(self::jeCervena($nahled, 10, 25));
        self::assertTrue(self::jeModra($nahled, 90, 25));
    }

    /**
     * @test
     */
    public function nahledPngSPruhlednostiMaPruhlednostIVAvif(): void
    {
        $logo = $this->zdroje . '/logo.png';
        $obrazek = new \Imagick();
        $obrazek->newImage(40, 20, new \ImagickPixel('transparent'));
        $kresba = new \ImagickDraw();
        $kresba->setFillColor(new \ImagickPixel('rgb(0,200,0)'));
        $kresba->rectangle(20, 0, 39, 19);
        $obrazek->drawImage($kresba);
        $obrazek->setImageFormat('png');
        file_put_contents($logo, $obrazek->getImageBlob());

        $nahled = $this->vytvorNahled(\Nahled::zeSouboru($logo)->pokryjOrez(40, 20));

        self::assertSame('image/avif', getimagesize($nahled)['mime']);
        $vysledek = new \Imagick($nahled);
        self::assertSame(0.0, round((float) $vysledek->getImagePixelColor(5, 10)->getColorValue(\Imagick::COLOR_ALPHA), 1), 'Levá půlka je průhledná');
        self::assertSame(1.0, round((float) $vysledek->getImagePixelColor(35, 10)->getColorValue(\Imagick::COLOR_ALPHA), 1), 'Pravá půlka je krytá');
    }

    /**
     * Zdroj ve formátu AVIF se čte přes `ImagickAvif::pripravPoNacteni()`, jinak by ho ImageMagick 6 přečetl v YCbCr
     * a barvy by byly jiné.
     *
     * @test
     */
    public function nahledZAvifZdrojeMaSpravneBarvy(): void
    {
        $zdroj = new \Imagick();
        $zdroj->readImageBlob(self::jpgBajty(40, 20));
        ImagickAvif::nastavZapis($zdroj, 90);
        $fotka = $this->zdroje . '/fotka.avif';
        file_put_contents($fotka, $zdroj->getImageBlob());

        $nahled = $this->vytvorNahled(\Nahled::zeSouboru($fotka)->pokryjOrez(20, 10));

        self::assertSame(['image/avif', 20, 10], [getimagesize($nahled)['mime'], getimagesize($nahled)[0], getimagesize($nahled)[1]]);
        self::assertTrue(self::jeCervena($nahled, 3, 5));
        self::assertTrue(self::jeModra($nahled, 17, 5));
    }

    /**
     * @test
     */
    public function kdyzImagickAvifNezapiseNahledJeWebpJakoDrive(): void
    {
        ImagickAvif::setZkouskaProTesty([
            'neprusvitny' => false,
            'prusvitny'   => false,
        ]);
        $fotka = $this->zdroje . '/fotka.jpg';
        file_put_contents($fotka, self::jpgBajty(40, 20));

        $nahled = $this->vytvorNahled(\Nahled::zeSouboru($fotka)->pokryjOrez(20, 10));

        self::assertStringEndsWith('.webp', $nahled);
        self::assertSame('image/webp', getimagesize($nahled)['mime']);
        self::assertTrue(self::jeCervena($nahled, 3, 5));
        // adresa WebP náhledu je jako před AVIF, takže se na ostré nic nepřegeneruje
        self::assertSame(md5($fotka . \Nahled::POKRYJ_OREZ . 10 . 20 . 92 . 'v2_webp') . '.webp', basename($nahled));
    }

    /**
     * ImageMagick 6 na ostré zapíše AVIF s průhledností špatně a dvanáctkrát pomaleji než WebP, takže se tam náhledy dál
     * ukládají do WebP, i když by šel JPEG zdroj zapsat do AVIF.
     *
     * @test
     */
    public function kdyzImagickNezapiseAvifSPruhlednostiJsouVsechnyNahledyWebp(): void
    {
        ImagickAvif::setZkouskaProTesty([
            'neprusvitny' => true,
            'prusvitny'   => false,
        ]);
        $fotka = $this->zdroje . '/fotka.jpg';
        file_put_contents($fotka, self::jpgBajty(40, 20));
        $logo = $this->zdroje . '/logo.png';
        $obrazek = new \Imagick();
        $obrazek->newImage(40, 20, new \ImagickPixel('transparent'));
        $obrazek->setImageFormat('png');
        file_put_contents($logo, $obrazek->getImageBlob());

        $nahledFotky = $this->vytvorNahled(\Nahled::zeSouboru($fotka)->pokryjOrez(20, 10));
        $nahledLoga = $this->vytvorNahled(\Nahled::zeSouboru($logo)->pokryjOrez(20, 10));

        self::assertSame('image/webp', getimagesize($nahledFotky)['mime']);
        self::assertSame('image/webp', getimagesize($nahledLoga)['mime']);
    }

    /**
     * @test
     */
    public function omezZmensiDelsiStranuAZachovaPomerStranANikdyNezvetsuje(): void
    {
        $velka = $this->zdroje . '/velka.jpg';
        file_put_contents($velka, self::jpgBajty(400, 200));
        $mala = $this->zdroje . '/mala.jpg';
        file_put_contents($mala, self::jpgBajty(40, 20));

        $zmensena = $this->vytvorNahled(\Nahled::zeSouboru($velka)->omez(100));
        $beze = $this->vytvorNahled(\Nahled::zeSouboru($mala)->omez(100));

        self::assertSame([100, 50], [getimagesize($zmensena)[0], getimagesize($zmensena)[1]]);
        self::assertSame([40, 20], [getimagesize($beze)[0], getimagesize($beze)[1]], 'Menší obrázek se nezvětšil');
        self::assertTrue(self::jeCervena($zmensena, 10, 25));
        self::assertTrue(self::jeModra($zmensena, 90, 25));
    }

    /**
     * Soubor se zapisuje přes dočasný, protože kódování AVIF trvá vteřiny a návštěvník by dostal adresu prázdného souboru.
     *
     * @test
     */
    public function nahledNeZanechaDocasnySouborAMaCelyObsah(): void
    {
        $fotka = $this->zdroje . '/fotka.jpg';
        file_put_contents($fotka, self::jpgBajty(400, 200));

        $url = \Nahled::zeSouboru($fotka)->pokryjOrez(100, 50)->url();

        $hash = pathinfo((string) strtok($url, '?'), PATHINFO_FILENAME);
        $soubory = glob(CACHE . '/img/' . $hash . '*') ?: [];
        $this->nahledyKUklidu = [...$this->nahledyKUklidu, ...$soubory];
        self::assertSame([CACHE . '/img/' . $hash . '.avif'], $soubory);
        self::assertGreaterThan(0, filesize($soubory[0]));
        self::assertSame(100, getimagesize($soubory[0])[0]);
    }

    /**
     * @test
     */
    public function nahledJakoJpegMaJinouAdresuAPruhlednostMaBilou(): void
    {
        $logo = $this->zdroje . '/logo.png';
        $obrazek = new \Imagick();
        $obrazek->newImage(40, 20, new \ImagickPixel('transparent'));
        $kresba = new \ImagickDraw();
        $kresba->setFillColor(new \ImagickPixel('rgb(0,200,0)'));
        $kresba->rectangle(20, 0, 39, 19);
        $obrazek->drawImage($kresba);
        $obrazek->setImageFormat('png');
        file_put_contents($logo, $obrazek->getImageBlob());

        $avif = \Nahled::zeSouboru($logo)->pokryjOrez(40, 20)->url();
        $jpeg = \Nahled::zeSouboru($logo)->pokryjOrez(40, 20)->jakoJpeg();
        $nahled = $this->vytvorNahled($jpeg);

        self::assertNotSame($avif, $jpeg->url());
        self::assertStringEndsWith('.jpg', (string) strtok($jpeg->url(), '?'));
        self::assertSame('image/jpeg', getimagesize($nahled)['mime']);
        self::assertGreaterThan(240, min(self::barvaPixelu($nahled, 5, 10)), 'Průhledná půlka je bílá');
        self::assertGreaterThan(self::barvaPixelu($nahled, 35, 10)['r'] + 60, self::barvaPixelu($nahled, 35, 10)['g'], 'Krytá půlka zůstala zelená');
    }

    private function vytvorNahled(\Nahled $nahled): string
    {
        $soubor = self::souborNahledu($nahled->url());
        $this->nahledyKUklidu[] = $soubor;

        return $soubor;
    }
}
