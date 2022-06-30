<?php

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Uzivatel\Finance;
use Gamecon\Uzivatel\KategorieNeplatice;
use PHPUnit\Framework\TestCase;

class KategorieNeplaticeTest extends TestCase
{

    /**
     * @dataProvider dataNeplatice
     */
    public function testDejCiselnouKategoriiNeplatice(
        Finance $finance,
        ?string $kdySeRegistrovalNaLetosniGc,
        bool    $maPravoPlatitAzNaMiste,
        ?string $zacatekVlnyOdhlasovani, // prvni nebo druha vlna
        int     $rok,
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
            $zacatekVlnyOdhlasovani
                ? new \DateTimeImmutable($zacatekVlnyOdhlasovani)
                : null,
            $rok,
            $castkaVelkyDluh,
            $castkaPoslalDost,
            $pocetDnuPredVlnouKdyJeJesteChrane
        );
        self::assertSame($ocekavanaKategorieNeplatice, $kategorieNeplatice->dejCiselnouKategoriiNeplatice());
    }

    public function dataNeplatice(): array {
        $ted = 'now';
        $predChvili = '-1 second';
        $predMesicem = '-1 month';
        $zitra = '+1 day';
        $nemaPravoPlatitAzNaMiste = false;

        $dataNeplatice = [];
        foreach ($this->pravoPlatitAzNaMistePrebijeVsechno() as $index => $pravoPLatitAzNaMistePrebijeVsechno) {
            $dataNeplatice['právo platit až na místě přebije všechno ' . chr(ord('a') + $index)] = $pravoPLatitAzNaMistePrebijeVsechno;
        }

        return array_merge(
            $dataNeplatice,
            [
                'neznámé přihlášení na GC nemá kategorii' => [$this->finance(), null, $nemaPravoPlatitAzNaMiste, $ted, ROK, 0.0, 0, 0, null],
                'neznámý začátek vlny odhlašování nemá kategorii' => [$this->finance(), $ted, $nemaPravoPlatitAzNaMiste, null, ROK, 0.0, 0, 0, null],
                'vlna odhlašování před přihlášením na GC nemá kategorii' => [$this->finance(), $ted, $nemaPravoPlatitAzNaMiste, $predChvili, ROK, 0.0, 0, 0, null],
                'registrován v ochranné lhůtě pár dní před vlnou odhlašování' => [$this->finance(), '-9 days -59 minutes -59 seconds', $nemaPravoPlatitAzNaMiste, $ted, ROK, 0.0, 0, 10 /* chráněn tolik dní před odhlašováním */, 4],
                'letos poslal málo a má velký dluh' => [$this->finance(0.1), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROK, 0.0, PHP_INT_MAX, 0, 2],
                'letos nic, z loňska žádný zůstatek a má dluh' => [$this->finance(0.0, 0.0, -0.1), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROK, 0.0, PHP_INT_MAX, 0, 1],
                'letos nic, z loňska něco málo a má malý dluh' => [$this->finance(0.0, 0.1, -0.1), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROK, -0.2 /* < stav -0.1 = malý dluh */, PHP_INT_MAX, 0, 3],
                'letos poslal dost' => [$this->finance(100.0), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROK, -0.2 /* < stav -0.1 = malý dluh */, 100, 0, 4],
            ]
        );
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
                    'kdy_se_registroval_na_letosni_gc' => $kdySeRegistrovalNaLetosniGc,
                    'zacatek_vlny_odhlasovani' => $zacatekVlnyOdhlasovani,
                    'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => $pocetDnuPredVlnouKdyJeJesteChranen,
                ] = $registrovalSeKVlne;
                $kombinace[] = [
                    $this->finance($sumaPlateb),
                    $kdySeRegistrovalNaLetosniGc,
                    true,
                    $zacatekVlnyOdhlasovani,
                    ROK,
                    0.0,
                    $castkaPoslalDost,
                    $pocetDnuPredVlnouKdyJeJesteChranen,
                    6,
                ];
            }
        }

        foreach ($this->kombinaceProKategorii3() as $velkyDluh) {
            [
                'suma_plateb' => $sumaPlateb,
                'zustatek_z_predchozich_rocniku' => $zustatekZPredchozichRocniku,
                'stav' => $stav,
                'castka_velky_dluh' => $castkaVelkyDluh,
                'castka_poslal_dost' => $castkaPoslalDost,
            ] = $velkyDluh;
            foreach ($this->registrovalSeAzPoVlneOdhlasovaniNeboPredNiNeboNevime() as $registrovalSeKVlne) {
                [
                    'kdy_se_registroval_na_letosni_gc' => $kdySeRegistrovalNaLetosniGc,
                    'zacatek_vlny_odhlasovani' => $zacatekVlnyOdhlasovani,
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
                    ROK,
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
     * @see \Gamecon\Uzivatel\KategorieNeplatice::LETOS_NEPOSLAL_NIC_Z_LONSKA_NECO_MA_A_MA_MALY_DLUH
     */
    private function kombinaceProKategorii3(): array {
        return [
            'nic letos neposlal' => [
                'suma_plateb' => 0.0,
                'zustatek_z_predchozich_rocniku' => 0.0,
                'stav' => 0.0,
                'castka_velky_dluh' => 0.0,
                'castka_poslal_dost' => 0.0,
            ],
            'letos poslal, ale nemá zůstatek z předchozích ročníků' => [
                'suma_plateb' => 0.1,
                'zustatek_z_predchozich_rocniku' => 0.0,
                'stav' => 0.0,
                'castka_velky_dluh' => 0.0,
                'castka_poslal_dost' => 0.0,
            ],
            'letos poslal, má zůstatek z předchozích ročníků, ale nemá velký dluh' => [
                'suma_plateb' => 0.1,
                'zustatek_z_predchozich_rocniku' => 0.1,
                'stav' => -0.2, // stejné jako castka_velky_dluh
                'castka_velky_dluh' => -0.2,
                'castka_poslal_dost' => 0.0,
            ],
            'letos poslal, má zůstatek z předchozích ročníků a má velký dluh' => [
                'suma_plateb' => 0.1,
                'zustatek_z_predchozich_rocniku' => 0.1,
                'stav' => -0.3,
                'castka_velky_dluh' => -0.2,
                'castka_poslal_dost' => 0.0,
            ],
        ];
    }

    /**
     * Kategorie neplatiče null (nevíme) a
     * @see \Gamecon\Uzivatel\KategorieNeplatice::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU
     */
    private function registrovalSeAzPoVlneOdhlasovaniNeboPredNiNeboNevime() {
        return [
            'neznáme ani registraci na GC, ani datum vlny odhlašování' => [
                'kdy_se_registroval_na_letosni_gc' => null,
                'zacatek_vlny_odhlasovani' => null,
                'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => 10,
            ],
            'neznáme registraci na GC' => [
                'kdy_se_registroval_na_letosni_gc' => null,
                'zacatek_vlny_odhlasovani' => $ted = 'now',
                'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => 10,
            ],
            'neznáme datum vlny odhlašování' => [
                'kdy_se_registroval_na_letosni_gc' => $ted,
                'zacatek_vlny_odhlasovani' => null,
                'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => 10,
            ],
            'registroval se před vlnou odhlašování' => [
                'kdy_se_registroval_na_letosni_gc' => '-9 days -23 hours -59 seconds',
                'zacatek_vlny_odhlasovani' => $ted,
                'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => 10,
            ],
            'registroval se zároveň s vlnou odhlašování' => [
                'kdy_se_registroval_na_letosni_gc' => $ted,
                'zacatek_vlny_odhlasovani' => $ted,
                'pocet_dnu_pred_vlnou_kdy_je_jeste_chranen' => 10,
            ],
            'registroval se až po vlně odhlašování' => [
                'kdy_se_registroval_na_letosni_gc' => $ted,
                'zacatek_vlny_odhlasovani' => '+2 seconds',
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
            'vic' => [
                'suma_plateb' => 9999.1,
                'castka_poslal_dost' => 9999,
            ],
            'presne' => [
                'suma_plateb' => 9999,
                'castka_poslal_dost' => 9999,
            ],
        ];
    }

    private function letosZaplatilMalo(): array {
        return [
            'malo' => [
                'suma_plateb' => 9998.9,
                'castka_poslal_dost' => 9999,
            ],
        ];
    }

    private function finance(float $sumaPlateb = 0.0, float $zustatekZPredchozichRocniku = 0.0, float $stav = 0.0): Finance {
        return new class($sumaPlateb, $zustatekZPredchozichRocniku, $stav) extends Finance {
            /** @var float */
            private $sumaPlateb;
            /** @var float */
            private $zustatekZPredchozichRocniku;
            /** @var float */
            private $stav;

            public function __construct(
                float $sumaPlateb,
                float $zustatekZPredchozichRocniku,
                float $stav
            ) {
                $this->sumaPlateb = $sumaPlateb;
                $this->zustatekZPredchozichRocniku = $zustatekZPredchozichRocniku;
                $this->stav = $stav;
            }

            public function sumaPlateb(int $rok = ROK): float {
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
}
