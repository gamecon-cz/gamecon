<?php

declare(strict_types=1);

namespace App\Discount;

/**
 * What a rule did to one item, and enough of the rule to explain it later.
 *
 * The snapshot is the point: legacy records nothing at all — every shop_nakupy row
 * holds the catalogue price and no discount, because Cenik recomputes it on each read.
 * Change a rule or a role and history silently rewrites itself. Here the rule as
 * applied, including any threshold as it resolved at that moment, is stored with the
 * purchase, so an audit years later reads what actually happened.
 */
final readonly class AppliedDiscount
{
    public function __construct(
        public int|string $itemKey,
        public string $ruleCode,
        public string $ruleName,
        public float $originalPrice,
        public float $discountAmount,
        public float $finalPrice,
        /**
         * @var array<string, mixed> the rule as applied, for shop_nakupy.discount_snapshot
         */
        public array $snapshot,
    ) {
    }

    /**
     * @param array<string, mixed> $resolved values resolved at calculation time, e.g. the threshold
     */
    public static function create(
        DiscountableItem $item,
        DiscountRule $rule,
        float $discountAmount,
        array $resolved = [],
    ): self {
        return new self(
            itemKey: $item->key,
            ruleCode: $rule->code,
            ruleName: $rule->name,
            originalPrice: $item->price,
            discountAmount: $discountAmount,
            finalPrice: round($item->price - $discountAmount, 2),
            snapshot: [
                'ruleCode'       => $rule->code,
                'ruleName'       => $rule->name,
                'requiredRight'  => $rule->requiredRight,
                'parameters'     => $rule->parameters->toArray(),
                'resolved'       => $resolved,
                'originalPrice'  => $item->price,
                'discountAmount' => $discountAmount,
            ],
        );
    }
}
