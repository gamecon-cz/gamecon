<?php declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;

class OnlinePrezenceTestovaciAktivity
{
    /**
     * @var Aktivita
     */
    private $obecnaAktivita;
    /**
     * @var \Stav
     */
    private $obecnyStav;

    public function __construct(Aktivita $obecnaAktivita, \Stav $obecnyStav) {

        $this->obecnaAktivita = $obecnaAktivita;
        $this->obecnyStav = $obecnyStav;
    }

    /**
     * @param int $rok
     * @param int $limit
     * @return array|Aktivita[]
     * @throws \ReflectionException
     */
    public function dejTestovaciAktivity(int $rok = ROK, int $limit = 10): array {
        $organizovaneAktivityFiltr = [
            'rok' => $rok,
            'stav' => [
                $this->obecnyStav::PRIPRAVENA,
                $this->obecnyStav::NOVA,
                $this->obecnyStav::AKTIVOVANA,
                $this->obecnyStav::PUBLIKOVANA,
            ],
        ];

        $organizovaneAktivity = $this->obecnaAktivita::zFiltru(
            $organizovaneAktivityFiltr,
            ['zacatek'], // razeni
            $limit
        );

        return $organizovaneAktivity;
    }

    /**
     * @param Aktivita[] $aktivity
     * @param \DateTimeInterface $now
     * @throws \ReflectionException
     */
    public function upravZacatkyAktivitNaParSekundPredEditovatelnosti(
        array              $aktivity,
        \DateTimeInterface $now,
        int $rozptylSekund = 10
    ) {
        array_walk($aktivity, static function (Aktivita $aktivita) use ($now, $rozptylSekund) {
            $aReflection = (new \ReflectionClass(Aktivita::class))->getProperty('a');
            $aReflection->setAccessible(true);
            $aValue = $aReflection->getValue($aktivita);
            $sekundyPredZacatkemKdyUzJeEditovatelna = MOJE_AKTIVITY_EDITOVATELNE_X_MINUT_PRED_JEJICH_ZACATKEM * 60;
            $sekundyKousekPredTimNezJeEditovatelna = $sekundyPredZacatkemKdyUzJeEditovatelna + random_int(0, $rozptylSekund);
            /** @var \DateTimeInterface $zacatek */
            $zacatek = (clone $now)
                ->modify("+ $sekundyKousekPredTimNezJeEditovatelna seconds");
            $aValue['zacatek'] = $zacatek->format(\DateTimeInterface::ATOM);
            $aReflection->setValue($aktivita, $aValue);
        });
    }

    /**
     * @param Aktivita[] $aktivity
     * @param \DateTimeInterface $novyZacatekAktivit
     * @throws \ReflectionException
     */
    public function upravZacatkyPrvnichAktivitNa(
        array              $aktivity,
        int                $pocetAktivitKuUprave,
        \DateTimeInterface $novyZacatekAktivit
    ) {
        $aktivityKeZmene = array_slice($aktivity, 0, $pocetAktivitKuUprave);
        array_walk($aktivityKeZmene, static function (Aktivita $aktivita) use ($novyZacatekAktivit) {
            $aReflection = (new \ReflectionClass(Aktivita::class))->getProperty('a');
            $aReflection->setAccessible(true);
            $aValue = $aReflection->getValue($aktivita);
            /** @var \DateTimeInterface $zacatek */
            $aValue['zacatek'] = $novyZacatekAktivit->format(\DateTimeInterface::ATOM);
            $aReflection->setValue($aktivita, $aValue);
        });
    }
}
