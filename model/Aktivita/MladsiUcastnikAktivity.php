<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

/**
 * Přihlášený účastník, kterému v den aktivity ještě nebude její minimální věk, viz MladsiUcastniciAktivit.
 */
readonly class MladsiUcastnikAktivity
{
    public function __construct(
        public Aktivita $aktivita,
        public \Uzivatel $ucastnik,
        /**
         * věk účastníka v den začátku aktivity
         */
        public int $vek,
        /**
         * minimální věk aktivity ze štítku „Věk: 15+“
         */
        public int $minimalniVek,
    ) {
    }
}
