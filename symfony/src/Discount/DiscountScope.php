<?php

declare(strict_types=1);

namespace App\Discount;

/**
 * Which purchases a rule can apply to.
 *
 * The four shapes come from the rules that exist, not from a guess at what might be
 * useful: dice and badges are matched by a fragment of their code, t-shirts by their
 * tag, a free night by tag plus the day, and TAG_CHEAPEST covers the bonus t-shirt,
 * which applies once to whichever matching item is cheapest rather than to each.
 */
enum DiscountScope: string
{
    case CODE_CONTAINS = 'code_contains';
    case TAG = 'tag';
    case TAG_AND_DAY = 'tag_and_day';
    case TAG_CHEAPEST = 'tag_cheapest';

    public function label(): string
    {
        return match ($this) {
            self::CODE_CONTAINS => 'Předměty, jejichž kód obsahuje',
            self::TAG           => 'Předměty s tagem',
            self::TAG_AND_DAY   => 'Předměty s tagem, jen pro daný den',
            self::TAG_CHEAPEST  => 'Nejlevnější předmět s tagem v košíku',
        };
    }

    /**
     * Names of the parameters this scope requires, so validation and the admin form
     * can both be driven from one place.
     *
     * Each entry is a set of alternatives, of which at least one must be present.
     *
     * @return string[][]
     */
    public function requiredParameters(): array
    {
        return match ($this) {
            self::CODE_CONTAINS => [['codeFragment']],
            self::TAG, self::TAG_CHEAPEST => [['tag']],
            self::TAG_AND_DAY => [['tag'], ['day']],
        };
    }

    /**
     * Does the entitlement get used up, or does it apply to every matching purchase?
     *
     * A free night applies to every Wednesday night bought; a free dice applies once.
     * Getting this backwards is the difference between one free bed and five.
     */
    public function isConsumable(): bool
    {
        return $this !== self::TAG_AND_DAY;
    }
}
