<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

use Gamecon\Uzivatel\Pohlavi;

/**
 * Pro koho je na aktivitě volné místo.
 *
 * Hodnoty backingu odpovídají historickým řetězcům z {@see Aktivita::volnoPro()}
 * a přímo pohlaví ({@see Pohlavi::ZENA_KOD} = 'f', {@see Pohlavi::MUZ_KOD} = 'm'),
 * aby šly srovnávat s Uzivatel::pohlavi().
 */
enum VolnoProEnum: string
{
    /** Volno pro všechny (aktivita bez omezení nebo zbývají univerzální místa). */
    case PRO_VSECHNY = 'u';

    /** Beznadějně plno – volné místo není pro nikoho. */
    case PLNO = 'x';

    /** Volno už jen pro ženy (muži vyžrali všechna univerzální i mužská místa). */
    case JEN_ZENY = 'f';

    /** Volno už jen pro muže (ženy vyžraly všechna univerzální i ženská místa). */
    case JEN_MUZI = 'm';

    /**
     * Má uživatel daného pohlaví na aktivitě volné místo?
     */
    public function proPohlaviJeVolno(string $kodPohlavi): bool
    {
        return $this === self::PRO_VSECHNY
            || $this->value === $kodPohlavi;
    }

    /**
     * Kódy pohlaví, pro která je na aktivitě volné místo (u PRO_VSECHNY obě, u PLNO žádné).
     *
     * @return list<string>
     */
    public function pohlaviSVolnymMistem(): array
    {
        return match ($this) {
            self::PRO_VSECHNY => [Pohlavi::MUZ_KOD, Pohlavi::ZENA_KOD],
            self::JEN_MUZI    => [Pohlavi::MUZ_KOD],
            self::JEN_ZENY    => [Pohlavi::ZENA_KOD],
            self::PLNO        => [],
        };
    }
}
