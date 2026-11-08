<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\ImagickAvif;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ImagickAvifTest extends TestCase
{
    use TestovaciDokumenty;

    protected function tearDown(): void
    {
        ImagickAvif::setZkouskaProTesty(null);
        ImagickAvif::setStarsiImagickProTesty(null);
        ImagickAvif::setZapisProTesty(null);
        @unlink(ImagickAvif::getSouborMezipameti());
    }

    /**
     * Docker obraz, ve kterém se spouští testy, musí AVIF zapsat; stačí k tomu kodér libheif (balíček
     * `libheif-plugin-aomenc` v `.docker/Dockerfile`). Bez něj Imagick AVIF vypisuje mezi formáty, ale zápis selže.
     *
     * @test
     */
    public function tentoImagickZapiseAvifSBarvamiIPruhlednosti(): void
    {
        self::assertContains('AVIF', \Imagick::queryFormats('AVIF'));
        self::assertSame(
            [
                'neprusvitny' => true,
                'prusvitny'   => true,
            ],
            ImagickAvif::provedZkousku(),
            'AVIF se nezapsal nebo se přečetl jinak, v obraze chybí kodér AVIF?',
        );
    }

    /**
     * @test
     */
    public function vysledekZkouskySePamatujeVSouboruAPoHodineSeZopakuje(): void
    {
        $soubor = ImagickAvif::getSouborMezipameti();
        @unlink($soubor);

        self::assertTrue(ImagickAvif::isAvifWritable(true));
        self::assertFileExists($soubor);
        self::assertSame([
            'neprusvitny' => true,
            'prusvitny'   => true,
        ], json_decode((string) file_get_contents($soubor), true));

        // výsledek ze souboru má přednost před novou zkouškou
        file_put_contents($soubor, '{"neprusvitny":true,"prusvitny":false}');
        ImagickAvif::setZkouskaProTesty(null);
        self::assertTrue(ImagickAvif::isAvifWritable(false));
        self::assertFalse(ImagickAvif::isAvifWritable(true), 'Použil se výsledek ze souboru');

        // starší než hodinu a poškozený soubor se ignorují
        touch($soubor, time() - 7200);
        ImagickAvif::setZkouskaProTesty(null);
        self::assertTrue(ImagickAvif::isAvifWritable(true), 'Starý výsledek se nepoužil');
        file_put_contents($soubor, 'nesmysl');
        ImagickAvif::setZkouskaProTesty(null);
        self::assertTrue(ImagickAvif::isAvifWritable(true), 'Poškozený soubor se nepoužil');
        self::assertSame([
            'neprusvitny' => true,
            'prusvitny'   => true,
        ], json_decode((string) file_get_contents($soubor), true), 'Soubor se opravil');
    }

    /**
     * @test
     */
    public function zapsanyAvifSeDaPrecistASeStejnymiBarvami(): void
    {
        $obrazek = new \Imagick();
        $obrazek->newImage(32, 16, new \ImagickPixel('rgb(0,200,0)'));
        ImagickAvif::nastavZapis($obrazek, 90);
        $obsah = $obrazek->getImageBlob();

        $nacteny = new \Imagick();
        $nacteny->readImageBlob($obsah);
        ImagickAvif::pripravPoNacteni($nacteny);

        self::assertSame('avif', strtolower($nacteny->getImageFormat()));
        $barva = $nacteny->getImagePixelColor(16, 8)->getColor();
        self::assertEqualsWithDelta(0, $barva['r'], 12);
        self::assertEqualsWithDelta(200, $barva['g'], 12);
        self::assertEqualsWithDelta(0, $barva['b'], 12);
    }

    /**
     * Kvalitu bere zapisovač AVIF z nastavení zápisu, ne z obrázku. Bez toho by každý AVIF vyšel s výchozími 50.
     *
     * @test
     */
    public function kvalitaSeUplatniProtozeZapisovacAvifCteJenNastaveniZapisu(): void
    {
        $velikosti = [];
        foreach ([30, 90] as $kvalita) {
            $obrazek = new \Imagick();
            $obrazek->newPseudoImage(200, 200, 'plasma:fractal');
            ImagickAvif::nastavZapis($obrazek, $kvalita);
            $velikosti[$kvalita] = strlen($obrazek->getImageBlob());
        }

        self::assertGreaterThan($velikosti[30] * 2, $velikosti[90]);
    }

    /**
     * ImageMagick 6 vrací AVIF v YCbCr a barvy by byly úplně jiné. Obrázek v YCbCr se převede do sRGB, ten v sRGB zůstane.
     *
     * @test
     */
    public function obrazekVYcbcrSePoNacteniPrevedeDoSrgbObrazekVSrgbZustane(): void
    {
        $cerveny = new \Imagick();
        $cerveny->newImage(8, 8, new \ImagickPixel('rgb(255,0,0)'));
        $cerveny->transformImageColorspace(\Imagick::COLORSPACE_YCBCR);
        self::assertSame(\Imagick::COLORSPACE_YCBCR, $cerveny->getImageColorspace(), 'Bez téhle kontroly by test prošel i s obrázkem, který YCbCr nebyl');

        ImagickAvif::pripravPoNacteni($cerveny);

        self::assertSame(\Imagick::COLORSPACE_SRGB, $cerveny->getImageColorspace());
        $barva = $cerveny->getImagePixelColor(4, 4)->getColor();
        self::assertSame([255, 0, 0], [$barva['r'], $barva['g'], $barva['b']]);

        $srgb = new \Imagick();
        $srgb->newImage(8, 8, new \ImagickPixel('rgb(0,0,255)'));
        ImagickAvif::pripravPoNacteni($srgb);
        self::assertSame(\Imagick::COLORSPACE_SRGB, $srgb->getImageColorspace());
        self::assertSame(255, $srgb->getImagePixelColor(4, 4)->getColor()['b']);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideKvality(): iterable
    {
        yield 'kvalita z měření, ke které se interpoluje' => [85, 70];
        yield 'výchozí kvalita náhledů' => [92, 82];
        yield 'vysoká kvalita přihlášky' => [98, 90];
        yield 'nejnižší změřená' => [80, 62];
        yield 'mezi dvěma změřenými' => [88, 75];
        yield 'pod nejnižší změřenou' => [70, 52];
        yield 'bezztrátová' => [100, 100];
        yield 'nad stupnicí' => [120, 100];
    }

    /**
     * @test
     *
     * @dataProvider provideKvality
     */
    public function kvalitaWebpSePrevedeNaKvalituAvif(int $kvalitaWebp, int $ocekavanaKvalitaAvif): void
    {
        self::assertSame($ocekavanaKvalitaAvif, ImagickAvif::getKvalitaAvifPodleWebp($kvalitaWebp));
    }

    /**
     * @test
     */
    public function kvalitaAvifSeSVyssiKvalitouWebpNikdyNesnizuje(): void
    {
        $predchozi = 0;
        foreach (range(1, 100) as $kvalitaWebp) {
            $kvalitaAvif = ImagickAvif::getKvalitaAvifPodleWebp($kvalitaWebp);
            self::assertGreaterThanOrEqual($predchozi, $kvalitaAvif, "WebP {$kvalitaWebp}");
            self::assertGreaterThanOrEqual(1, $kvalitaAvif);
            self::assertLessThanOrEqual(100, $kvalitaAvif);
            $predchozi = $kvalitaAvif;
        }
    }

    /**
     * Průhlednost nese AVIF jako pomocný obraz s označením v bloku `auxC`; ImageMagick 6 ji u AVIF bez průhlednosti
     * dodá s náhodnými hodnotami, a podle označení se pozná, že ji má zahodit.
     *
     * @test
     */
    public function pruhlednostAvifSePoznaPodleOznaceniVSouboru(): void
    {
        $zkouska = sys_get_temp_dir() . '/gamecon-avif-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($zkouska);
        try {
            $sPruhlednosti = new \Imagick();
            $sPruhlednosti->newImage(16, 16, new \ImagickPixel('transparent'));
            ImagickAvif::nastavZapis($sPruhlednosti, 80);
            file_put_contents($zkouska . '/s.avif', $sPruhlednosti->getImageBlob());
            file_put_contents($zkouska . '/bez.avif', self::avifBajty());

            self::assertTrue(ImagickAvif::isTransparent($zkouska . '/s.avif'));
            self::assertFalse(ImagickAvif::isTransparent($zkouska . '/bez.avif'));
            self::assertFalse(ImagickAvif::isTransparent($zkouska . '/neexistuje.avif'));
        } finally {
            (new Filesystem())->remove($zkouska);
        }
    }

    /**
     * @test
     */
    public function pruhlednostObrazkuZAvifBezPruhlednostiSeVypneAZAvifSNiZustane(): void
    {
        $zkouska = sys_get_temp_dir() . '/gamecon-avif-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($zkouska);
        try {
            file_put_contents($zkouska . '/bez.avif', self::avifBajty());
            $sPruhlednosti = new \Imagick();
            $sPruhlednosti->newImage(16, 16, new \ImagickPixel('transparent'));
            ImagickAvif::nastavZapis($sPruhlednosti, 80);
            file_put_contents($zkouska . '/s.avif', $sPruhlednosti->getImageBlob());

            // obrázek, který ImageMagick 6 přečte s náhodnou průhledností, napodobí obrázek s průhledným kanálem
            $nahodnaPruhlednost = new \Imagick();
            $nahodnaPruhlednost->newImage(16, 12, new \ImagickPixel('rgba(10,20,30,0.5)'));
            self::assertTrue($nahodnaPruhlednost->getImageAlphaChannel(), 'Bez téhle kontroly by test prošel i s obrázkem bez průhlednosti');
            ImagickAvif::setStarsiImagickProTesty(true);
            ImagickAvif::pripravPoNacteni($nahodnaPruhlednost, $zkouska . '/bez.avif');
            self::assertFalse($nahodnaPruhlednost->getImageAlphaChannel());
            self::assertSame(1.0, round((float) $nahodnaPruhlednost->getImagePixelColor(3, 3)->getColorValue(\Imagick::COLOR_ALPHA), 1));

            $zustaneSPruhlednosti = new \Imagick();
            $zustaneSPruhlednosti->newImage(16, 12, new \ImagickPixel('rgba(10,20,30,0.5)'));
            ImagickAvif::pripravPoNacteni($zustaneSPruhlednosti, $zkouska . '/s.avif');
            self::assertTrue($zustaneSPruhlednosti->getImageAlphaChannel());

            // ImageMagick 7 čte průhlednost správně a nic se nevypíná, ani když ji označení v souboru nenašlo (velký profil)
            $zustaneNaSedmicce = new \Imagick();
            $zustaneNaSedmicce->newImage(16, 12, new \ImagickPixel('rgba(10,20,30,0.5)'));
            ImagickAvif::setStarsiImagickProTesty(false);
            ImagickAvif::pripravPoNacteni($zustaneNaSedmicce, $zkouska . '/bez.avif');
            self::assertTrue($zustaneNaSedmicce->getImageAlphaChannel());
        } finally {
            (new Filesystem())->remove($zkouska);
        }
    }

    /**
     * @test
     */
    public function zkouskuJdeNahraditProTestyAVratit(): void
    {
        ImagickAvif::setZkouskaProTesty([
            'neprusvitny' => true,
            'prusvitny'   => false,
        ]);
        self::assertTrue(ImagickAvif::isAvifWritable(false));
        self::assertFalse(ImagickAvif::isAvifWritable(true));

        ImagickAvif::setZkouskaProTesty(null);
        self::assertTrue(ImagickAvif::isAvifWritable(true), 'Skutečná zkouška na tomhle ImageMagicku projde');
    }

    /**
     * O průhlednosti se nerozhoduje podle verze ImageMagicku: 6.9.11 ji zapisuje špatně, 6.9.12 s novějším libheif
     * správně. Rozhodne, co ImageMagick skutečně zapíše a přečte.
     *
     * @test
     */
    public function starsiImagickKterySPruhlednostiZvladneDostaneAvifSPruhlednosti(): void
    {
        ImagickAvif::setStarsiImagickProTesty(true);

        self::assertSame(
            [
                'neprusvitny' => true,
                'prusvitny'   => true,
            ],
            ImagickAvif::provedZkousku(),
            'Dev obraz zapíše i přečte průhlednost správně, takže zkouška projde, i když se tváří jako ImageMagick 6',
        );
    }

    /**
     * Dekodér ImageMagicku 6 někdy vrací náhodnou průhlednost, takže jedno kolečko může projít náhodou (na 22.04 jen každé
     * druhé). Na ImageMagicku 6 se proto opakuje, na novějším, který je deterministický, ne.
     *
     * @test
     */
    public function zkouskaPruhlednostiSeNaStarsimImagickuOpakujeANaNovemNe(): void
    {
        $spoctiZapisyPruhlednosti = static function (bool $starsiImagick): int {
            $zapisu = 0;
            ImagickAvif::setZapisProTesty(static function (\Imagick $obrazek, bool $sPruhlednosti) use (&$zapisu): string {
                if ($sPruhlednosti) {
                    ++$zapisu;
                }

                return $obrazek->getImageBlob();
            });
            ImagickAvif::setStarsiImagickProTesty($starsiImagick);
            ImagickAvif::provedZkousku();

            return $zapisu;
        };

        self::assertGreaterThan(1, $spoctiZapisyPruhlednosti(true));
        self::assertSame(1, $spoctiZapisyPruhlednosti(false));
    }

    /**
     * Opakování zkoušky se zastaví u prvního neúspěchu, takže zkouška, která neprojde, nestojí desetinásobek.
     *
     * @test
     */
    public function zkouskaPruhlednostiSeZastaviUPrvnihoNeuspechu(): void
    {
        $zapisu = 0;
        ImagickAvif::setStarsiImagickProTesty(true);
        ImagickAvif::setZapisProTesty(static function (\Imagick $obrazek, bool $sPruhlednosti) use (&$zapisu): string {
            if ($sPruhlednosti) {
                ++$zapisu;
                $obrazek->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            }

            return $obrazek->getImageBlob();
        });

        ImagickAvif::provedZkousku();

        self::assertSame(1, $zapisu);
    }

    /**
     * ImageMagick 6.9.11 zapíše průhledný obrázek bez pomocného obrazu s průhledností (zkušební obrázek jako 301 bajtů).
     * Takový zapisovač zkouškou neprojde, ani kdyby ho dekodér při čtení náhodou vykouzlil průhlednost.
     *
     * @test
     */
    public function zapisovacKteryPruhlednostZahodiZkouskouNeprojde(): void
    {
        ImagickAvif::setStarsiImagickProTesty(true);
        ImagickAvif::setZapisProTesty(static function (\Imagick $obrazek, bool $sPruhlednosti): string {
            if ($sPruhlednosti) {
                $obrazek->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            }

            return $obrazek->getImageBlob();
        });

        self::assertSame(
            [
                'neprusvitny' => true,
                'prusvitny'   => false,
            ],
            ImagickAvif::provedZkousku(),
        );
    }

    /**
     * @test
     */
    public function znackaPruhlednostiSePoznaVBajtechSouboru(): void
    {
        $sPruhlednosti = new \Imagick();
        $sPruhlednosti->newImage(16, 16, new \ImagickPixel('transparent'));
        ImagickAvif::nastavZapis($sPruhlednosti, 80);

        self::assertTrue(ImagickAvif::maZnackuPruhlednosti($sPruhlednosti->getImageBlob()));
        self::assertFalse(ImagickAvif::maZnackuPruhlednosti(self::avifBajty()));
        self::assertFalse(ImagickAvif::maZnackuPruhlednosti(''));
    }
}
