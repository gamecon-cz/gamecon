<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\NahravaniObrazkuException;
use Gamecon\Web\OptimalizaceObrazku;
use Gamecon\Web\PouzitiObrazku;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class OptimalizaceObrazkuTest extends TestCase
{
    use TestovaciObrazky;

    /**
     * 1 × 1 px bezztrátové WebP, GD v našem obrazu WebP zapsat neumí
     */
    private const string WEBP_1X1 = 'UklGRhoAAABXRUJQVlA4TA0AAAAvAAAAEAcQERGIiP4HAA==';

    private string $koren;
    private string $zdroje;

    protected function setUp(): void
    {
        $zaklad = sys_get_temp_dir() . '/gamecon-optimalizace-obrazku-' . bin2hex(random_bytes(6));
        $this->koren = $zaklad . '/koren';
        $this->zdroje = $zaklad . '/zdroje';
        (new Filesystem())->mkdir([$this->koren, $this->zdroje]);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(dirname($this->koren));
    }

    /**
     * @test
     */
    public function poznaZeJeObrazekVetsiNezWebPotrebuje(): void
    {
        $optimalizace = new OptimalizaceObrazku(maxRozmerPx: 1920);

        self::assertFalse($optimalizace->jeVetsiNezPotrebuje(1920, 1920));
        self::assertFalse($optimalizace->jeVetsiNezPotrebuje(800, 600));
        self::assertTrue($optimalizace->jeVetsiNezPotrebuje(1921, 10));
        self::assertTrue($optimalizace->jeVetsiNezPotrebuje(10, 1921));
    }

    /**
     * @test
     */
    public function velkaFotkaSeZmensiAUlozJakoWebp(): void
    {
        $nahravani = $this->nahravani();
        $obrazek = $nahravani->overSoubor($this->soubor('fotka.jpg', self::jpgBajty(3000, 2000)));

        self::assertSame(NahravaniObrazku::WEBP, $obrazek->mime);
        self::assertSame('webp', $obrazek->pripona);
        // rozměry nahraného souboru, ne výsledku
        self::assertSame([3000, 2000], [$obrazek->sirka, $obrazek->vyska]);

        $ulozeno = $nahravani->ulozNovy($obrazek, '', 'fotka');

        self::assertSame($this->koren . '/fotka.webp', $ulozeno);
        $info = getimagesize($ulozeno);
        self::assertSame('image/webp', $info['mime']);
        self::assertSame([1920, 1280], [$info[0], $info[1]]);
        self::assertTrue(self::jeCervena($ulozeno, 100, 600));
        self::assertTrue(self::jeModra($ulozeno, 1800, 600));
        self::assertSame(['fotka.webp'], $this->souboryVKoreni());
    }

    /**
     * @test
     */
    public function malyObrazekSeNezvetsujeAleDoWebpSePrevede(): void
    {
        $nahravani = $this->nahravani();

        $ulozeno = $nahravani->ulozNovy(
            $nahravani->overSoubor($this->soubor('maly.jpg', self::jpgBajty(800, 600))),
            '',
            'maly',
        );

        $info = getimagesize($ulozeno);
        self::assertSame('image/webp', $info['mime']);
        self::assertSame([800, 600], [$info[0], $info[1]]);
    }

    /**
     * @test
     */
    public function fotkaZTelefonuSeOtociAZbaviPolohy(): void
    {
        $zdroj = $this->soubor('telefon.jpg', self::jpgSOrientaci(6, 40, 20, sPolohou: true));
        // bez téhle kontroly by test prošel i s fotkou, ve které poloha nikdy nebyla
        self::assertSame('N', (new \Imagick($zdroj))->getImageProperty('exif:GPSLatitudeRef'));
        $nahravani = $this->nahravani();

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'telefon');

        // levá červená půlka se otočením o 90° po směru hodin stane horní
        self::assertSame([20, 40], array_slice(getimagesize($ulozeno), 0, 2));
        self::assertTrue(self::jeCervena($ulozeno, 10, 5));
        self::assertTrue(self::jeModra($ulozeno, 10, 35));
        $vysledek = new \Imagick($ulozeno);
        self::assertNotContains('exif', $vysledek->getImageProfiles('*', false));
        self::assertFalse($vysledek->getImageProperty('exif:GPSLatitudeRef'));
    }

    /**
     * @test
     */
    public function pngSPruhlednostiSeUlozBezztratove(): void
    {
        $nahravani = $this->nahravani();
        $obrazek = $nahravani->overSoubor($this->soubor('logo.png', self::pngSPolovinouPruhlednou(100, 60)));

        self::assertSame(NahravaniObrazku::WEBP, $obrazek->mime);
        $ulozeno = $nahravani->ulozNovy($obrazek, '', 'logo');

        $vysledek = new \Imagick($ulozeno);
        self::assertTrue((bool) $vysledek->getImageAlphaChannel());
        self::assertSame(0.0, (float) $vysledek->getImagePixelColor(10, 30)->getColorValue(\Imagick::COLOR_ALPHA));
        $nepruhledny = $vysledek->getImagePixelColor(80, 30);
        self::assertSame(
            [0, 200, 0],
            [$nepruhledny->getColor()['r'], $nepruhledny->getColor()['g'], $nepruhledny->getColor()['b']],
        );
        self::assertSame(1.0, (float) $nepruhledny->getColorValue(\Imagick::COLOR_ALPHA));
    }

    /**
     * @test
     */
    public function gifZustaneJakoByl(): void
    {
        $nahravani = $this->nahravani();
        $staticky = $this->soubor('a.gif', self::gifBajty());
        $animovany = $this->soubor('b.gif', self::animovanyGifBajty());

        $nahravani->ulozNovy($nahravani->overSoubor($staticky), '', 'a');
        $nahravani->ulozNovy($nahravani->overSoubor($animovany), '', 'b');

        self::assertFileEquals($staticky, $this->koren . '/a.gif');
        self::assertFileEquals($animovany, $this->koren . '/b.gif');
    }

    /**
     * @test
     */
    public function webpMensiNezWebPotrebujeZustaneJakoByl(): void
    {
        $nahravani = $this->nahravani();
        $zdroj = $this->soubor('a.webp', base64_decode(self::WEBP_1X1, true));

        $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'a');

        self::assertFileEquals($zdroj, $this->koren . '/a.webp');
    }

    /**
     * @test
     */
    public function velkeWebpSeZmensi(): void
    {
        $nahravani = $this->nahravani();
        $zdroj = $this->soubor('velke.webp', self::imagickBajty(2500, 1000, 'webp'));

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'velke');

        $info = getimagesize($ulozeno);
        self::assertSame('image/webp', $info['mime']);
        self::assertSame([1920, 768], [$info[0], $info[1]]);
    }

    /**
     * @test
     */
    public function animovaneWebpZustaneJakoByloAniKdyzJeVelke(): void
    {
        $nahravani = $this->nahravani();
        $zdroj = $this->soubor('animace.webp', self::animovanyWebpBajty(2000, 100));

        $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'animace');

        self::assertFileEquals($zdroj, $this->koren . '/animace.webp');
    }

    /**
     * @test
     */
    public function sluzbaBezWebpFormatZachovaAVelkeObrazkyZmensi(): void
    {
        $nahravani = $this->nahravaniBezWebp();

        $jpg = $nahravani->ulozNovy($nahravani->overSoubor($this->soubor('a.jpg', self::jpgBajty(3000, 2000))), '', 'a');
        $png = $nahravani->ulozNovy($nahravani->overSoubor($this->soubor('b.png', self::imagickBajty(3000, 500, 'png'))), '', 'b');

        self::assertSame($this->koren . '/a.jpg', $jpg);
        self::assertSame([1920, 1280, 'image/jpeg'], $this->rozmeryAMime($jpg));
        self::assertSame($this->koren . '/b.png', $png);
        self::assertSame([1920, 320, 'image/png'], $this->rozmeryAMime($png));
    }

    /**
     * @test
     */
    public function sluzbaBezWebpNechaMalyObrazekBezZmeny(): void
    {
        $nahravani = $this->nahravaniBezWebp();
        $zdroj = $this->soubor('maly.jpg', self::jpgBajty(800, 600));

        $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'maly');

        self::assertFileEquals($zdroj, $this->koren . '/maly.jpg');
    }

    /**
     * @test
     */
    public function bezOptimalizaceSeNicNemeni(): void
    {
        $nahravani = new NahravaniObrazku($this->koren);
        $zdroj = $this->soubor('velka.jpg', self::jpgBajty(3000, 2000));

        $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'velka');

        self::assertFileEquals($zdroj, $this->koren . '/velka.jpg');
    }

    /**
     * @test
     */
    public function nahrazeniZachovaFormatSouboruKteryNahrazuje(): void
    {
        file_put_contents($this->koren . '/a.jpg', self::jpgBajty(10, 10));
        file_put_contents($this->koren . '/b.png', 'stary png');
        $nahravani = $this->nahravani();

        $nahravani->nahrad($nahravani->overSoubor($this->soubor('nova.jpg', self::jpgBajty(3000, 2000))), 'a.jpg');
        $nahravani->nahrad($nahravani->overSoubor($this->soubor('nova.png', self::imagickBajty(2500, 100, 'png'))), 'b.png');

        self::assertSame(['a.jpg', 'b.png'], $this->souboryVKoreni());
        self::assertSame([1920, 1280, 'image/jpeg'], $this->rozmeryAMime($this->koren . '/a.jpg'));
        self::assertSame([1920, 77, 'image/png'], $this->rozmeryAMime($this->koren . '/b.png'));
    }

    /**
     * @test
     */
    public function nahrazeniJinymFormatemSeDalOdmita(): void
    {
        file_put_contents($this->koren . '/a.jpg', 'stary jpg');
        $nahravani = $this->nahravani();
        $png = $nahravani->overSoubor($this->soubor('nova.png', self::imagickBajty(30, 20, 'png')));

        $this->expectException(NahravaniObrazkuException::class);
        $this->expectExceptionMessage('a.jpg je ve formátu JPG a nahraný soubor ve formátu PNG');
        $nahravani->nahrad($png, 'a.jpg');
    }

    /**
     * @test
     */
    public function fotkaNahrazujeStarouVariantuAUkladaJiJakoWebp(): void
    {
        file_put_contents($this->koren . '/5.jpg', 'stara fotka');
        $nahravani = $this->nahravani();

        $nahravani->ulozJakoJediny(
            $nahravani->overSoubor($this->soubor('nova.jpg', self::jpgBajty(300, 200))),
            '',
            '5',
        );

        self::assertSame(['5.webp'], $this->souboryVKoreni());
    }

    /**
     * @test
     */
    public function fotkaNaKterouOdkazujeTextZustaneVeStaremFormatu(): void
    {
        file_put_contents($this->koren . '/5.jpg', 'stara fotka');
        $nahravani = $this->nahravani($this->pouzitiVKazdemJpg());

        $ulozeno = $nahravani->ulozJakoJediny(
            $nahravani->overSoubor($this->soubor('nova.jpg', self::jpgBajty(3000, 2000))),
            '',
            '5',
        );

        self::assertSame($this->koren . '/5.jpg', $ulozeno);
        self::assertSame(['5.jpg'], $this->souboryVKoreni());
        self::assertSame([1920, 1280, 'image/jpeg'], $this->rozmeryAMime($ulozeno));
    }

    /**
     * @test
     */
    public function poskozenyObrazekSHlavickouSeOdmitneAnizNecehoZustane(): void
    {
        $nahravani = $this->nahravani();
        $obrazek = $nahravani->overSoubor($this->soubor('rozbity.png', self::poskozenyPngSHlavickou(2500, 2000)));
        $log = $this->zdroje . '/php-error.log';
        $puvodniLog = ini_set('error_log', $log);

        try {
            $nahravani->ulozNovy($obrazek, '', 'rozbity');
            self::fail('Poškozený obrázek se nemá uložit.');
        } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
            self::assertStringContainsString('nepodařilo zpracovat', $nahravaniObrazkuException->getMessage());
        } finally {
            ini_set('error_log', (string) $puvodniLog);
        }
        self::assertSame([], $this->souboryVKoreni());
        // organizátor se dozví jen obecnou hlášku, příčina (třeba zákaz v policy.xml) je v logu
        self::assertStringContainsString('Úprava obrázku selhala', (string) file_get_contents($log));
    }

    /**
     * @test
     */
    public function souborPrevedenyNaWebpSeDaNahraditJpegemIPngem(): void
    {
        $nahravani = $this->nahravani();
        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($this->soubor('a.jpg', self::jpgBajty(300, 200))), '', 'a');
        self::assertSame($this->koren . '/a.webp', $ulozeno);

        $nahravani->nahrad($nahravani->overSoubor($this->soubor('b.jpg', self::jpgBajty(3000, 2000))), 'a.webp');
        self::assertSame([1920, 1280, 'image/webp'], $this->rozmeryAMime($ulozeno));

        $nahravani->nahrad($nahravani->overSoubor($this->soubor('c.png', self::imagickBajty(30, 20, 'png'))), 'a.webp');
        self::assertSame([30, 20, 'image/webp'], $this->rozmeryAMime($ulozeno));
        self::assertSame(['a.webp'], $this->souboryVKoreni());
    }

    /**
     * @test
     */
    public function obrazekDelsiNezServerPrecteSeOdmitneSJasnouHlaskou(): void
    {
        if (max(\Imagick::getResourceLimit(\Imagick::RESOURCETYPE_WIDTH), \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_HEIGHT)) >= 65_000) {
            self::markTestSkipped('ImageMagick tu nemá omezení rozměru, které by šlo překročit hlavičkou JPEG.');
        }
        $limitSirky = (int) \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_WIDTH);
        $limitVysky = (int) \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_HEIGHT);
        $nahravani = $this->nahravani();

        // na hraně projde, o pixel víc ne: ImageMagick by ho přečíst nedokázal a organizátor by dostal jen „poškozený“
        $nahravani->overSoubor($this->soubor('na-hrane.jpg', self::jpgSRozmeryVHlavicce($limitSirky, 10)));
        $this->expectException(NahravaniObrazkuException::class);
        $this->expectExceptionMessage(sprintf('server zvládne nejvýš %d × %d px', $limitSirky, $limitVysky));
        $nahravani->overSoubor($this->soubor('moc-siroky.jpg', self::jpgSRozmeryVHlavicce($limitSirky + 1, 10)));
    }

    /**
     * @test
     */
    public function nabidkaProNahrazeniZahrnujeFormatyKtereSePrevedouDoCile(): void
    {
        self::assertSame('image/webp,image/jpeg,image/png', $this->nahravani()->acceptProNahrazeni(NahravaniObrazku::WEBP));
        self::assertSame('image/jpeg', $this->nahravani()->acceptProNahrazeni(NahravaniObrazku::JPG));
        self::assertSame('image/webp', (new NahravaniObrazku($this->koren))->acceptProNahrazeni(NahravaniObrazku::WEBP));
    }

    /**
     * @test
     */
    public function novyObrazekNevytvoriVedleStarehoSouboruStejnehoNazvuDruhy(): void
    {
        file_put_contents($this->koren . '/stary.jpg', 'stary');
        $nahravani = $this->nahravani();
        $obrazek = $nahravani->overSoubor($this->soubor('novy.jpg', self::jpgBajty(30, 20)));

        $this->expectException(NahravaniObrazkuException::class);
        $this->expectExceptionMessage('Soubor stary.jpg už existuje.');
        $nahravani->ulozNovy($obrazek, '', 'stary');
    }

    /**
     * @test
     */
    public function dokumentStejnehoNazvuNovemuObrazkuNevadi(): void
    {
        file_put_contents($this->koren . '/plakat.pdf', 'dokument');
        $nahravani = new NahravaniObrazku(
            $this->koren,
            formaty: [...NahravaniObrazku::DOKUMENTY, ...NahravaniObrazku::RASTROVE_FORMATY],
            optimalizace: new OptimalizaceObrazku(),
        );

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($this->soubor('plakat.jpg', self::jpgBajty(30, 20))), '', 'plakat');

        self::assertSame($this->koren . '/plakat.webp', $ulozeno);
    }

    /**
     * @test
     */
    public function svgStejnehoNazvuNovemuRastrovemuObrazkuNevadi(): void
    {
        file_put_contents($this->koren . '/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $nahravani = new NahravaniObrazku(
            $this->koren,
            formaty: [...NahravaniObrazku::RASTROVE_FORMATY, NahravaniObrazku::SVG],
            optimalizace: new OptimalizaceObrazku(),
        );

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($this->soubor('logo.png', self::pngSPolovinouPruhlednou(20, 10))), '', 'logo');

        self::assertSame($this->koren . '/logo.webp', $ulozeno);
    }

    /**
     * @test
     */
    public function svgNovemuSouboruNevadiRastrovyObrazekStejnehoNazvu(): void
    {
        file_put_contents($this->koren . '/logo.jpg', 'stary');
        $nahravani = new NahravaniObrazku(
            $this->koren,
            formaty: [...NahravaniObrazku::RASTROVE_FORMATY, NahravaniObrazku::SVG],
            optimalizace: new OptimalizaceObrazku(),
        );
        $svg = $this->soubor('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>');

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($svg), '', 'logo');

        self::assertSame($this->koren . '/logo.svg', $ulozeno);
    }

    public static function provideNahraditWebpNejde(): iterable
    {
        yield 'GIF' => ['pohyb.gif', self::gifBajty()];
        yield 'animovaný PNG' => ['pohyb.png', self::apngBajty(10, 10)];
    }

    /**
     * @test
     *
     * @dataProvider provideNahraditWebpNejde
     */
    public function nahrazeniWebpGifemNeboAnimovanymPngemSeOdmitneSJasnouHlaskou(string $nazev, string $bajty): void
    {
        file_put_contents($this->koren . '/a.webp', 'stary');
        $nahravani = $this->nahravani();
        $obrazek = $nahravani->overSoubor($this->soubor($nazev, $bajty));

        $this->expectException(NahravaniObrazkuException::class);
        $this->expectExceptionMessage('Nahraj prosím WebP, JPG nebo PNG bez animace');
        $nahravani->nahrad($obrazek, 'a.webp');
    }

    /**
     * @test
     */
    public function animovanyPngZustaneJakoByl(): void
    {
        $zdroj = $this->soubor('animace.png', self::apngBajty(2500, 2000));

        foreach ([
            'sWebp'   => $this->nahravani(),
            'bezWebp' => $this->nahravaniBezWebp(),
        ] as $nazev => $nahravani) {
            $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', $nazev);
            self::assertFileEquals($zdroj, $this->koren . '/' . $nazev . '.png');
        }
    }

    /**
     * @test
     */
    public function statickyPngSeNeberuZaAnimovany(): void
    {
        $optimalizace = new OptimalizaceObrazku();

        self::assertTrue($optimalizace->jeAnimovany($this->soubor('a.png', self::apngBajty(10, 10)), NahravaniObrazku::PNG));
        self::assertFalse($optimalizace->jeAnimovany($this->soubor('s.png', self::pngSPolovinouPruhlednou(10, 10)), NahravaniObrazku::PNG));
        self::assertFalse($optimalizace->jeAnimovany($this->soubor('f.jpg', self::jpgBajty(10, 10)), NahravaniObrazku::JPG));
    }

    /**
     * @test
     */
    public function velkyJpegSeDekodujeRovnouZmensenyAVysledekMaSpravnyRozmer(): void
    {
        $nahravani = $this->nahravani();

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($this->soubor('velky.jpg', self::jpgBajty(4000, 2200))), '', 'velky');

        self::assertSame([1920, 1056, 'image/webp'], $this->rozmeryAMime($ulozeno));
        self::assertTrue(self::jeCervena($ulozeno, 100, 500));
        self::assertTrue(self::jeModra($ulozeno, 1800, 500));
    }

    public static function provideOrientace(): iterable
    {
        yield 'transponovaná, vlevo je nahoře' => [5, true];
        yield 'otočená o 90° po směru hodin' => [6, true];
        yield 'transverzální, vlevo je dole' => [7, false];
        yield 'otočená o 90° proti směru hodin' => [8, false];
    }

    /**
     * @test
     *
     * @dataProvider provideOrientace
     */
    public function orientaceZExifuSeUplatniIPriZmenseniAPolohaZmizi(int $orientace, bool $cervenaNahore): void
    {
        $zdroj = $this->soubor('telefon.jpg', self::jpgSOrientaci($orientace, 3000, 1500, sPolohou: true));
        $nahravani = $this->nahravani();

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'telefon');

        self::assertSame([960, 1920], array_slice(getimagesize($ulozeno), 0, 2));
        self::assertTrue($cervenaNahore ? self::jeCervena($ulozeno, 480, 200) : self::jeModra($ulozeno, 480, 200));
        self::assertTrue($cervenaNahore ? self::jeModra($ulozeno, 480, 1700) : self::jeCervena($ulozeno, 480, 1700));
        self::assertNotContains('exif', (new \Imagick($ulozeno))->getImageProfiles('*', false));
    }

    /**
     * @test
     */
    public function cmykJpegSePrevedeDoSrgb(): void
    {
        $cmyk = new \Imagick();
        $cmyk->newImage(100, 100, new \ImagickPixel('rgb(255,0,0)'));
        $cmyk->transformImageColorspace(\Imagick::COLORSPACE_CMYK);
        $cmyk->setImageFormat('jpeg');
        $zdroj = $this->soubor('cmyk.jpg', $cmyk->getImageBlob());
        // bez téhle kontroly by test prošel i s obrázkem, který CMYK nikdy nebyl
        self::assertSame(\Imagick::COLORSPACE_CMYK, (new \Imagick($zdroj))->getImageColorspace());
        $nahravani = $this->nahravani();

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'cmyk');

        self::assertTrue(self::jeCervena($ulozeno, 50, 50));
    }

    /**
     * @test
     */
    public function barevnyProfilSeZachova(): void
    {
        $fotka = new \Imagick();
        $fotka->readImageBlob(self::jpgBajty(300, 200));
        $fotka->setImageProfile('icc', str_repeat("\0", 128));
        $zdroj = $this->soubor('profil.jpg', $fotka->getImageBlob());
        self::assertContains('icc', (new \Imagick($zdroj))->getImageProfiles('*', false));
        $nahravani = $this->nahravani();

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'profil');

        self::assertContains('icc', (new \Imagick($ulozeno))->getImageProfiles('*', false));
    }

    /**
     * @test
     */
    public function nizkaKvalitaJpegSeNezvysujeAVysledekNeniVetsiNezOriginal(): void
    {
        $zdroj = $this->soubor('nizka.jpg', self::fotkaZFotakuBajty(1600, 1200, 40));
        $nahravani = $this->nahravani();

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'nizka');

        self::assertLessThan(filesize($zdroj), filesize($ulozeno));
    }

    /**
     * @test
     */
    public function bezztratoveWebpSePriZmenseniNezmeniNaZtratove(): void
    {
        $nahravani = $this->nahravani();

        $bezztratove = $nahravani->ulozNovy(
            $nahravani->overSoubor($this->soubor('grafika.webp', self::webpBajty(2500, 1000, true))),
            '',
            'grafika',
        );
        $ztratove = $nahravani->ulozNovy(
            $nahravani->overSoubor($this->soubor('foto.webp', self::webpBajty(2500, 1000, false))),
            '',
            'foto',
        );

        self::assertSame([1920, 768], array_slice(getimagesize($bezztratove), 0, 2));
        self::assertSame('VP8L', substr((string) file_get_contents($bezztratove), 12, 4));
        self::assertSame('VP8 ', substr((string) file_get_contents($ztratove), 12, 4));
    }

    /**
     * @test
     */
    public function pngZmensenyVSluzbeBezWebpNenesePuvodniTextoveBloky(): void
    {
        $zdroj = $this->soubor('autor.png', self::pngSTextovymBlokem(2500, 100));
        // bez téhle kontroly by test prošel i s PNG, které žádný text nemělo
        self::assertStringContainsString('Autor', (string) file_get_contents($zdroj));
        $nahravani = $this->nahravaniBezWebp();

        $ulozeno = $nahravani->ulozNovy($nahravani->overSoubor($zdroj), '', 'autor');

        self::assertSame([1920, 77, 'image/png'], $this->rozmeryAMime($ulozeno));
        self::assertStringNotContainsString('tEXt', (string) file_get_contents($ulozeno));
        self::assertStringNotContainsString('Autor', (string) file_get_contents($ulozeno));
    }

    /**
     * @test
     */
    public function galerieEshopuPustiFotkuAzDoPadesatiMegapixelu(): void
    {
        $nahravani = NahravaniObrazku::proObrazkyEshopu();

        $osmUPadesat = $nahravani->overSoubor($this->soubor('velka.jpg', self::jpgSRozmeryVHlavicce(8000, 6000)));

        self::assertSame(NahravaniObrazku::JPG, $osmUPadesat->mime);
        $this->expectException(NahravaniObrazkuException::class);
        $this->expectExceptionMessage('Obrázek má 10000 × 6000 px, to je na web zbytečně moc. Zmenši ho prosím nejvýš na 50 Mpx.');
        $nahravani->overSoubor($this->soubor('obri.jpg', self::jpgSRozmeryVHlavicce(10000, 6000)));
    }

    /**
     * @test
     */
    public function galerieEshopuDalOdmitaGifSVicNezDvanactiMegapixely(): void
    {
        $this->expectException(NahravaniObrazkuException::class);
        $this->expectExceptionMessage('Zmenši ho prosím nejvýš na 12 Mpx.');
        NahravaniObrazku::proObrazkyEshopu()->overSoubor($this->soubor('velky.gif', self::gifSRozmeryVHlavicce(5000, 3000)));
    }

    /**
     * @test
     */
    public function popisLimituRikaCoSeStaneSObrazkem(): void
    {
        self::assertStringContainsString(
            'JPG a PNG se zmenší na nejvýš 1920 px a uloží jako WebP',
            $this->nahravani()->popisLimitu(),
        );
        self::assertStringContainsString(
            'větší obrázky se zmenší na nejvýš 1920 px',
            $this->nahravaniBezWebp()->popisLimitu(),
        );
        self::assertStringNotContainsString('WebP', $this->nahravaniBezWebp()->popisLimitu());
        self::assertStringNotContainsString('zmenší', (new NahravaniObrazku($this->koren))->popisLimitu());
        self::assertStringNotContainsString(
            'zmenší',
            (new NahravaniObrazku($this->koren, formaty: [NahravaniObrazku::PDF], optimalizace: new OptimalizaceObrazku()))->popisLimitu(),
        );
    }

    /**
     * @test
     */
    public function sluzbyProObrazkyZAdministraceObrazkyOptimalizuji(): void
    {
        self::assertStringContainsString('1920 px a uloží jako WebP', NahravaniObrazku::proFotkyUzivatelu()->popisLimitu());
        self::assertStringContainsString('1920 px a uloží jako WebP', NahravaniObrazku::proObrazkyLinii()->popisLimitu());
        self::assertStringContainsString('1920 px a uloží jako WebP', NahravaniObrazku::proObsahWebu()->popisLimitu());
        self::assertStringContainsString('větší obrázky se zmenší na nejvýš 1920 px', NahravaniObrazku::proObrazkyEshopu()->popisLimitu());
    }

    private function nahravani(?PouzitiObrazku $pouziti = null): NahravaniObrazku
    {
        return new NahravaniObrazku($this->koren, $pouziti, optimalizace: new OptimalizaceObrazku());
    }

    private function nahravaniBezWebp(): NahravaniObrazku
    {
        return new NahravaniObrazku(
            $this->koren,
            formaty: [NahravaniObrazku::JPG, NahravaniObrazku::PNG, NahravaniObrazku::GIF],
            optimalizace: new OptimalizaceObrazku(),
        );
    }

    private function pouzitiVKazdemJpg(): PouzitiObrazku
    {
        return new class extends PouzitiObrazku {
            public function __construct()
            {
            }

            public function kdeJePouzity(string $absolutniCesta): array
            {
                return str_ends_with($absolutniCesta, '.jpg')
                    ? [[
                        'popis' => 'stránka O nás',
                        'url'   => 'web/editace-stranek?id=1',
                    ]]
                    : [];
            }
        };
    }

    /**
     * @return list<string>
     */
    private function souboryVKoreni(): array
    {
        return array_values(array_diff(scandir($this->koren), ['.', '..']));
    }

    /**
     * @return array{int, int, string}
     */
    private function rozmeryAMime(string $soubor): array
    {
        $info = getimagesize($soubor);

        return [$info[0], $info[1], $info['mime']];
    }

    private function soubor(string $nazev, string $obsah): string
    {
        $cesta = $this->zdroje . '/' . bin2hex(random_bytes(4)) . '-' . $nazev;
        file_put_contents($cesta, $obsah);

        return $cesta;
    }

    private static function gifBajty(): string
    {
        ob_start();
        imagegif(imagecreatetruecolor(20, 10));

        return (string) ob_get_clean();
    }

    /**
     * GIF, jehož hlavička hlásí jiné rozměry, `getimagesize()` v něm vidí obří obrázek
     */
    private static function gifSRozmeryVHlavicce(int $sirka, int $vyska): string
    {
        return 'GIF89a' . pack('vv', $sirka, $vyska) . "\x00\x00\x00;";
    }

    private static function animovanyGifBajty(): string
    {
        return self::animaceBajty(30, 20, 'gif');
    }

    private static function animovanyWebpBajty(int $sirka, int $vyska): string
    {
        return self::animaceBajty($sirka, $vyska, 'webp');
    }

    private static function animaceBajty(int $sirka, int $vyska, string $format): string
    {
        $animace = new \Imagick();
        foreach (['red', 'green', 'blue'] as $barva) {
            $snimek = new \Imagick();
            $snimek->newImage($sirka, $vyska, new \ImagickPixel($barva));
            $snimek->setImageFormat($format);
            $snimek->setImageDelay(10);
            $animace->addImage($snimek);
        }
        $animace->setImageFormat($format);

        return $animace->getImagesBlob();
    }

    private static function imagickBajty(int $sirka, int $vyska, string $format): string
    {
        $obrazek = new \Imagick();
        $obrazek->newImage($sirka, $vyska, new \ImagickPixel('#3366cc'));
        $obrazek->setImageFormat($format);

        return $obrazek->getImageBlob();
    }

    /**
     * Levá polovina je průhledná, pravá krytě zelená.
     */
    private static function pngSPolovinouPruhlednou(int $sirka, int $vyska): string
    {
        $obrazek = imagecreatetruecolor($sirka, $vyska);
        imagealphablending($obrazek, false);
        imagesavealpha($obrazek, true);
        imagefill($obrazek, 0, 0, imagecolorallocatealpha($obrazek, 0, 0, 0, 127));
        imagefilledrectangle($obrazek, intdiv($sirka, 2), 0, $sirka - 1, $vyska - 1, imagecolorallocate($obrazek, 0, 200, 0));
        ob_start();
        imagepng($obrazek);

        return (string) ob_get_clean();
    }

    /**
     * PNG s platnou hlavičkou, ale nesmyslnými daty: `getimagesize()` si přečte jen hlavičku
     */
    private static function poskozenyPngSHlavickou(int $sirka, int $vyska): string
    {
        return "\x89PNG\r\n\x1A\n"
            . self::pngBlok('IHDR', pack('NNCCCCC', $sirka, $vyska, 8, 2, 0, 0, 0))
            . self::pngBlok('IDAT', 'tohle neni zlib')
            . self::pngBlok('IEND', '');
    }

    /**
     * PNG s blokem `acTL`, podle kterého je animované; bez dekódování, jen s hlavičkou
     */
    private static function apngBajty(int $sirka, int $vyska): string
    {
        return "\x89PNG\r\n\x1A\n"
            . self::pngBlok('IHDR', pack('NNCCCCC', $sirka, $vyska, 8, 2, 0, 0, 0))
            . self::pngBlok('acTL', pack('NN', 2, 0))
            . self::pngBlok('IDAT', 'data')
            . self::pngBlok('IEND', '');
    }

    /**
     * Platné PNG s textovým blokem autora hned za hlavičkou
     */
    private static function pngSTextovymBlokem(int $sirka, int $vyska): string
    {
        ob_start();
        imagepng(imagecreatetruecolor($sirka, $vyska));
        $png = (string) ob_get_clean();
        // podpis (8 bajtů) a blok IHDR (4 + 4 + 13 + 4)
        $konecHlavicky = 8 + 25;

        return substr($png, 0, $konecHlavicky) . self::pngBlok('tEXt', "Autor\0Jan Novák") . substr($png, $konecHlavicky);
    }

    private static function pngBlok(string $typ, string $data): string
    {
        return pack('N', strlen($data)) . $typ . $data . pack('N', crc32($typ . $data));
    }

    private static function webpBajty(int $sirka, int $vyska, bool $bezztratove): string
    {
        $obrazek = new \Imagick();
        $obrazek->newImage($sirka, $vyska, new \ImagickPixel('#cc3300'));
        $obrazek->setImageFormat('webp');
        $obrazek->setOption('webp:lossless', $bezztratove ? 'true' : 'false');

        return $obrazek->getImageBlob();
    }
}
