<?php

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Finance;
use Gamecon\Uzivatel\KategorieNeplatice;
use PHPUnit\Framework\TestCase;

class KategorieNeplaticeTest extends TestCase
{

    /**
     * @test
     * @dataProvider provideDataNeplatice
     */
    public function Muzu_ziskat_ciselnou_kategorii_neplatice_a_zjistit_zda_ma_byt_odhlasen(
        Finance $finance,
        ?string $kdySeRegistrovalNaLetosniGc,
        bool    $maPravoNerusitObjednavky,
        string  $zacatekVlnyOdhlasovani, // prvni nebo druha vlna
        int     $rocnik,
        float   $castkaVelkyDluh,
        float   $castkaPoslalDost,
        int     $pocetDnuPredVlnouKdyJeJesteChrane,
        ?int    $ocekavanaKategorieNeplatice,
    )
    {
        $kategorieNeplatice = new KategorieNeplatice(
            $finance,
            $kdySeRegistrovalNaLetosniGc
                ? new \DateTimeImmutable($kdySeRegistrovalNaLetosniGc)
                : null,
            $maPravoNerusitObjednavky,
            new \DateTimeImmutable($zacatekVlnyOdhlasovani),
            $rocnik,
            $castkaVelkyDluh,
            $castkaPoslalDost,
            $pocetDnuPredVlnouKdyJeJesteChrane
        );
        self::assertSame($ocekavanaKategorieNeplatice, $kategorieNeplatice->ciselnaKategoriiNeplatice());

        self::assertSame(
            $ocekavanaKategorieNeplatice === KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH
            || $ocekavanaKategorieNeplatice === KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
            $kategorieNeplatice->melByBytOdhlasen(),
        );

        self::assertSame(
            $ocekavanaKategorieNeplatice === KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
            $kategorieNeplatice->maSmyslOdhlasitMuJenNeco(),
        );
    }

    public static function provideDataNeplatice(): array
    {
        $ted         = 'now';
        $predChvili  = '-1 second';
        $predMesicem = '-1 month';
        $zitra       = '+1 day';

        $dataNeplatice = [];
        foreach (self::pravoNerusitObjednavkyPrebijeVsechno() as $index => $pravoNerusitObjednavkyPrebijeVsechno) {
            $dataNeplatice['právo  nerušit objednávky přebije všechno ' . self::pismenoPodleIndexu($index)] = $pravoNerusitObjednavkyPrebijeVsechno;
        }

        return array_merge(
            $dataNeplatice,
            [
                'neznámé přihlášení na GC nemá kategorii'                        => self::fixture(
                    finance: self::finance(),
                    kdySeRegistrovalNaLetosniGc: null,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $ted,
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: 0,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: null,
                ),
                'vlna odhlašování před přihlášením na GC znamená chráněný'       => self::fixture(
                    finance: self::finance(),
                    kdySeRegistrovalNaLetosniGc: $ted,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $predChvili,
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: 0,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: 5,
                ),
                'registrován v ochranné lhůtě pár dní před vlnou odhlašování'    => self::fixture(
                    finance: self::finance(),
                    kdySeRegistrovalNaLetosniGc: '-9 days -59 minutes -59 seconds',
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $ted,
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: 0,
                    pocetDnuPredVlnouKdyJeJesteChranen: 10 /* chráněn tolik dní před odhlašováním */,
                    ocekavanaKategorieNeplatice: 4,
                ),
                'letos poslal málo a má velký dluh'                              => self::fixture(
                    finance: self::finance(sumaPlateb: 0.1),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $zitra,
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: PHP_INT_MAX,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: 2,
                ),
                'letos nic, z loňska žádný zůstatek a má velký dluh'             => self::fixture(
                    finance: self::finance(stav: -0.1),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $zitra,
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: PHP_INT_MAX,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: 1,
                ),
                'letos nic, z loňska žádný zůstatek a má malý dluh situace 2023' => self::fixture(
                    finance: self::finance(stav: -137),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: '+14 days',
                    castkaVelkyDluh: 200.0,
                    castkaPoslalDost: 1000,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: 7, // 1 ?
                ),
                'letos nic, z loňska něco málo a má malý dluh'                   => self::fixture(
                    finance: self::finance(zustatekZPredchozichRocniku: 0.1, stav: -0.1),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $zitra,
                    castkaVelkyDluh: 200,
                    castkaPoslalDost: PHP_INT_MAX,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: 3,
                ),
                'letos poslal dost'                                              => self::fixture(
                    finance: self::finance(sumaPlateb: 100.0),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $zitra,
                    castkaVelkyDluh: 200,
                    castkaPoslalDost: 100,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: 4,
                ),
                'letos nic, z loňska nic a nemá velký dluh'                      => self::fixture(
                    finance: self::finance(stav: -0.1),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $zitra,
                    castkaVelkyDluh: 200,
                    castkaPoslalDost: PHP_INT_MAX,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: 7,
                ),
            ],
        );
    }

    private static function pismenoPodleIndexu(int $index): string
    {
        $uvodniPismeno = $index < (ord('z') - ord('a'))
            ? 'a'
            : 'A';
        $posunPismene  = $uvodniPismeno === 'a'
            ? $index
            : $index - (ord('z') - ord('a'));
        return chr(ord($uvodniPismeno) + $posunPismene);
    }

    /**
     * @see KategorieNeplatice::MA_PRAVO_PLATIT_AZ_NA_MISTE
     */
    private static function pravoNerusitObjednavkyPrebijeVsechno(): array
    {
        $kombinace = [];

        // kombinace všeho ostatního
        foreach (self::letosZaplatilDostCiMalo() as $letosZaplatilDostCiMalo) {
            ['suma_plateb' => $sumaPlateb, 'castka_poslal_dost' => $castkaPoslalDost] = $letosZaplatilDostCiMalo;
            foreach (self::registrovalSeAzPoVlneOdhlasovaniNeboPredNiNeboNevime() as $registrovalSeKVlne) {
                [
                    'kdy_se_registroval_na_letosni_gc'          => $kdySeRegistrovalNaLetosniGc,
                    'zacatek_vlny_odhlasovani'                  => $zacatekVlnyOdhlasovani,
                    'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => $pocetDnuPredVlnouKdyJeJesteChranen,
                ] = $registrovalSeKVlne;

                $kombinace[] = self::fixture(
                    finance: self::finance(sumaPlateb: $sumaPlateb),
                    kdySeRegistrovalNaLetosniGc: $kdySeRegistrovalNaLetosniGc,
                    maPravoNerusitObjednavky: true,
                    zacatekVlnyOdhlasovani: $zacatekVlnyOdhlasovani,
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: $castkaPoslalDost,
                    pocetDnuPredVlnouKdyJeJesteChranen: $pocetDnuPredVlnouKdyJeJesteChranen,
                    ocekavanaKategorieNeplatice: 6,
                );
            }
        }

        foreach (self::kombinaceProKategorii3VelkyDluh() as $velkyDluh) {
            [
                'suma_plateb'                    => $sumaPlateb,
                'zustatek_z_predchozich_rocniku' => $zustatekZPredchozichRocniku,
                'stav'                           => $stav,
                'castka_velky_dluh'              => $castkaVelkyDluh,
                'castka_poslal_dost'             => $castkaPoslalDost,
            ] = $velkyDluh;
            foreach (self::registrovalSeAzPoVlneOdhlasovaniNeboPredNiNeboNevime() as $registrovalSeKVlne) {
                [
                    'kdy_se_registroval_na_letosni_gc'          => $kdySeRegistrovalNaLetosniGc,
                    'zacatek_vlny_odhlasovani'                  => $zacatekVlnyOdhlasovani,
                    'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => $pocetDnuPredVlnouKdyJeJesteChranen,
                ] = $registrovalSeKVlne;

                $kombinace[] = self::fixture(
                    finance: self::finance(
                        sumaPlateb: $sumaPlateb,
                        zustatekZPredchozichRocniku: $zustatekZPredchozichRocniku,
                        stav: $stav,
                    ),
                    kdySeRegistrovalNaLetosniGc: $kdySeRegistrovalNaLetosniGc,
                    maPravoNerusitObjednavky: true,
                    zacatekVlnyOdhlasovani: $zacatekVlnyOdhlasovani,
                    castkaVelkyDluh: $castkaVelkyDluh,
                    castkaPoslalDost: $castkaPoslalDost,
                    pocetDnuPredVlnouKdyJeJesteChranen: $pocetDnuPredVlnouKdyJeJesteChranen,
                    ocekavanaKategorieNeplatice: 6,
                );
            }
        }

        return $kombinace;
    }

    private static function fixture(
        Finance $finance,
                $kdySeRegistrovalNaLetosniGc,
        bool    $maPravoNerusitObjednavky,
                $zacatekVlnyOdhlasovani,
                $castkaVelkyDluh,
                $castkaPoslalDost,
                $pocetDnuPredVlnouKdyJeJesteChranen,
        ?int    $ocekavanaKategorieNeplatice,
        int     $rocnik = ROCNIK,
    ): array
    {
        return [
            $finance,
            $kdySeRegistrovalNaLetosniGc,
            $maPravoNerusitObjednavky,
            $zacatekVlnyOdhlasovani,
            $rocnik,
            $castkaVelkyDluh,
            $castkaPoslalDost,
            $pocetDnuPredVlnouKdyJeJesteChranen,
            $ocekavanaKategorieNeplatice,
        ];
    }

    /**
     * @see KategorieNeplatice::LETOS_NEPOSLAL_DOST_NEBO_Z_LONSKA_NECO_MA_A_NEMA_VELKY_DLUH
     */
    private static function kombinaceProKategorii3VelkyDluh(): array
    {
        return [
            'nic letos neposlal'                                                   => [
                'suma_plateb'                    => 0.0,
                'zustatek_z_predchozich_rocniku' => 0.0,
                'stav'                           => 0.0,
                'castka_velky_dluh'              => 0.0,
                'castka_poslal_dost'             => 0.0,
            ],
            'letos poslal, ale nemá zůstatek z předchozích ročníků'                => [
                'suma_plateb'                    => 0.1,
                'zustatek_z_predchozich_rocniku' => 0.0,
                'stav'                           => 0.0,
                'castka_velky_dluh'              => 0.0,
                'castka_poslal_dost'             => 0.0,
            ],
            'letos poslal, má zůstatek z předchozích ročníků, ale nemá velký dluh' => [
                'suma_plateb'                    => 0.1,
                'zustatek_z_predchozich_rocniku' => 0.1,
                'stav'                           => -0.2, // stejné jako castka_velky_dluh
                'castka_velky_dluh'              => -0.2,
                'castka_poslal_dost'             => 0.0,
            ],
            'letos poslal, má zůstatek z předchozích ročníků a má velký dluh'      => [
                'suma_plateb'                    => 0.1,
                'zustatek_z_predchozich_rocniku' => 0.1,
                'stav'                           => -0.3,
                'castka_velky_dluh'              => -0.2,
                'castka_poslal_dost'             => 0.0,
            ],
        ];
    }

    /**
     * Kategorie neplatiče null (nevíme) a
     * @see KategorieNeplatice::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU
     */
    private static function registrovalSeAzPoVlneOdhlasovaniNeboPredNiNeboNevime(): array
    {
        return [
            'neznáme registraci na GC'                   => [
                'kdy_se_registroval_na_letosni_gc'          => null,
                'zacatek_vlny_odhlasovani'                  => $ted = 'now',
                'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => 10,
            ],
            'registroval se před vlnou odhlašování'      => [
                'kdy_se_registroval_na_letosni_gc'          => '-9 days -23 hours -59 seconds',
                'zacatek_vlny_odhlasovani'                  => $ted,
                'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => 10,
            ],
            'registroval se zároveň s vlnou odhlašování' => [
                'kdy_se_registroval_na_letosni_gc'          => $ted,
                'zacatek_vlny_odhlasovani'                  => $ted,
                'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => 10,
            ],
            'registroval se až po vlně odhlašování'      => [
                'kdy_se_registroval_na_letosni_gc'          => $ted,
                'zacatek_vlny_odhlasovani'                  => '+2 seconds',
                'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => 10,
            ],
        ];
    }

    /**
     * @see KategorieNeplatice::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY
     */
    private static function letosZaplatilDostCiMalo(): array
    {
        return array_merge(
            self::letosZaplatilDost(),
            self::letosZaplatilMalo(),
        );
    }

    private static function letosZaplatilDost(): array
    {
        return [
            'vic'    => [
                'suma_plateb'        => 9999.1,
                'castka_poslal_dost' => 9999,
            ],
            'presne' => [
                'suma_plateb'        => 9999,
                'castka_poslal_dost' => 9999,
            ],
        ];
    }

    private static function letosZaplatilMalo(): array
    {
        return [
            'malo' => [
                'suma_plateb'        => 9998.9,
                'castka_poslal_dost' => 9999,
            ],
        ];
    }

    private static function finance(
        float $sumaPlateb = 0.0,
        float $zustatekZPredchozichRocniku = 0.0,
        float $stav = 0.0,
    ): Finance
    {
        return new class($sumaPlateb, $zustatekZPredchozichRocniku, $stav) extends Finance {

            public function __construct(
                protected float $sumaPlateb,
                protected float $zustatekZPredchozichRocniku,
                protected float $stav,
            )
            {
            }

            public function obnovUdaje()
            {
            }

            public function nastavSumuPlateb(float $sumaPlateb)
            {
                $this->sumaPlateb = $sumaPlateb;
            }

            public function sumaPlateb(int $rok = ROCNIK): float
            {
                return $this->sumaPlateb;
            }

            public function zustatekZPredchozichRocniku(): float
            {
                return $this->zustatekZPredchozichRocniku;
            }

            public function stav(): float
            {
                return $this->stav;
            }
        };
    }

    /**
     * @test
     */
    public function Muzu_resetovat_vnitrni_cache()
    {
        $finance                           = self::finance(sumaPlateb: 123.456);
        $kdySeRegistrovalNaLetosniGc       = new DateTimeImmutableStrict('-1 month');
        $maPravoNerusitObjednavky          = false;
        $zacatekVlnyOdhlasovani            = new DateTimeImmutableStrict();
        $rocnik                            = (int)$kdySeRegistrovalNaLetosniGc->format('y');
        $castkaVelkyDluh                   = 123456;
        $castkaPoslalDost                  = 654321;
        $pocetDnuPredVlnouKdyJeJesteChrane = 0;

        $kategorieNeplatice = new KategorieNeplatice(
            $finance,
            $kdySeRegistrovalNaLetosniGc,
            $maPravoNerusitObjednavky,
            $zacatekVlnyOdhlasovani,
            $rocnik,
            $castkaVelkyDluh,
            $castkaPoslalDost,
            $pocetDnuPredVlnouKdyJeJesteChrane
        );

        $puvodniCiselnaKategorieNeplatice = $kategorieNeplatice->ciselnaKategoriiNeplatice();
        self::assertSame(
            KategorieNeplatice::LETOS_NEPOSLAL_DOST_NEBO_Z_LONSKA_NECO_MA_A_NEMA_VELKY_DLUH,
            $puvodniCiselnaKategorieNeplatice,
        );

        foreach ([true, false] as $vcetneSumyLetosnichPlateb) {
            $kategorieNeplatice->obnovUdaje($vcetneSumyLetosnichPlateb);
            self::assertSame(
                $puvodniCiselnaKategorieNeplatice,
                $kategorieNeplatice->ciselnaKategoriiNeplatice(),
                'Po pouhém resetu vnitní cache by se nemělo nic změnit',
            );
        }

        $finance->nastavSumuPlateb($castkaPoslalDost);

        $kategorieNeplatice->obnovUdaje(false);
        self::assertSame(
            $puvodniCiselnaKategorieNeplatice,
            $kategorieNeplatice->ciselnaKategoriiNeplatice(),
            'Po resetu vnitní cache bez sumy letošních plateb by se nemělo nic změnit',
        );

        $kategorieNeplatice->obnovUdaje(true);
        self::assertSame(
            KategorieNeplatice::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY,
            $kategorieNeplatice->ciselnaKategoriiNeplatice(),
        );
        self::assertNotSame(
            $puvodniCiselnaKategorieNeplatice,
            $kategorieNeplatice->ciselnaKategoriiNeplatice(),
            'Po resetu vnitní cache včetně sumy letošních plateb by kategorie neplatiče měla změnit',
        );
    }
}
