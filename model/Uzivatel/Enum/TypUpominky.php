<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Enum;

/**
 * Typ upomínky dlužníků
 */
enum TypUpominky: string
{
    case TYDEN = 'tyden';
    case MESIC = 'mesic';
    case RUCNI = 'rucni';

    /**
     * Ruční rozeslání nemá vlastní text - použije naléhavější měsíční variantu,
     * protože se pouští až dlouho po GC. V logu se od automatiky liší typem
     * a odesílatelem, takže o zdroji upomínky se tím nic neztrácí.
     */
    public function textovaVarianta(): self
    {
        return $this === self::RUCNI
            ? self::MESIC
            : $this;
    }
}
