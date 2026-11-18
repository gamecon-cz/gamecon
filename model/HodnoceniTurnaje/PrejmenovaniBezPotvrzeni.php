<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Nová kritéria mají stejný tvar, ale na některých místech jiné názvy, a už existují hodnocení. Hodnocení zůstanou
 * na místech, tedy pod novými názvy; org turnaje to musí potvrdit klíčem z @see getKlicPotvrzeni().
 */
final class PrejmenovaniBezPotvrzeni extends HodnoceniTurnajeException
{
    /**
     * @param list<array{popis: string, stary: string, novy: string}> $prejmenovani
     */
    public function __construct(
        private readonly array $prejmenovani,
        private readonly string $klicPotvrzeni,
        int $pocetHodnoceni,
    ) {
        parent::__construct(sprintf(
            'Na stejných místech jsou jiné názvy a už je uloženo %d hodnocení. Hodnocení zůstanou na místech, tedy pod'
            . ' novými názvy. Zkontroluj přejmenování a potvrď je.',
            $pocetHodnoceni,
        ));
    }

    /**
     * Co se přejmenuje, místo po místě.
     *
     * @return list<array{popis: string, stary: string, novy: string}>
     */
    public function getPrejmenovani(): array
    {
        return $this->prejmenovani;
    }

    /**
     * Platí jen pro tohle přejmenování: když org mezitím text změní, potvrzuje znovu.
     */
    public function getKlicPotvrzeni(): string
    {
        return $this->klicPotvrzeni;
    }
}
