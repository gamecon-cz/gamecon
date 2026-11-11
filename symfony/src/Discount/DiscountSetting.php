<?php

declare(strict_types=1);

namespace App\Discount;

use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;

/**
 * The system settings a discount rule may read a number from.
 *
 * Rules keep their numbers in systemove_nastaveni rather than copying them, so that
 * changing a setting changes the rule instead of moving the two apart. That means the
 * stored JSON has to name the setting somehow, and a bare setting key would be a
 * string nothing checks: rename the constant and the rule silently stops resolving.
 *
 * So the JSON holds one of these case names instead, and this enum is the only place
 * that knows which setting each one means. A rename of a settings key is then a change
 * here, where the compiler can see it, and the stored data does not move at all.
 *
 * The case names are camelCase on purpose — deliberately unlike the SCREAMING_CASE
 * setting keys, so nobody reads them as needing to match.
 */
enum DiscountSetting: string
{
    case FreeShirtBonusThreshold = 'freeShirtBonusThreshold';
    case OrganizerMealDiscount = 'organizerMealDiscount';

    /**
     * @return SystemoveNastaveniKlice::* the settings key this stands for
     */
    public function settingKey(): string
    {
        return match ($this) {
            self::FreeShirtBonusThreshold => SystemoveNastaveniKlice::MODRE_TRICKO_ZDARMA_OD,
            self::OrganizerMealDiscount   => SystemoveNastaveniKlice::SLEVA_ORGU_NA_JIDLO_CASTKA,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::FreeShirtBonusThreshold => 'Bonus, od kterého je tričko zdarma',
            self::OrganizerMealDiscount   => 'Sleva orga na jídlo',
        };
    }
}
