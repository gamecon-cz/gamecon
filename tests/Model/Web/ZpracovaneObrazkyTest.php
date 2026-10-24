<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\ZpracovaneObrazky;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ZpracovaneObrazkyTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $adresareKUklidu = [];

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->adresareKUklidu);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function popisekObrazkuEshopuJeNazevBezPoradiAPripony(): void
    {
        self::assertSame('Účastnické triko na postavě', ZpracovaneObrazky::popisekObrazkuEshopu('08_Účastnické triko na postavě.jpeg'));
        self::assertSame('nicknack vyrocni', ZpracovaneObrazky::popisekObrazkuEshopu('/cesta/20_nicknack_vyrocni.JPG'));
        self::assertSame('Tematická placka Verneovky', ZpracovaneObrazky::popisekObrazkuEshopu('04_Tematická placka - Verneovky.png'));
    }

    /**
     * @test
     */
    public function popisekObrazkuEshopuPrezijeNazevVeStarsiKodovaniNezUtf8(): void
    {
        // FTP klient z Windows pošle název v cp1250, ne v UTF-8
        self::assertSame('Triko ern', ZpracovaneObrazky::popisekObrazkuEshopu("08_Triko \xE8ern\xE9.jpg"));
    }

    /**
     * @test
     */
    public function poznaVsechnyAdresareKtereSpravujeJinyModul(): void
    {
        $obsah = ZpracovaneObrazky::adresarObsahu() . '/';

        foreach ([
            ZpracovaneObrazky::adresarSponzoruPrehled(),
            ZpracovaneObrazky::adresarSponzoruTitulka(),
            ZpracovaneObrazky::adresarPartneruPrehled(),
            ZpracovaneObrazky::adresarPartneruTitulka(),
            ZpracovaneObrazky::adresarObrazkuEshopu(),
            ZpracovaneObrazky::adresarObrazkuEshopu(ROCNIK - 1),
        ] as $adresar) {
            self::assertStringStartsWith($obsah, $adresar);
            self::assertTrue(
                ZpracovaneObrazky::jeAdresarJinehoModulu(substr($adresar, strlen($obsah)) . '/logo.png'),
                "Adresář {$adresar} spravuje jiný modul",
            );
        }
    }

    /**
     * @test
     */
    public function adresareJinychModuluPoznaIZapisSeZpetnymiLomitky(): void
    {
        self::assertTrue(ZpracovaneObrazky::jeAdresarJinehoModulu('sponzori\\podvrh.png'));
        self::assertTrue(ZpracovaneObrazky::jeAdresarJinehoModulu('materialy\\2026\\eshop\\01_Blok.jpg'));
        self::assertTrue(ZpracovaneObrazky::jeAdresarJinehoModulu('SPONZORI/logo.png'));
    }

    /**
     * Služba cestu před použitím skládá ze segmentů a prázdné zahodí, takže `2026//eshop` je pro ni `2026/eshop`.
     *
     * @test
     */
    public function adresareJinychModuluPoznaIKdyzJeVCesteDvojiteNeboUvodniLomitko(): void
    {
        self::assertTrue(ZpracovaneObrazky::jeAdresarJinehoModulu('materialy/2026//eshop/01_Blok.jpg'));
        self::assertTrue(ZpracovaneObrazky::jeAdresarJinehoModulu('materialy///2026\\\\eshop\\01_Blok.jpg'));
        self::assertTrue(ZpracovaneObrazky::jeAdresarJinehoModulu('/sponzori/logo.png'));
        self::assertTrue(ZpracovaneObrazky::jeAdresarJinehoModulu('//partneri//logo.png'));
        self::assertTrue(ZpracovaneObrazky::jeAdresarJinehoModulu('materialy/2026/eshop/'), 'Samotný adresář');
    }

    /**
     * @test
     */
    public function bezneAdresareObsahuNejsouAdresareJinychModulu(): void
    {
        foreach ([
            'stranky/kontakty/clenka.jpg',
            'blog/jak-na-to/mapa.png',
            'materialy/2026/mapa.jpg',
            'sponzori-na-strance/logo.png',
            'sponzori.png',
        ] as $cesta) {
            self::assertFalse(ZpracovaneObrazky::jeAdresarJinehoModulu($cesta), $cesta);
        }
    }

    /**
     * @test
     */
    public function razeniCsvSeraziLogaPodleSeznamuBezOhleduNaVelikostPismenWwwPriponuAKomentare(): void
    {
        $adresar = $this->adresarSLogy(['albi.cz.png', 'alfa.cz.png', 'blackfire.cz.png', 'zebra.cz.png']);
        file_put_contents(
            $adresar . '/RAZENI.csv',
            "# komentář\n\nBLACKFIRE.cz.jpg\nwww.Albi.cz.png\n",
        );

        self::assertSame(
            ['blackfire.cz', 'albi.cz', 'alfa.cz', 'zebra.cz'],
            array_keys((new ZpracovaneObrazky($adresar))->seznamObrazku()),
        );
    }

    /**
     * @test
     */
    public function logaBezRazeniCsvJsouSerazenaPodleAbecedy(): void
    {
        $adresar = $this->adresarSLogy(['zebra.cz.png', 'albi.cz.png', 'blackfire.cz.png']);

        self::assertSame(
            ['albi.cz', 'blackfire.cz', 'zebra.cz'],
            array_keys((new ZpracovaneObrazky($adresar))->seznamObrazku()),
        );
    }

    /**
     * @test
     */
    public function ulozenePoradiSeNacteVeStejnemPoradi(): void
    {
        $adresar = $this->adresarSLogy(['albi.cz.png', 'alfa.cz.png', 'blackfire.cz.png', 'zebra.cz.png']);
        $loga = new ZpracovaneObrazky($adresar);

        $loga->ulozPoradi(['zebra.cz.png', 'albi.cz.png', 'alfa.cz.png', 'blackfire.cz.png']);

        self::assertSame(
            ['zebra.cz', 'albi.cz', 'alfa.cz', 'blackfire.cz'],
            array_keys($loga->seznamObrazku()),
        );
        $radky = file($adresar . '/RAZENI.csv', FILE_IGNORE_NEW_LINES);
        self::assertStringStartsWith('#', $radky[0]);
        self::assertSame(['zebra.cz.png', 'albi.cz.png', 'alfa.cz.png', 'blackfire.cz.png'], array_slice($radky, 1));
    }

    /**
     * @test
     */
    public function logoKtereVUlozenemPoradiChybiSeZaraziZaNeVDosavadnimPoradi(): void
    {
        $adresar = $this->adresarSLogy(['albi.cz.png', 'alfa.cz.png', 'blackfire.cz.png', 'zebra.cz.png']);
        $loga = new ZpracovaneObrazky($adresar);
        $loga->ulozPoradi(['zebra.cz.png', 'blackfire.cz.png', 'albi.cz.png', 'alfa.cz.png']);

        $loga->ulozPoradi(['alfa.cz.png']);

        self::assertSame(
            ['alfa.cz', 'zebra.cz', 'blackfire.cz', 'albi.cz'],
            array_keys($loga->seznamObrazku()),
        );
    }

    /**
     * @test
     */
    public function pridaneLogoSeZaraziNaKonecUlozenehoPoradi(): void
    {
        $adresar = $this->adresarSLogy(['albi.cz.png', 'zebra.cz.png']);
        $loga = new ZpracovaneObrazky($adresar);
        $loga->ulozPoradi(['zebra.cz.png', 'albi.cz.png']);

        $this->vytvorLogo($adresar, 'aaa.cz.png');

        self::assertSame(['zebra.cz', 'albi.cz', 'aaa.cz'], array_keys($loga->seznamObrazku()));
    }

    /**
     * @test
     */
    public function ulozeniPoradiPreskociNazvyKterePatriMimoAdresarLog(): void
    {
        $adresar = $this->adresarSLogy(['albi.cz.png', 'zebra.cz.png']);
        $loga = new ZpracovaneObrazky($adresar);

        $loga->ulozPoradi(['../RAZENI.csv', 'neexistuje.cz.png', 'zebra.cz.png', 'zebra.cz.png']);

        $radky = array_slice(file($adresar . '/RAZENI.csv', FILE_IGNORE_NEW_LINES), 1);
        self::assertSame(['zebra.cz.png', 'albi.cz.png'], $radky);
        self::assertFileDoesNotExist(dirname($adresar) . '/RAZENI.csv');
    }

    /**
     * @test
     */
    public function ulozeniPoradiBezJedinehoZnamehoLogaNicNezapise(): void
    {
        $adresar = $this->adresarSLogy(['albi.cz.png']);

        try {
            (new ZpracovaneObrazky($adresar))->ulozPoradi(['neexistuje.cz.png']);
            self::fail('Měla vzniknout výjimka');
        } catch (\InvalidArgumentException) {
            self::assertFileDoesNotExist($adresar . '/RAZENI.csv');
        }
    }

    /**
     * @param list<string> $nazvy
     */
    private function adresarSLogy(array $nazvy): string
    {
        $adresar = sys_get_temp_dir() . '/gamecon-loga-' . bin2hex(random_bytes(6)) . '/loga';
        (new Filesystem())->mkdir($adresar);
        $this->adresareKUklidu[] = dirname($adresar);
        foreach ($nazvy as $nazev) {
            $this->vytvorLogo($adresar, $nazev);
        }

        return $adresar;
    }

    private function vytvorLogo(string $adresar, string $nazev): void
    {
        imagepng(imagecreatetruecolor(8, 8), $adresar . '/' . $nazev);
    }
}
