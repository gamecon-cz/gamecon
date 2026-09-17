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
final readonly class HodnotyNastaveniSlev
{
    /**
     * @return array<string, float>
     */
    public static function z(?SystemoveNastaveni $systemoveNastaveni = null): array
    {
        $nastaveni = $systemoveNastaveni ?? SystemoveNastaveni::zGlobals();

        return [
            DiscountSetting::OrganizerMealDiscount->value   => (float) $nastaveni->slevaOrguNaJidloCastka(),
            DiscountSetting::FreeShirtBonusThreshold->value => (float) $nastaveni->modreTrickoZdarmaOd(),
        ];
    }
}
