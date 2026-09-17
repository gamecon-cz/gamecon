<?php

declare(strict_types=1);

namespace App\Discount;

/**
 * Kolik z kvóty každého pravidla už zákazník letos utratil.
 *
 * Nárok s omezeným počtem („jedna kostka zdarma") je jeden na všechny produkty, které
 * pravidlo pokrývá — a kostek je v nabídce 45. Žebřík počítá jeden produkt, takže sám
 * o tom neví; bez tohohle součtu by slíbil nulu u každé z nich a košík by podle toho
 * i účtoval, takže by zákazník dostal 45 kostek zdarma místo jedné.
 *
 * Spotřeba se NEPOČÍTÁ znovu — pouští se `apply()` na dosavadní nákupy a sečte se, co
 * z toho vyšlo. Vlastní kopie výběru pravidel by se musela shodovat ve všem: v právech,
 * v prahu bonusu, v přeskočení TAG_CHEAPEST i v zahození nulové slevy. Jakmile by se
 * rozešla, spotřebuje kvótu jiné pravidlo, než které slevu doopravdy zaplatilo, a nárok
 * se rozdá podruhé.
 */
final readonly class SpotrebovanaKvota
{
    /**
     * @param DiscountRule[]       $rules
     * @param DiscountableItem[]   $jizKoupene co zákazník letos koupil
     * @param int[]                $prava      id_prava, která kupující drží
     * @param array<string, float> $nastaveni  hodnoty DiscountSetting
     *
     * @return array<string, int> kód pravidla → kolik kusů kvóty padlo
     */
    public static function zNakupu(
        array $rules,
        array $jizKoupene,
        array $prava = [],
        array $nastaveni = [],
        float $earnedBonus = 0.0,
    ): array {
        if ($jizKoupene === []) {
            return [];
        }

        $spotrebovano = [];
        foreach ((new DiscountCalculation($rules, $prava, $nastaveni, $earnedBonus))->apply($jizKoupene) as $sleva) {
            $spotrebovano[$sleva->ruleCode] = ($spotrebovano[$sleva->ruleCode] ?? 0) + 1;
        }

        // Pravidla bez omezeného počtu se nevyčerpávají, takže do kvóty nepatří — nemají
        // co ubrat a žebřík je nabídne na každý kus znovu.
        $omezena = [];
        foreach ($rules as $rule) {
            if ($rule->parameters->maxQuantity !== null && $rule->parameters->scope->isConsumable()) {
                $omezena[$rule->code] = true;
            }
        }

        return array_intersect_key($spotrebovano, $omezena);
    }
}
