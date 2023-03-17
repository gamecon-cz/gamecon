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
     * @dataProvider dataNeplatice
     */
    public function Muzu_ziskat_ciselnou_kategorii_neplatice_a_zjistit_zda_ma_byt_odhlasen(
        Finance $finance,
        ?string $kdySeRegistrovalNaLetosniGc,
        bool    $maPravoPlatitAzNaMiste,
        string  $zacatekVlnyOdhlasovani, // prvni nebo druha vlna
        int     $rocnik,
        float   $castkaVelkyDluh,
        float   $castkaPoslalDost,
        int     $pocetDnuPredVlnouKdyJeJesteChrane,
        ?int    $ocekavanaKategorieNeplatice

    ) {
        $kategorieNeplatice = new KategorieNeplatice(
            $finance,
            $kdySeRegistrovalNaLetosniGc
                ? new \DateTimeImmutable($kdySeRegistrovalNaLetosniGc)
                : null,
            $maPravoPlatitAzNaMiste,
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
            $kategorieNeplatice->melByBytOdhlasen()
        );

        self::assertSame(
            $ocekavanaKategorieNeplatice === KategorieNeplatice::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
            $kategorieNeplatice->maSmyslOdhlasitMuJenNeco()
        );
    }

    public function dataNeplatice(): array {
        $ted                      = 'now';
        $predChvili               = '-1 second';
        $predMesicem              = '-1 month';
        $zitra                    = '+1 day';
        $nemaPravoPlatitAzNaMiste = false;

        $dataNeplatice = [];
        foreach ($this->pravoPlatitAzNaMistePrebijeVsechno() as $index => $pravoPLatitAzNaMistePrebijeVsechno) {
            $dataNeplatice['právo platit až na místě přebije všechno ' . $this->pismenoPodleIndexu($index)] = $pravoPLatitAzNaMistePrebijeVsechno;
        }

        return array_merge(
            $dataNeplatice,
            [
                'neznámé přihlášení na GC nemá kategorii'                     => [$this->finance(), null, $nemaPravoPlatitAzNaMiste, $ted, ROCNIK, 0.0, 0, 0, null],
                'vlna odhlašování před přihlášením na GC znamená chráněný'    => [$this->finance(), $ted, $nemaPravoPlatitAzNaMiste, $predChvili, ROCNIK, 0.0, 0, 0, 5],
                'registrován v ochranné lhůtě pár dní před vlnou odhlašování' => [$this->finance(), '-9 days -59 minutes -59 seconds', $nemaPravoPlatitAzNaMiste, $ted, ROCNIK, 0.0, 0, 10 /* chráněn tolik dní před odhlašováním */, 4],
                'letos poslal málo a má velký dluh'                           => [$this->finance(0.1), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROCNIK, 0.0, PHP_INT_MAX, 0, 2],
                'letos nic, z loňska žádný zůstatek a má dluh'                => [$this->finance(0.0, 0.0, -0.1), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROCNIK, 0.0, PHP_INT_MAX, 0, 1],
                'letos nic, z loňska něco málo a má malý dluh'                => [$this->finance(0.0, 0.1, -0.1), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROCNIK, -0.2 /* < stav -0.1 = malý dluh */, PHP_INT_MAX, 0, 3],
                'letos poslal dost'                                           => [$this->finance(100.0), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROCNIK, -0.2 /* < stav -0.1 = malý dluh */, 100, 0, 4],
                'letos nic, z loňska nic a nemá velký dluh'                   => [$this->finance(0.0, 0.0, -0.1), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROCNIK, -0.2 /* < stav -0.1 = malý dluh */, PHP_INT_MAX, 0, 7],

            ]
        );
    }

    private function pismenoPodleIndexu(int $index): string {
        $uvodniPismeno = $index < (ord('z') - ord('a'))
            ? 'a'
            : 'A';
        $posunPismene  = $uvodniPismeno === 'a'
            ? $index
            : $index - (ord('z') - ord('a'));
        return chr(ord($uvodniPismeno) + $posunPismene);
    }

    /**
     * @see \Gamecon\Uzivatel\KategorieNeplatice::MA_PRAVO_PLATIT_AZ_NA_MISTE
     */
    private function pravoPlatitAzNaMistePrebijeVsechno(): array {
        $kombinace = [];

        // kombinace všeho ostatního
        foreach ($this->letosZaplatilDostCiMalo() as $letosZaplatilDostCiMalo) {
            ['suma_plateb' => $sumaPlateb, 'castka_poslal_dost' => $castkaPoslalDost] = $letosZaplatilDostCiMalo;
            foreach ($this->registrovalSeAzPoVlneOdhlasovaniNeboPredNiNeboNevime() as $registrovalSeKVlne) {
                [
                    'kdy_se_registroval_na_letosni_gc'          => $kdySeRegistrovalNaLetosniGc,
                    'zacatek_vlny_odhlasovani'                  => $zacatekVlnyOdhlasovani,
                    'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => $pocetDnuPredVlnouKdyJeJesteChranen,
                ] = $registrovalSeKVlne;
                $kombinace[] = [
                    $this->finance($sumaPlateb),
                    $kdySeRegistrovalNaLetosniGc,
                    true,
                    $zacatekVlnyOdhlasovani,
                    ROCNIK,
                    0.0,
                    $castkaPoslalDost,
                    $pocetDnuPredVlnouKdyJeJesteChranen,
                    6,
                ];
            }
        }

        foreach ($this->kombinaceProKategorii3VelkyDluh() as $velkyDluh) {
            [
                'suma_plateb'                    => $sumaPlateb,
                'zustatek_z_predchozich_rocniku' => $zustatekZPredchozichRocniku,
                'stav'                           => $stav,
                'castka_velky_dluh'              => $castkaVelkyDluh,
                'castka_poslal_dost'             => $castkaPoslalDost,
            ] = $velkyDluh;
            foreach ($this->registrovalSeAzPoVlneOdhlasovaniNeboPredNiNeboNevime() as $registrovalSeKVlne) {
                [
                    'kdy_se_registroval_na_letosni_gc'          => $kdySeRegistrovalNaLetosniGc,
                    'zacatek_vlny_odhlasovani'                  => $zacatekVlnyOdhlasovani,
                    'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => $pocetDnuPredVlnouKdyJeJesteChranen,
                ] = $registrovalSeKVlne;
                $kombinace[] = [
                    $this->finance(
                        $sumaPlateb,
                        $zustatekZPredchozichRocniku,
                        $stav
                    ),
                    $kdySeRegistrovalNaLetosniGc,
                    true,
                    $zacatekVlnyOdhlasovani,
                    ROCNIK,
                    $castkaVelkyDluh,
                    $castkaPoslalDost,
                    $pocetDnuPredVlnouKdyJeJesteChranen,
                    6,
                ];
            }
        }

        return $kombinace;
    }

    /**
     * @see \Gamecon\Uzivatel\KategorieNeplatice::LETOS_NEPOSLAL_DOST_NEBO_Z_LONSKA_NECO_MA_A_NEMA_VELKY_DLUH
     */
    private function kombinaceProKategorii3VelkyDluh(): array {
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
     * @see \Gamecon\Uzivatel\KategorieNeplatice::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU
     */
    private function registrovalSeAzPoVlneOdhlasovaniNeboPredNiNeboNevime() {
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
     * @see \Gamecon\Uzivatel\KategorieNeplatice::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY
     */
    private function letosZaplatilDostCiMalo(): array {
        return array_merge(
            $this->letosZaplatilDost(),
            $this->letosZaplatilMalo()
        );
    }

    private function letosZaplatilDost(): array {
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

    private function letosZaplatilMalo(): array {
        return [
            'malo' => [
                'suma_plateb'        => 9998.9,
                'castka_poslal_dost' => 9999,
            ],
        ];
    }

    private function finance(
        float $sumaPlateb = 0.0,
        float $zustatekZPredchozichRocniku = 0.0,
        float $stav = 0.0
    ): Finance {
        return new class($sumaPlateb, $zustatekZPredchozichRocniku, $stav) extends Finance {

            public function __construct(
                protected float $sumaPlateb,
                protected float $zustatekZPredchozichRocniku,
                protected float $stav
            ) {
            }

            public function otoc(): static {
                return $this;
            }

            public function nastavSumuPlateb(float $sumaPlateb) {
                $this->sumaPlateb = $sumaPlateb;
            }

            public function sumaPlateb(int $rok = ROCNIK): float {
                return $this->sumaPlateb;
            }

            public function zustatekZPredchozichRocniku(): float {
                return $this->zustatekZPredchozichRocniku;
            }

            public function stav(): float {
                return $this->stav;
            }
        };
    }

    /**
     * @test
     */
    public function Muzu_resetovat_vnitrni_cache() {
        $finance                           = $this->finance(123.456);
        $kdySeRegistrovalNaLetosniGc       = new DateTimeImmutableStrict('-1 month');
        $maPravoPlatitAzNaMiste            = false;
        $zacatekVlnyOdhlasovani            = new DateTimeImmutableStrict();
        $rocnik                            = (int)$kdySeRegistrovalNaLetosniGc->format('y');
        $castkaVelkyDluh                   = 123456;
        $castkaPoslalDost                  = 654321;
        $pocetDnuPredVlnouKdyJeJesteChrane = 0;

        $kategorieNeplatice = new KategorieNeplatice(
            $finance,
            $kdySeRegistrovalNaLetosniGc,
            $maPravoPlatitAzNaMiste,
            $zacatekVlnyOdhlasovani,
            $rocnik,
            $castkaVelkyDluh,
            $castkaPoslalDost,
            $pocetDnuPredVlnouKdyJeJesteChrane
        );

        $puvodniCiselnaKategorieNeplatice = $kategorieNeplatice->ciselnaKategoriiNeplatice();
        self::assertSame(
            KategorieNeplatice::LETOS_NEPOSLAL_DOST_NEBO_Z_LONSKA_NECO_MA_A_NEMA_VELKY_DLUH,
            $puvodniCiselnaKategorieNeplatice
        );

        foreach ([true, false] as $vcetneSumyLetosnichPlateb) {
            $kategorieNeplatice->otoc($vcetneSumyLetosnichPlateb);
            self::assertSame(
                $puvodniCiselnaKategorieNeplatice,
                $kategorieNeplatice->ciselnaKategoriiNeplatice(),
                'Po pouhém resetu vnitní cache by se nemělo nic změnit'
            );
        }

        $finance->nastavSumuPlateb($castkaPoslalDost);

        $kategorieNeplatice->otoc(false);
        self::assertSame(
            $puvodniCiselnaKategorieNeplatice,
            $kategorieNeplatice->ciselnaKategoriiNeplatice(),
            'Po resetu vnitní cache bez sumy letošních plateb by se nemělo nic změnit'
        );

        $kategorieNeplatice->otoc(true);
        self::assertSame(
            KategorieNeplatice::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY,
            $kategorieNeplatice->ciselnaKategoriiNeplatice()
        );
        self::assertNotSame(
            $puvodniCiselnaKategorieNeplatice,
            $kategorieNeplatice->ciselnaKategoriiNeplatice(),
            'Po resetu vnitní cache včetně sumy letošních plateb by kategorie neplatiče měla změnit'
        );
    }
}
