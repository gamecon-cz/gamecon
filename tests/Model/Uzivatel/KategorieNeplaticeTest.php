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
        ?string $kdySePrihlasilNaLetosniGc,
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
            $kdySePrihlasilNaLetosniGc
                ? new \DateTimeImmutable($kdySePrihlasilNaLetosniGc)
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
        $maPravoPlatitAzNaMiste = true;
        $nemaPravoPlatitAzNaMiste = false;

        return [
            'právo platit až na místě přebije všechno' => [$this->finance(), null, $maPravoPlatitAzNaMiste, null, ROK, 0.0, 0, 0, 6],
            'neznámé přihlášení na GC nemá kategorii' => [$this->finance(), null, $nemaPravoPlatitAzNaMiste, $ted, ROK, 0.0, 0, 0, null],
            'neznámý začátek vlny odhlašování nemá kategorii' => [$this->finance(), $ted, $nemaPravoPlatitAzNaMiste, null, ROK, 0.0, 0, 0, null],
            'vlna odhlašování před přihlášením na GC nemá kategorii' => [$this->finance(), $ted, $nemaPravoPlatitAzNaMiste, $predChvili, ROK, 0.0, 0, 0, null],
            'letos poslal málo má velký dluh' => [$this->finance(0.1), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROK, 0.0, PHP_INT_MAX, 0, 2],
            'letos nic, z loňska žádný zůstatek a má dluh' => [$this->finance(0.0, 0.0, -0.1), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROK, 0.0, PHP_INT_MAX, 0, 1],
            'letos nic, z loňska něco málo a má malý dluh' => [$this->finance(0.0, 0.1, -0.1), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROK, -0.2 /* < stav -0.1 = malý dluh */, PHP_INT_MAX, 0, 3],
            'letos poslal dost' => [$this->finance(100.0), $predMesicem, $nemaPravoPlatitAzNaMiste, $zitra, ROK, -0.2 /* < stav -0.1 = malý dluh */, 100, 0, 4],
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
