<?php

declare(strict_types=1);

namespace App\Discount;

/**
 * The varying part of a discount rule, stored as JSON on discount_rule.parameters.
 *
 * The rules have genuinely different shapes — a cart-wide "cheapest t-shirt" has no
 * product, a free night needs a day, a meal discount needs an amount in Kč — so as
 * columns most would be NULL most of the time and every new shape would be a
 * migration. Keeping them as JSON puts the shape where it can be validated properly,
 * here in PHP, while the columns an admin filters by stay real columns.
 *
 * Nothing outside this class should read the raw array: fromArray() is the only way
 * in, and it refuses anything the scope or effect does not describe.
 */
final readonly class DiscountParameters
{
    private function __construct(
        public DiscountScope $scope,
        public DiscountEffect $effect,
        /**
         * Fragment of kod_predmetu, for CODE_CONTAINS.
         */
        public ?string $codeFragment,
        /**
         * Product tag code, for the TAG scopes.
         */
        public ?string $tag,
        /**
         * Accommodation day 0–4 (Wed–Sun), for TAG_AND_DAY.
         */
        public ?int $day,
        /**
         * Kč for FIXED_AMOUNT, percent for PERCENT.
         */
        public ?float $amount,
        /**
         * How many purchases the rule covers; null means every match.
         */
        public ?int $maxQuantity,
        /**
         * System setting whose value the user's bonus must reach, e.g.
         * MODRE_TRICKO_ZDARMA_OD. Resolved at calculation time so an admin changing
         * the setting changes the rule, and the resolved number is recorded in the
         * snapshot on the purchase.
         */
        public ?string $thresholdSetting,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws \InvalidArgumentException when the shape does not match the scope and effect
     */
    public static function fromArray(array $parameters): self
    {
        $scope = DiscountScope::tryFrom((string) ($parameters['scope'] ?? ''))
            ?? throw new \InvalidArgumentException(sprintf('Neznámý rozsah slevy "%s"', (string) ($parameters['scope'] ?? '')));
        $effect = DiscountEffect::tryFrom((string) ($parameters['effect'] ?? ''))
            ?? throw new \InvalidArgumentException(sprintf('Neznámý efekt slevy "%s"', (string) ($parameters['effect'] ?? '')));

        $required = [...$scope->requiredParameters(), ...$effect->requiredParameters()];
        foreach ($required as $name) {
            if (! isset($parameters[$name])) {
                throw new \InvalidArgumentException(sprintf('Sleva typu %s/%s vyžaduje parametr "%s"', $scope->value, $effect->value, $name));
            }
        }

        $known = [...$required, 'scope', 'effect', 'maxQuantity', 'thresholdSetting'];
        $unknown = array_diff(array_keys($parameters), $known);
        if ($unknown !== []) {
            // A typo in a parameter name would otherwise be silently ignored and the
            // rule would quietly behave as if the value had never been set.
            throw new \InvalidArgumentException(sprintf('Neznámé parametry slevy: %s', implode(', ', $unknown)));
        }

        $day = isset($parameters['day']) ? (int) $parameters['day'] : null;
        if ($day !== null && ($day < 0 || $day > 4)) {
            throw new \InvalidArgumentException(sprintf('Den ubytování musí být 0–4, dostal %d', $day));
        }

        return new self(
            scope: $scope,
            effect: $effect,
            codeFragment: isset($parameters['codeFragment']) ? (string) $parameters['codeFragment'] : null,
            tag: isset($parameters['tag']) ? (string) $parameters['tag'] : null,
            day: $day,
            amount: isset($parameters['amount'])
                ? (float) $parameters['amount']
                : (isset($parameters['percent']) ? (float) $parameters['percent'] : null),
            maxQuantity: isset($parameters['maxQuantity']) ? (int) $parameters['maxQuantity'] : null,
            thresholdSetting: isset($parameters['thresholdSetting']) ? (string) $parameters['thresholdSetting'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'scope'                                                          => $this->scope->value,
            'effect'                                                         => $this->effect->value,
            'codeFragment'                                                   => $this->codeFragment,
            'tag'                                                            => $this->tag,
            'day'                                                            => $this->day,
            $this->effect === DiscountEffect::PERCENT ? 'percent' : 'amount' => $this->amount,
            'maxQuantity'                                                    => $this->maxQuantity,
            'thresholdSetting'                                               => $this->thresholdSetting,
        ], static fn ($value): bool => $value !== null);
    }
}
