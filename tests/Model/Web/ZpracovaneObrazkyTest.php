<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Web\ZpracovaneObrazky;
use PHPUnit\Framework\TestCase;

class ZpracovaneObrazkyTest extends TestCase
{
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
}
