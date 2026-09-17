<?php

declare(strict_types=1);

namespace App\Discount;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Hodnoty, na které se pravidla slev odkazují přes `amountSetting` / `thresholdSetting`.
 *
 * Sdílí je cenový žebřík i výpočet spotřebované kvóty — obojí musí vidět tytéž částky,
 * jinak by se rozešlo, co sleva stála, s tím, kolik nároku spotřebovala.
 */
final readonly class DiscountSettingValues
{
    /**
     * @return array<string, float>
     */
    public static function from(?SystemoveNastaveni $systemoveNastaveni = null): array
    {
        $settings = $systemoveNastaveni ?? SystemoveNastaveni::zGlobals();

        return [
            DiscountSetting::OrganizerMealDiscount->value   => (float) $settings->slevaOrguNaJidloCastka(),
            DiscountSetting::FreeShirtBonusThreshold->value => (float) $settings->modreTrickoZdarmaOd(),
        ];
    }
}
