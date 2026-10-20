<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Enum;

use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;

/**
 * Typ upomínky dlužníků
 */
enum TypUpominky: string
{
    case TYDEN = 'tyden';
    case MESIC = 'mesic';
    case RUCNI = 'rucni';
    case VLASTNI = 'vlastni';

    /**
     * Ruční rozeslání nemá vlastní text - použije naléhavější měsíční variantu,
     * protože se pouští až dlouho po GC. V logu se od automatiky liší typem
     * a odesílatelem, takže o zdroji upomínky se tím nic neztrácí.
     */
    public function textovaVarianta(): self
    {
        return match ($this) {
            self::RUCNI, self::VLASTNI => self::MESIC,
            default                    => $this,
        };
    }

    public function maVlastniZneni(): bool
    {
        return $this === self::VLASTNI;
    }

    public function getKodSablonyInfoCfo(): KodEmailoveSablonyEnum
    {
        return match ($this) {
            self::TYDEN   => KodEmailoveSablonyEnum::CFO_UPOMINKY_TYDEN_ODESLANO,
            self::MESIC   => KodEmailoveSablonyEnum::CFO_UPOMINKY_MESIC_ODESLANO,
            self::RUCNI   => KodEmailoveSablonyEnum::CFO_UPOMINKY_RUCNI_ODESLANO,
            self::VLASTNI => KodEmailoveSablonyEnum::CFO_UPOMINKY_VLASTNI_ODESLANO,
        };
    }
}
