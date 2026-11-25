<?php

declare(strict_types=1);

namespace Gamecon\HodnoceniTurnaje;

/**
 * Uložení kritérií by smazalo, co vypravěči zadali: hodnoty odebraných kritérií, nebo všechna hodnocení, když přibude
 * kritérium (hodnocení by k němu nemělo hodnotu) nebo když z nové škály vypadnou použité hodnoty. Org turnaje to musí
 * potvrdit klíčem z @see getKlicPotvrzeni(). Klíč popisuje, co se smaže, takže když org mezitím změní něco, co maže
 * víc nebo jinak, potvrzuje znovu; přejmenování ani přeřazení klíč nemění.
 */
final class ZmenaKriteriiChcePotvrzeni extends HodnoceniTurnajeException
{
    /**
     * @param list<string> $podrobnosti co se změní a proč se maže, text pro uživatele
     */
    public function __construct(
        private readonly bool $smazaniVsech,
        private readonly int $pocetHodnoceni,
        private readonly array $podrobnosti,
        private readonly string $klicPotvrzeni,
    ) {
        parent::__construct($smazaniVsech
            ? sprintf('Uložení kritérií smaže všech %d hodnocení. Zkontroluj, co se mění, a potvrď to.', $pocetHodnoceni)
            : sprintf(
                'Uložení kritérií smaže hodnoty odebraných kritérií z %d hodnocení. Zkontroluj, co se mění, a potvrď to.',
                $pocetHodnoceni,
            ));
    }

    public function isSmazaniVsech(): bool
    {
        return $this->smazaniVsech;
    }

    public function getPocetHodnoceni(): int
    {
        return $this->pocetHodnoceni;
    }

    /**
     * @return list<string>
     */
    public function getPodrobnosti(): array
    {
        return $this->podrobnosti;
    }

    public function getKlicPotvrzeni(): string
    {
        return $this->klicPotvrzeni;
    }
}
