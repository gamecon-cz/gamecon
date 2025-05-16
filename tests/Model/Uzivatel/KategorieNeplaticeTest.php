<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Uzivatel\Finance;
use Gamecon\Uzivatel\KategorieNeplatice;
use PHPUnit\Framework\TestCase;

class KategorieNeplaticeTest extends TestCase
{

    /**
     * @test
     */
    public function Mame_srozumitelnou_konstantu_pro_kazde_cislo_kategorie()
    {
        self::assertSame(1, KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH);
        self::assertSame(2, KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH);
        self::assertSame(3, KategorieNeplatice::LETOS_NEPOSLAL_DOST_NEBO_Z_LONSKA_NECO_MA_A_NEMA_VELKY_DLUH);
        self::assertSame(4, KategorieNeplatice::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY);
        self::assertSame(5, KategorieNeplatice::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU);
        self::assertSame(6, KategorieNeplatice::MA_PRAVO_NEODHLASOVAT);
        self::assertSame(7, KategorieNeplatice::NEDLUZNIK);
    }

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
        $kategorieNeplatice         = new KategorieNeplatice(
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
        $zjistenaKategorieNeplatice = $kategorieNeplatice->ciselnaKategoriiNeplatice();
        self::assertSame(
            $ocekavanaKategorieNeplatice,
            $zjistenaKategorieNeplatice,
            sprintf(
                "Očekávána kategorie %s, zjištěna %s",
                var_export($ocekavanaKategorieNeplatice, true),
                var_export($zjistenaKategorieNeplatice, true),
            ),
        );

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
        foreach (self::pravoNerusitObjednavkyPrebijeTemerVsechno() as $index => $pravoNerusitObjednavkyPrebijeVsechnoKromeNedluznika) {
            $dataNeplatice['právo nerušit objednávky přebije skoro všechno ' . $index] = $pravoNerusitObjednavkyPrebijeVsechnoKromeNedluznika;
        }

        return array_merge(
            $dataNeplatice,
            [
                'neznámé přihlášení na GC nemá kategorii'                                                  => self::fixture(
                    finance: self::finance(),
                    kdySeRegistrovalNaLetosniGc: null,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $ted,
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: 0,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: null,
                ),
                'přihlášení po vlně odhlašování na GC znamená chráněný i s mínusem'                        => self::fixture(
                    finance: self::finance(stav: -999),
                    kdySeRegistrovalNaLetosniGc: $ted,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $predChvili,
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: 0,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: KategorieNeplatice::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU,
                ),
                'registrován v ochranné lhůtě pár dní před vlnou odhlašování znamená chráněný i s mínusem' => self::fixture(
                    finance: self::finance(stav: -999),
                    kdySeRegistrovalNaLetosniGc: '-9 days -59 minutes -59 seconds',
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $ted,
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: 0,
                    pocetDnuPredVlnouKdyJeJesteChranen: 10 /* chráněn tolik dní před odhlašováním */,
                    ocekavanaKategorieNeplatice: KategorieNeplatice::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY,
                ),
                'letos poslal málo a má velký dluh'                                                        => self::fixture(
                    finance: self::finance(sumaPlateb: 0.1, stav: -999),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $zitra,
                    castkaVelkyDluh: 999.0,
                    castkaPoslalDost: PHP_INT_MAX,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
                ),
                'letos nic, z loňska něco málo a má malý dluh'                                             => self::fixture(
                    finance: self::finance(zustatekZPredchozichRocniku: 0.1, stav: -0.1),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $zitra,
                    castkaVelkyDluh: 200,
                    castkaPoslalDost: PHP_INT_MAX,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: KategorieNeplatice::LETOS_NEPOSLAL_DOST_NEBO_Z_LONSKA_NECO_MA_A_NEMA_VELKY_DLUH,
                ),
                'letos poslal dost'                                                                        => self::fixture(
                    finance: self::finance(sumaPlateb: 100.0, stav: -PHP_INT_MAX),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $zitra,
                    castkaVelkyDluh: 200,
                    castkaPoslalDost: 100,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: KategorieNeplatice::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY,
                ),
                'letos nic, z loňska nic a nemá velký dluh'                                                => self::fixture(
                    finance: self::finance(stav: -0.1),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $zitra,
                    castkaVelkyDluh: 200,
                    castkaPoslalDost: PHP_INT_MAX,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
                ),
                'letos nic, z loňska nic a má velký dluh'                                                  => self::fixture(
                    finance: self::finance(stav: -0.1),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: $zitra,
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: PHP_INT_MAX,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
                ),
                'letos nic, z loňska nic a má malý dluh situace 2023'                                      => self::fixture(
                    finance: self::finance(stav: -137),
                    kdySeRegistrovalNaLetosniGc: $predMesicem,
                    maPravoNerusitObjednavky: false,
                    zacatekVlnyOdhlasovani: '+14 days',
                    castkaVelkyDluh: 200.0,
                    castkaPoslalDost: 1000,
                    pocetDnuPredVlnouKdyJeJesteChranen: 0,
                    ocekavanaKategorieNeplatice: KategorieNeplatice::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
                ),
            ],
        );
    }

    /**
     * @see KategorieNeplatice::MA_PRAVO_NEODHLASOVAT
     */
    private static function pravoNerusitObjednavkyPrebijeTemerVsechno(): array
    {
        $ocekavanaKategorieNeplatice = static function (?string $kdySeRegistrovalNaLetosniGc, float $stav) {
            return match ($kdySeRegistrovalNaLetosniGc) {
                null => null,
                default => match ($stav >= 0) {
                    true => KategorieNeplatice::NEDLUZNIK,
                    false => KategorieNeplatice::MA_PRAVO_NEODHLASOVAT,
                }
            };
        };

        $kombinace = [];

        // kombinace všeho ostatního
        foreach (self::vsechnyKombinaceFinanci() as $kombinaceFinanci) {
            [
                'sumaPlateb'                  => $sumaPlateb,
                'castkaPoslalDost'            => $castkaPoslalDost,
                'stav'                        => $stav,
                'zustatekZPredchozichRocniku' => $zustatekZPredchozichRocniku,
            ] = $kombinaceFinanci;
            foreach (self::registrovalSeAzPoVlneOdhlasovaniNeboPredNiNeboNevime() as $registrovalSeKVlne) {
                [
                    'kdySeRegistrovalNaLetosniGc'        => $kdySeRegistrovalNaLetosniGc,
                    'zacatekVlnyOdhlasovani'             => $zacatekVlnyOdhlasovani,
                    'pocetDnuPredVlnouKdyJeJesteChranen' => $pocetDnuPredVlnouKdyJeJesteChranen,
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
                    castkaVelkyDluh: 0.0,
                    castkaPoslalDost: $castkaPoslalDost,
                    pocetDnuPredVlnouKdyJeJesteChranen: $pocetDnuPredVlnouKdyJeJesteChranen,
                    ocekavanaKategorieNeplatice: $ocekavanaKategorieNeplatice($kdySeRegistrovalNaLetosniGc, $stav),
                );
            }
        }

        foreach (self::kombinaceProKategorii3VelkyDluh() as $velkyDluh) {
            [
                'sumaPlateb'                  => $sumaPlateb,
                'zustatekZPredchozichRocniku' => $zustatekZPredchozichRocniku,
                'stav'                        => $stav,
                'castka_velky_dluh'           => $castkaVelkyDluh,
                'castkaPoslalDost'            => $castkaPoslalDost,
            ] = $velkyDluh;
            foreach (self::registrovalSeAzPoVlneOdhlasovaniNeboPredNiNeboNevime() as $registrovalSeKVlne) {
                [
                    'kdySeRegistrovalNaLetosniGc'        => $kdySeRegistrovalNaLetosniGc,
                    'zacatekVlnyOdhlasovani'             => $zacatekVlnyOdhlasovani,
                    'pocetDnuPredVlnouKdyJeJesteChranen' => $pocetDnuPredVlnouKdyJeJesteChranen,
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
                    ocekavanaKategorieNeplatice: $ocekavanaKategorieNeplatice($kdySeRegistrovalNaLetosniGc, $stav),
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
                'sumaPlateb'                  => 0.0,
                'zustatekZPredchozichRocniku' => 0.0,
                'stav'                        => 0.0,
                'castka_velky_dluh'           => 0.0,
                'castkaPoslalDost'            => 0.0,
            ],
            'letos poslal, ale nemá zůstatek z předchozích ročníků'                => [
                'sumaPlateb'                  => 0.1,
                'zustatekZPredchozichRocniku' => 0.0,
                'stav'                        => 0.0,
                'castka_velky_dluh'           => 0.0,
                'castkaPoslalDost'            => 0.0,
            ],
            'letos poslal, má zůstatek z předchozích ročníků, ale nemá velký dluh' => [
                'sumaPlateb'                  => 0.1,
                'zustatekZPredchozichRocniku' => 0.1,
                'stav'                        => -0.123, // stejné jako castka_velky_dluh
                'castka_velky_dluh'           => -0.123,
                'castkaPoslalDost'            => 0.0,
            ],
            'letos poslal, má zůstatek z předchozích ročníků a má velký dluh'      => [
                'sumaPlateb'                  => 0.1,
                'zustatekZPredchozichRocniku' => 0.1,
                'stav'                        => -0.3456,
                'castka_velky_dluh'           => -0.2222,
                'castkaPoslalDost'            => 0.0,
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
                'kdySeRegistrovalNaLetosniGc'        => null,
                'zacatekVlnyOdhlasovani'             => $ted = 'now',
                'pocetDnuPredVlnouKdyJeJesteChranen' => 10,
            ],
            'registroval se před vlnou odhlašování'      => [
                'kdySeRegistrovalNaLetosniGc'        => '-9 days -23 hours -59 seconds',
                'zacatekVlnyOdhlasovani'             => $ted,
                'pocetDnuPredVlnouKdyJeJesteChranen' => 10,
            ],
            'registroval se zároveň s vlnou odhlašování' => [
                'kdySeRegistrovalNaLetosniGc'        => $ted,
                'zacatekVlnyOdhlasovani'             => $ted,
                'pocetDnuPredVlnouKdyJeJesteChranen' => 10,
            ],
            'registroval se až po vlně odhlašování'      => [
                'kdySeRegistrovalNaLetosniGc'        => $ted,
                'zacatekVlnyOdhlasovani'             => '+2 seconds',
                'pocetDnuPredVlnouKdyJeJesteChranen' => 10,
            ],
        ];
    }

    /**
     * @see KategorieNeplatice::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY
     */
    private static function vsechnyKombinaceFinanci(): array
    {
        $platby   = [...self::letosZaplatilDost(), ...self::letosZaplatilMalo()];
        $zustatky = self::kombinaceZustatku();

        $kombinace = [];
        foreach ($platby as $platbaANastaveni) {
            foreach ($zustatky as $stavAZustatek) {
                $kombinace[] = [
                    ...$platbaANastaveni,
                    ...$stavAZustatek,
                ];
            }
        }
        return $kombinace;
    }

    private static function kombinaceZustatku(): array
    {
        $cisla             = [[1, 0], [1, 1]];
        $znamenka          = [[1, 1], [-1, -1], [-1, 1], [1, -1]];
        $kombinaceZustatku = [
            [
                'stav'                        => 0,
                'zustatekZPredchozichRocniku' => 0,
            ],
        ];
        foreach ($cisla as $skupinaCisel) {
            foreach ($znamenka as $skupinaZnamenek) {
                $kombinaceZustatku[] = [
                    'stav'                        => $skupinaCisel[0] * $skupinaZnamenek[0],
                    'zustatekZPredchozichRocniku' => $skupinaCisel[1] * $skupinaZnamenek[1],
                ];
            }
        }
        return $kombinaceZustatku;
    }

    private static function letosZaplatilDost(): array
    {
        return [
            'víc'    => [
                'sumaPlateb'       => 9999.1,
                'castkaPoslalDost' => 9999,
            ],
            'přesně' => [
                'sumaPlateb'       => 9999,
                'castkaPoslalDost' => 9999,
            ],
        ];
    }

    private static function letosZaplatilMalo(): array
    {
        return [
            'málo' => [
                'sumaPlateb'       => 9998.9,
                'castkaPoslalDost' => 9999,
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

            public function obnovUdaje(): void
            {
            }

            public function nastavSumuPlateb(float $sumaPlateb)
            {
                $this->sumaPlateb = $sumaPlateb;
            }

            public function sumaPlateb(?int $rocnik = ROCNIK, bool $prepocti = false): float
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
        $finance                           = self::finance(sumaPlateb: 123.456, stav: -0.1);
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
