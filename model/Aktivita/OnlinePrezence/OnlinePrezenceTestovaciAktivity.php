<?php
declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

readonly class OnlinePrezenceTestovaciAktivity
{
    public static function vytvor(SystemoveNastaveni $systemoveNastaveni): self
    {
        return new static(
            Aktivita::dejPrazdnou(),
            StavAktivity::dejPrazdny(),
            $systemoveNastaveni,
        );
    }

    public function __construct(
        private Aktivita           $obecnaAktivita,
        private StavAktivity       $obecnyStav,
        private SystemoveNastaveni $systemoveNastaveni,
    ) {
    }

    /**
     * @param int $rok
     * @param int $limit
     * @return array|Aktivita[]
     * @throws \ReflectionException
     */
    public function dejTestovaciAktivity(
        int $rok = ROCNIK,
        int $limit = 10,
    ): array {
        $organizovaneAktivityFiltr = [
            FiltrAktivity::ROK  => $rok,
            FiltrAktivity::STAV => [
                $this->obecnyStav::PRIPRAVENA,
                $this->obecnyStav::NOVA,
                $this->obecnyStav::AKTIVOVANA,
                $this->obecnyStav::PUBLIKOVANA,
            ],
            FiltrAktivity::OD   => '2000-01-01', // hlavně prostě aby měly vyplěný začátek a konec
            FiltrAktivity::DO   => '3000-01-01',
        ];

        $organizovaneAktivity = $this->obecnaAktivita::zFiltru(
            systemoveNastaveni: $this->systemoveNastaveni,
            filtr: $organizovaneAktivityFiltr,
            razeni: ['zacatek'],
            limit: $limit,
        );

        if ($organizovaneAktivity) {
            return $organizovaneAktivity;
        }

        $stavPublikovana = $this->obecnyStav::PUBLIKOVANA;

        dbBegin();
        dbQuery(<<<SQL
UPDATE akce_seznam
SET stav = '$stavPublikovana'
WHERE rok = $rok
SQL,
        );
        $organizovaneAktivity = $this->dejTestovaciAktivity($rok, $limit);
        dbRollback();

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
        int                $rozptylSekund = 10,
    ) {
        array_walk($aktivity, function (
            Aktivita $aktivita,
        ) use
        (
            $now,
            $rozptylSekund,
        ) {
            $sekundyPredZacatkemKdyUzJeEditovatelna = AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM * 60;
            $sekundyKousekPredTimNezJeEditovatelna  = $sekundyPredZacatkemKdyUzJeEditovatelna + random_int(0, $rozptylSekund);
            $this->upravZacatkyAktivitNa([$aktivita], (clone $now)->modify("+ $sekundyKousekPredTimNezJeEditovatelna seconds"));
        });
    }

    /**
     * @param Aktivita[] $aktivityKeZmene
     * @param \DateTimeInterface $novyZacatekAktivit
     * @throws \ReflectionException
     */
    public function upravZacatkyAktivitNa(
        array              $aktivityKeZmene,
        \DateTimeInterface $novyZacatekAktivit,
    ) {
        array_walk($aktivityKeZmene, static function (
            Aktivita $aktivita,
        ) use
        (
            $novyZacatekAktivit,
        ) {
            $aReflection = (new \ReflectionClass(Aktivita::class))->getProperty('a');
            $aReflection->setAccessible(true);
            $aValue = $aReflection->getValue($aktivita);
            /** @var \DateTimeInterface $zacatek */
            $aValue['zacatek'] = $novyZacatekAktivit->format(\DateTimeInterface::ATOM);
            $aReflection->setValue($aktivita, $aValue);
        });
    }

    /**
     * @param Aktivita[] $aktivityKeZmene
     * @param \DateTimeInterface $novyKonecAktivit
     * @throws \ReflectionException
     */
    public function upravKonceAktivitNa(
        array              $aktivityKeZmene,
        \DateTimeInterface $novyKonecAktivit,
    ) {
        array_walk($aktivityKeZmene, static function (
            Aktivita $aktivita,
        ) use
        (
            $novyKonecAktivit,
        ) {
            $aReflection = (new \ReflectionClass(Aktivita::class))->getProperty('a');
            $aReflection->setAccessible(true);
            $aValue = $aReflection->getValue($aktivita);
            /** @var \DateTimeInterface $zacatek */
            $aValue['konec'] = $novyKonecAktivit->format(\DateTimeInterface::ATOM);
            $aReflection->setValue($aktivita, $aValue);
        });
    }

    public function upravKonceAktivitNaSekundyPoOdemceni(
        Aktivita $aktivita,
        int      $sekundPoOdemceni,
    ) {
        $this->upravKonceAktivitNa(
            [$aktivita],
            (clone $aktivita->zacatek())
                ->modify('-' . AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM . ' minutes')
                ->modify("+$sekundPoOdemceni seconds"),
        );

    }
}
