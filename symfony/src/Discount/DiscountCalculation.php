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
     * Cenový žebřík pro jednu položku: kolikátý kus stojí kolik.
     *
     * Nároky se vyčerpávají v pořadí priorit, takže cena není jedno číslo — kdo má dvě
     * trička zdarma a k tomu jedno navíc, platí 0, 0, 0 a pak plnou cenu. Frontend takhle
     * dostane celou posloupnost dopředu a po přidání do košíku nemusí čekat na server.
     *
     * Žebřík platí pro JEDEN produkt. Nárok sdílený přes víc produktů (jedna kostka
     * zdarma, ale kostek je v nabídce sedm) se tím pádem ukáže u každé z nich — dokud
     * zákazník žádnou nemá, je to u každé pravda, ale zdarma dostane jen první koupenou.
     * Přesné číslo řekne až košík, který vidí všechny položky najednou.
     *
     * @param int $jizKoupeno kolik kusů už zákazník letos má — jejich nároky jsou pryč
     *
     * @return PriceStep[] vždy aspoň jeden stupeň, seřazené od prvního kusu
     */
    public function priceSteps(DiscountableItem $item, int $jizKoupeno = 0): array
    {
        $remaining = $this->initialQuantities();

        // Nároky spotřebované tím, co zákazník už má. Odbýt se to musí zvlášť, ne uvnitř
        // cyklu pod stropem — jinak by velký nárok a hodně koupených kusů strop vyčerpaly
        // dřív, než se vydá první stupeň, a vyšlo by z toho „plná cena".
        for ($kus = 1; $kus <= $jizKoupeno; ++$kus) {
            $rule = $this->firstMatching($item, $remaining);
            if ($rule === null || ! isset($remaining[$rule->code])) {
                break;
            }
            --$remaining[$rule->code];
        }

        $steps = [];
        $poradi = 1;

        // Strop omezuje počet STUPŇŮ, ne kusů: každý průchod buď stupeň přidá, nebo
        // zvedne pořadí u stávajícího, takže víc než tolik různých cen nevznikne.
        $strop = 50;

        while (count($steps) < $strop) {
            $rule = $this->firstMatching($item, $remaining);

            if ($rule === null) {
                $steps[] = new PriceStep($poradi, $item->price, 0.0, null, null);

                break;
            }

            $amount = $this->amountFor($rule);
            $discount = $amount === null ? 0.0 : $rule->parameters->effect->discountFrom($item->price, $amount);

            if (isset($remaining[$rule->code])) {
                --$remaining[$rule->code];
            }

            $predchozi = $steps === [] ? null : $steps[count($steps) - 1];
            if ($predchozi !== null && $predchozi->ruleCode === $rule->code) {
                ++$poradi;

                continue;
            }

            $steps[] = new PriceStep($poradi, $item->price - $discount, $discount, $rule->code, $rule->name);
            ++$poradi;

            // Neomezené pravidlo se nevyčerpá, takže platí pro všechny další kusy —
            // další stupeň už nepřijde a cyklus by se jinak točil donekonečna.
            if (! isset($remaining[$rule->code])) {
                break;
            }
        }

        return $steps;
    }

    /**
     * @param array<string, int> $remaining
     */
    private function firstMatching(DiscountableItem $item, array $remaining): ?DiscountRule
    {
        foreach ($this->rules as $rule) {
            if (! $this->isEligible($rule) || ! $item->matches($rule->parameters)) {
                continue;
            }
            // TAG_CHEAPEST dá nárok nejlevnější položce v košíku, jenže žebřík počítá
            // jeden produkt a žádný košík nevidí. Slíbil by nulu i u dražšího trička,
            // které se nakonec zaplatí — radši o pravidle mlčet, než lhát o ceně.
            if ($rule->parameters->scope === DiscountScope::TAG_CHEAPEST) {
                continue;
            }
            if (isset($remaining[$rule->code]) && $remaining[$rule->code] <= 0) {
                continue;
            }
            $amount = $this->amountFor($rule);
            if ($amount === null) {
                continue;
            }
            // Pravidlo, které nakonec nic neubere (nastavená částka 0, nebo „zdarma" na
            // produktu za nulu), není sleva — apply() ho zahodí na témže místě. Kdyby
            // sem prošlo, neomezené by se navíc vybíralo donekonečna.
            if ($rule->parameters->effect->discountFrom($item->price, $amount) <= 0.0) {
                continue;
            }

            return $rule;
        }

        return null;
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
