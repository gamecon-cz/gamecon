<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\SystemoveNastaveni;

use App\Kernel;
use Gamecon\Cache\ProgramStaticFileGenerator;
use Gamecon\Cache\ProgramStaticFileType;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Prostredi\Prostredi;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Symfony\Component\Filesystem\Filesystem;

class SystemoveNastaveniTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function muzuZmenitKurzEura()
    {
        $nastaveni = SystemoveNastaveni::zGlobals();

        $zaznamKurzuEuro = $nastaveni->dejZaznamyNastaveniPodleKlicu(['KURZ_EURO'])['KURZ_EURO'];

        self::assertSame('23.8', $zaznamKurzuEuro['hodnota']);
        self::assertNull($zaznamKurzuEuro['id_uzivatele']);

        $nastaveni->ulozZmenuHodnoty('123', 'KURZ_EURO', \Uzivatel::zId(\Uzivatel::SYSTEM));

        $zaznamKurzuEuroPoZmene = $nastaveni->dejZaznamyNastaveniPodleKlicu(['KURZ_EURO'])['KURZ_EURO'];
        self::assertSame(
            123.0,
            $zaznamKurzuEuroPoZmene['hodnota'],
            'Očekáváme novou hodnotu, zkonvertovanou na float',
        );
        self::assertSame(
            (string) \Uzivatel::SYSTEM,
            $zaznamKurzuEuroPoZmene['id_uzivatele'],
            'Očekáváme ID posledního editujícícho, jako string tak jak se běžně vytáhne z databáze',
        );
    }

    /**
     * @test
     *
     * Regression: změna jakékoli hodnoty v Nastavení musí označit JSON program
     * cache jako dirty, jinak frontend dál zobrazuje stará data (např. po posunu
     * REG_AKTIVIT_OD se aktivity tváří jako vBudoucnu/vDalsiVlne podle staré hodnoty).
     * Reportováno v https://trello.com/c/XkQrBvbK.
     */
    public function ulozZmenuHodnotyOznaciJsonCacheJakoDirty()
    {
        $privateCacheDir = sys_get_temp_dir() . '/gamecon-test-nastaveni-cache-' . getmypid() . '-' . mt_rand();
        $publicCacheDir = sys_get_temp_dir() . '/gamecon-test-nastaveni-public-' . getmypid() . '-' . mt_rand();
        $filesystem = new Filesystem();
        $filesystem->mkdir($privateCacheDir);
        $filesystem->mkdir($publicCacheDir);

        try {
            $nastaveni = new SystemoveNastaveni(
                ROCNIK,
                new DateTimeImmutableStrict(),
                false,
                false,
                DatabazoveNastaveni::vytvorZGlobals(),
                PROJECT_ROOT_DIR,
                $privateCacheDir,
                new Kernel('test', false),
                $publicCacheDir,
            );
            $generator = new ProgramStaticFileGenerator($nastaveni);

            foreach (ProgramStaticFileType::cases() as $typ) {
                self::assertFalse(
                    $generator->hasDirtyFlag($typ),
                    "Test předpokládá čistý stav před změnou hodnoty ({$typ->value})",
                );
            }

            $nastaveni->ulozZmenuHodnoty('123', 'KURZ_EURO', \Uzivatel::zId(\Uzivatel::SYSTEM));

            foreach (ProgramStaticFileType::cases() as $typ) {
                self::assertTrue(
                    $generator->hasDirtyFlag($typ),
                    "Po ulozZmenuHodnoty musí být dirty flag pro {$typ->value} (jinak frontend zobrazí stará data)",
                );
            }
        } finally {
            $filesystem->remove($privateCacheDir);
            $filesystem->remove($publicCacheDir);
        }
    }

    /**
     * @test
     *
     * @dataProvider provideVychoziHodnota
     */
    public function vychoziHodnotaOdpovidaOcekavani(int $rok, string $klic, string $ocekavanaHodnota)
    {
        $nastaveni = $this->systemoveNastaveni($rok, new DateTimeImmutableStrict($rok . '-12-31 23:59:59'));

        self::assertSame($ocekavanaHodnota, $nastaveni->dejVychoziHodnotu($klic));
    }

    private function systemoveNastaveni(
        int $rocnik = ROCNIK,
        DateTimeImmutableStrict $now = new DateTimeImmutableStrict(),
        bool $jsmeNaBete = false,
        bool $jsmeNaLocale = false,
    ): SystemoveNastaveni {
        // Translate the legacy boolean pair into the new Prostredi enum.
        // Tests passed (false, false) for "ostre", (true, false) for "bete",
        // (false, true) for "locale". The (true, true) combination used to
        // throw — keep that for compatibility (Prostredi is single-valued).
        $prostredi = match (true) {
            $jsmeNaBete && $jsmeNaLocale => throw new \LogicException('Nemůžeme být na betě a zároveň na locale'),
            $jsmeNaBete                  => Prostredi::Beta,
            $jsmeNaLocale                => Prostredi::Locale,
            default                      => Prostredi::Ostre,
        };

        return new SystemoveNastaveni(
            $rocnik,
            $now,
            $prostredi,
            DatabazoveNastaveni::vytvorZGlobals(),
            PROJECT_ROOT_DIR,
            SPEC,
            new Kernel('test', false),
            publicCacheDir: CACHE,
        );
    }

    public static function provideVychoziHodnota(): array
    {
        /* 2023 https://trello.com/c/z2gulrWL/481-d%C5%AFle%C5%BEit%C3%A9-term%C3%ADny-2023 */
        return [
            '2022 TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE' => [2022, 'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2022-07-01'],
            // 2023
            '2023 GC_BEZI_OD'                                      => [2023, 'GC_BEZI_OD', '2023-07-20 07:00:00'],
            '2023 GC_BEZI_DO'                                      => [2023, 'GC_BEZI_DO', '2023-07-23 21:00:00'],
            '2023 REG_GC_OD'                                       => [2023, 'REG_GC_OD', '2023-05-11 20:23:00'],
            '2023 REG_GC_DO'                                       => [2023, 'REG_GC_DO', '2023-07-23 21:00:00'],
            '2023 PRVNI_VLNA_KDY'                                  => [2023, 'PRVNI_VLNA_KDY', '2023-05-18 20:23:00'],
            '2023 DRUHA_VLNA_KDY'                                  => [2023, 'DRUHA_VLNA_KDY', '2023-06-08 20:23:00'],
            '2023 TRETI_VLNA_KDY'                                  => [2023, 'TRETI_VLNA_KDY', '2023-07-01 20:23:00'],
            '2023 TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE'              => [2023, 'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2023-06-23'],
            '2023 UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE'           => [2023, 'UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2023-07-16'],
            '2023 JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE'               => [2023, 'JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2023-07-16'],
            '2023 MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE'              => [2023, 'MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2023-07-09'],
            '2023 PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE' => [2023, 'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2023-07-09'],
            '2023 TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY'               => [2023, 'TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY', 'vraceni zustatku GC ID:'],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideKonecUbytovani
     */
    public function muzemeZjistitZeProdejUbytovaniBylUkoncen(string $konecUbytovaniDne, bool $ocekavaneUkoceniProdeje)
    {
        define('UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE', $konecUbytovaniDne);
        $nastaveni = $this->systemoveNastaveni();
        self::assertSame($ocekavaneUkoceniProdeje, $nastaveni->prodejUbytovaniUkoncen());
    }

    public static function provideKonecUbytovani()
    {
        return [
            'Byl ukončen' => [
                (new \DateTimeImmutable())->setTime(0, 0, 0)->modify('-1 second')->format('Y-m-d'),
                true,
            ],
        ];
    }

    /**
     * @test
     *
     * @dataProvider provideKdeJsme
     */
    public function zeSystemovehoNastaveniVimeKdeJsme(bool $jsmeNaBete, bool $jsmeNaLocale, bool $ocekavaneJsmeNaOstre)
    {
        $nastaveni = $this->systemoveNastaveni(ROCNIK, new DateTimeImmutableStrict(), $jsmeNaBete, $jsmeNaLocale);
        self::assertSame($jsmeNaBete, $nastaveni->jsmeNaBete());
        self::assertSame($jsmeNaLocale, $nastaveni->jsmeNaLocale());
        self::assertSame($ocekavaneJsmeNaOstre, $nastaveni->jsmeNaOstre());
    }

    public static function provideKdeJsme(): array
    {
        return [
            'jsme na locale' => [false, true, false],
            'jsme na betě'   => [true, false, false],
            'jsme na ostré'  => [false, false, true],
        ];
    }

    /**
     * @test
     */
    public function nemuzemeNastavitZeJsmeJakNaBeteTakNaLocale()
    {
        $this->expectException(\LogicException::class);
        $this->systemoveNastaveni(ROCNIK, new DateTimeImmutableStrict(), true, true);
    }

    /**
     * @test
     */
    public function zacatekNejblizsiVlnyUbytovaniJeOcekavany()
    {
        $nastaveni = $this->systemoveNastaveni();
        self::assertEquals(
            DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($nastaveni),
            $nastaveni->nejblizsiHromadneOdhlasovaniKdy(),
        );
    }

    /**
     * @test
     */
    public function muzemeZjistitZeJeApril()
    {
        $secondBeforeApril = new DateTimeImmutableStrict('2023-03-31 23:59:59');
        self::assertFalse($this->systemoveNastaveni(ROCNIK, $secondBeforeApril)->jeApril());

        $firstSecondOfApril = $secondBeforeApril->modify('+1 second');
        self::assertTrue($this->systemoveNastaveni(ROCNIK, $firstSecondOfApril)->jeApril());

        $lastSecondOfApril = $secondBeforeApril->modify('+1 day');
        self::assertTrue($this->systemoveNastaveni(ROCNIK, $lastSecondOfApril)->jeApril());

        $secondAfterApril = $lastSecondOfApril->modify('+1 second');
        self::assertFalse($this->systemoveNastaveni(ROCNIK, $secondAfterApril)->jeApril());
    }

    /**
     * @test
     */
    public function muzemeZjistitKolikMinutPoPrihlaseniNaAktivituSeMuzemeOdhlasitBezStorna()
    {
        self::assertSame(
            5,
            $this->systemoveNastaveni(ROCNIK)->kolikMinutJeOdhlaseniBezPokuty(),
        );
    }
}
