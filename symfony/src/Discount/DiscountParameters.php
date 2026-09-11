<?php

declare(strict_types=1);

namespace App\Discount;

use App\Enum\ProductTagCode;

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
         * Product tag, for the TAG scopes.
         */
        public ?ProductTagCode $tag,
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
         * Setting to read the discount amount from, instead of freezing today's number
         * into the rule.
         */
        public ?DiscountSetting $amountSetting,
        /**
         * Setting whose value the user's bonus must reach. Resolved at calculation
         * time, so an admin changing the setting changes the rule; the resolved number
         * goes into the snapshot on the purchase, so history stays truthful when the
         * setting later changes.
         */
        public ?DiscountSetting $thresholdSetting,
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
        foreach ($required as $alternatives) {
            $present = array_filter($alternatives, static fn (string $name): bool => isset($parameters[$name]));
            if ($present === []) {
                throw new \InvalidArgumentException(sprintf('Sleva typu %s/%s vyžaduje parametr "%s"', $scope->value, $effect->value, implode('" nebo "', $alternatives)));
            }
        }

        $known = [...array_merge(...$required), 'scope', 'effect', 'maxQuantity', 'thresholdSetting'];
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
            tag: isset($parameters['tag'])
                ? (ProductTagCode::tryFrom((string) $parameters['tag'])
                    ?? throw new \InvalidArgumentException(sprintf('Neznámý tag "%s". Povolené: %s', (string) $parameters['tag'], implode(', ', array_column(ProductTagCode::cases(), 'value')))))
                : null,
            day: $day,
            amount: isset($parameters['amount'])
                ? (float) $parameters['amount']
                : (isset($parameters['percent']) ? (float) $parameters['percent'] : null),
            amountSetting: isset($parameters['amountSetting'])
                ? self::setting((string) $parameters['amountSetting'], 'amountSetting')
                : null,
            maxQuantity: isset($parameters['maxQuantity']) ? (int) $parameters['maxQuantity'] : null,
            thresholdSetting: isset($parameters['thresholdSetting'])
                ? self::setting((string) $parameters['thresholdSetting'], 'thresholdSetting')
                : null,
        );
    }

    private static function setting(string $value, string $parameterName): DiscountSetting
    {
        return DiscountSetting::tryFrom($value)
            ?? throw new \InvalidArgumentException(sprintf('Neznámé nastavení "%s" v parametru %s. Povolené: %s', $value, $parameterName, implode(', ', array_column(DiscountSetting::cases(), 'value'))));
    }

    /**
     * Parameter names the admin form may offer as inputs, everything else being shown
     * read-only. maxQuantity is here regardless of scope and effect — how many items a
     * rule covers is policy, not shape.
     *
     * @return string[]
     */
    public function editableParameters(): array
    {
        return [
            ...$this->scope->editableParameters(),
            ...$this->effect->editableParameters(),
            'maxQuantity',
        ];
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
            'tag'                                                            => $this->tag?->value,
            'day'                                                            => $this->day,
            $this->effect === DiscountEffect::PERCENT ? 'percent' : 'amount' => $this->amount,
            'amountSetting'                                                  => $this->amountSetting?->value,
            'maxQuantity'                                                    => $this->maxQuantity,
            'thresholdSetting'                                               => $this->thresholdSetting?->value,
        ], static fn ($value): bool => $value !== null);
    }
}
