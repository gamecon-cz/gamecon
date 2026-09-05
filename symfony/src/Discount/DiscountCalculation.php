<?php

declare(strict_types=1);

namespace App\Discount;

/**
 * Applies discount rules to a whole cart.
 *
 * Cart-level rather than per-item, because three of the rule kinds cannot be decided
 * one item at a time: TAG_CHEAPEST needs to know which matching item is cheapest,
 * maxQuantity counters run across items, and legacy prices from the cheapest up so the
 * free-shirt entitlement lands on the cheapest shirt.
 *
 * Pure: no database, no entity manager, no Uzivatel. The caller supplies the rules, the
 * rights, and the earned bonus, so the same code serves the new cart and legacy Cenik.
 */
final readonly class DiscountCalculation
{
    /**
     * @param DiscountRule[]       $rules
     * @param int[]                $rights      id_prava the buyer holds
     * @param array<string, float> $settings    resolved DiscountSetting values, keyed by case value
     * @param float                $earnedBonus bonus for running activities, for threshold rules
     */
    public function __construct(
        private array $rules,
        private array $rights,
        private array $settings,
        private float $earnedBonus = 0.0,
    ) {
    }

    /**
     * @param DiscountableItem[] $items
     *
     * @return AppliedDiscount[] keyed by item key; items with no discount are absent
     */
    public function apply(array $items): array
    {
        // Cheapest first, matching Finance::zapoctiShop's ORDER BY: a single free-shirt
        // entitlement must land on the cheapest shirt, not on whichever came first.
        usort($items, static fn (DiscountableItem $a, DiscountableItem $b): int => $a->price <=> $b->price);

        $applied = [];
        $remaining = $this->initialQuantities();

        foreach ($items as $item) {
            foreach ($this->rules as $rule) {
                if (! $this->isEligible($rule)) {
                    continue;
                }
                if (! $item->matches($rule->parameters)) {
                    continue;
                }
                if (isset($remaining[$rule->code]) && $remaining[$rule->code] <= 0) {
                    continue;
                }

                $amount = $this->amountFor($rule);
                if ($amount === null) {
                    continue;
                }

                $discount = $rule->parameters->effect->discountFrom($item->price, $amount);
                if ($discount <= 0.0) {
                    continue;
                }

                $applied[$item->key] = AppliedDiscount::create($item, $rule, $discount, $this->resolvedFor($rule));

                if (isset($remaining[$rule->code])) {
                    --$remaining[$rule->code];
                }

                // One discount per item: the rules are entitlements, not stacking offers.
                break;
            }
        }

        return $applied;
    }

    /**
     * @return array<string, int> remaining uses per rule code; rules without a limit are absent
     */
    private function initialQuantities(): array
    {
        $quantities = [];
        foreach ($this->rules as $rule) {
            // An entitlement tied to a specific night is not consumed — it applies to
            // every night bought for that day, unlike a free dice.
            if ($rule->parameters->maxQuantity !== null && $rule->parameters->scope->isConsumable()) {
                $quantities[$rule->code] = $rule->parameters->maxQuantity;
            }
        }

        return $quantities;
    }

    private function isEligible(DiscountRule $rule): bool
    {
        if (! in_array($rule->requiredRight, $this->rights, true)) {
            return false;
        }

        $threshold = $rule->parameters->thresholdSetting;

        return $threshold === null || $this->earnedBonus >= $this->settingValue($threshold);
    }

    /**
     * @return float|null the effect's parameter, or null when the rule cannot be priced
     */
    private function amountFor(DiscountRule $rule): ?float
    {
        if ($rule->parameters->effect === DiscountEffect::FREE) {
            return 0.0;
        }
        if ($rule->parameters->amountSetting !== null) {
            return $this->settingValue($rule->parameters->amountSetting);
        }

        return $rule->parameters->amount;
    }

    /**
     * @return array<string, mixed> the values a rule resolved to, recorded in the snapshot
     */
    private function resolvedFor(DiscountRule $rule): array
    {
        $resolved = [];
        if ($rule->parameters->thresholdSetting !== null) {
            $resolved['threshold'] = $this->settingValue($rule->parameters->thresholdSetting);
            $resolved['earnedBonus'] = $this->earnedBonus;
        }
        if ($rule->parameters->amountSetting !== null) {
            $resolved['amount'] = $this->settingValue($rule->parameters->amountSetting);
        }

        return $resolved;
    }

    private function settingValue(DiscountSetting $setting): float
    {
        // A rule naming a setting the caller did not supply would otherwise be treated
        // as a zero threshold or a zero discount — silently wrong in both directions.
        return $this->settings[$setting->value]
            ?? throw new \InvalidArgumentException(sprintf('Chybí hodnota nastavení "%s" (%s)', $setting->value, $setting->settingKey()));
    }
}
