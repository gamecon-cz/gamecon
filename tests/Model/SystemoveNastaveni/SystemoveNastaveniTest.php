<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\SystemoveNastaveni;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Uzivatel;

class SystemoveNastaveniTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function Muzu_zmenit_kurz_eura() {
        $nastaveni = SystemoveNastaveni::vytvorZGlobals();

        $zaznamKurzuEuro = $nastaveni->dejZaznamyNastaveniPodleKlicu(['KURZ_EURO'])['KURZ_EURO'];
        /** viz migrace 2022-05-05_03-kurz-euro-do-systemoveho-nastaveni.php */
        self::assertSame('24', $zaznamKurzuEuro['hodnota']);
        self::assertNull($zaznamKurzuEuro['id_uzivatele']);

        $nastaveni->ulozZmenuHodnoty(123, 'KURZ_EURO', Uzivatel::zId(Uzivatel::SYSTEM));

        $zaznamKurzuEuroPoZmene = $nastaveni->dejZaznamyNastaveniPodleKlicu(['KURZ_EURO'])['KURZ_EURO'];
        self::assertSame(
            123.0,
            $zaznamKurzuEuroPoZmene['hodnota'],
            'Očekáváme novou hodnotu, zkonvertovanou na float'
        );
        self::assertSame(
            (string)Uzivatel::SYSTEM,
            $zaznamKurzuEuroPoZmene['id_uzivatele'],
            'Očekáváme ID posledního editujícícho, jako string tak jak se běžně vytáhne z databáze'
        );
    }

    /**
     * @test
     * @dataProvider provideVychoziHodnota
     */
    public function Vychozi_hodnota_odpovida_ocekavani(int $rok, string $klic, string $ocekavanaHodnota) {
        $nastaveni = $this->systemoveNastaveni($rok, new DateTimeImmutableStrict($rok . '-12-31 23:59:59'));

        self::assertSame($ocekavanaHodnota, $nastaveni->dejVychoziHodnotu($klic));
    }

    private function systemoveNastaveni(
        int                     $rocnik = ROCNIK,
        DateTimeImmutableStrict $now = new DateTimeImmutableStrict(),
        bool                    $jsmeNaBete = false,
        bool                    $jsmeNaLocale = false
    ): SystemoveNastaveni {
        return new SystemoveNastaveni(
            $rocnik,
            $now,
            $jsmeNaBete,
            $jsmeNaLocale,
            DatabazoveNastaveni::vytvorZGlobals()
        );
    }

    public static function provideVychoziHodnota(): array {
        /** 2023 https://trello.com/c/z2gulrWL/481-d%C5%AFle%C5%BEit%C3%A9-term%C3%ADny-2023 */
        return [
            '2022 TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE'              => [2022, 'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2022-07-01'],
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
            '2023 PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE' => [2023, 'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE', '2023-07-09'],
            '2023 TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY'               => [2023, 'TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY', 'vraceni zustatku GC ID:'],
        ];
    }

    /**
     * @test
     * @dataProvider provideKonecUbytovani
     */
    public function Muzeme_zjistit_ze_prodej_ubytovani_byl_ukoncen(string $konecUbytovaniDne, bool $ocekavaneUkoceniProdeje) {
        define('UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE', $konecUbytovaniDne);
        $nastaveni = $this->systemoveNastaveni();
        self::assertSame($ocekavaneUkoceniProdeje, $nastaveni->prodejUbytovaniUkoncen());
    }

    public static function provideKonecUbytovani() {
        return [
            'Byl ukončen' => [
                (new \DateTimeImmutable())->setTime(0, 0, 0)->modify('-1 second')->format('Y-m-d'),
                true,
            ],
        ];
    }

    /**
     * @test
     * @dataProvider provideKdeJsme
     */
    public function Ze_systemoveho_nastaveni_vime_kde_jsme(bool $jsmeNaBete, bool $jsmeNaLocale, bool $ocekavaneJsmeNaOstre) {
        $nastaveni = $this->systemoveNastaveni(ROCNIK, new DateTimeImmutableStrict(), $jsmeNaBete, $jsmeNaLocale);
        self::assertSame($jsmeNaBete, $nastaveni->jsmeNaBete());
        self::assertSame($jsmeNaLocale, $nastaveni->jsmeNaLocale());
        self::assertSame($ocekavaneJsmeNaOstre, $nastaveni->jsmeNaOstre());
    }

    public static function provideKdeJsme(): array {
        return [
            'jsme na locale' => [false, true, false],
            'jsme na betě'   => [true, false, false],
            'jsme na ostré'  => [false, false, true],
        ];
    }

    /**
     * @test
     */
    public function Nemuzeme_nastavit_ze_jsme_jak_na_bete_tak_na_locale() {
        $this->expectException(\LogicException::class);
        $this->systemoveNastaveni(ROCNIK, new DateTimeImmutableStrict(), true, true);
    }

    /**
     * @test
     */
    public function Zacatek_nejblizsi_vlny_ubytovani_je_ocekavany() {
        $nastaveni = $this->systemoveNastaveni();
        self::assertEquals(
            DateTimeGamecon::nejblizsiHromadneOdhlasovaniKdy($nastaveni),
            $nastaveni->nejblizsiHromadneOdhlasovaniKdy()
        );
    }

    /**
     * @test
     */
    public function Muzeme_zjistit_ze_je_april() {
        $secondBeforeApril = new DateTimeImmutableStrict('2023-03-31 23:59:59');
        self::assertFalse($this->systemoveNastaveni(ROCNIK, $secondBeforeApril)->jeApril());

        $firstSecondOfApril = $secondBeforeApril->modify('+1 second');
        self::assertTrue($this->systemoveNastaveni(ROCNIK, $firstSecondOfApril)->jeApril());

        $lastSecondOfApril = $secondBeforeApril->modify('+1 day');
        self::assertTrue($this->systemoveNastaveni(ROCNIK, $lastSecondOfApril)->jeApril());

        $secondAfterApril = $lastSecondOfApril->modify('+1 second');
        self::assertFalse($this->systemoveNastaveni(ROCNIK, $secondAfterApril)->jeApril());
    }
}
