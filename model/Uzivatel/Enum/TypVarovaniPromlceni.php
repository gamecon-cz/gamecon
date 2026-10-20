<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Enum;

use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;

/**
 * Typ varovného e-mailu o promlčení zůstatku
 */
enum TypVarovaniPromlceni: string
{
    case MESIC = 'mesic';
    case TYDEN = 'tyden';

    public function getKodSablonyVarovani(): KodEmailoveSablonyEnum
    {
        return match ($this) {
            self::MESIC => KodEmailoveSablonyEnum::PROMLCENI_VAROVANI_MESIC,
            self::TYDEN => KodEmailoveSablonyEnum::PROMLCENI_VAROVANI_TYDEN,
        };
    }

    public function getKodSablonyInfoCfo(): KodEmailoveSablonyEnum
    {
        return match ($this) {
            self::MESIC => KodEmailoveSablonyEnum::CFO_PROMLCENI_VAROVANI_MESIC_ODESLANO,
            self::TYDEN => KodEmailoveSablonyEnum::CFO_PROMLCENI_VAROVANI_TYDEN_ODESLANO,
        };
    }
}
