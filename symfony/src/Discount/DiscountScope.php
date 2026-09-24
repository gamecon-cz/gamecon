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
     * Parameters this scope cannot do without, for validation.
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
     * Of those, the ones an admin may change.
     *
     * Deliberately narrower than requiredParameters(): the numbers are policy an admin
     * tunes, the rest is what the rule *is*. Switching a rule's tag from jidlo to
     * tricko does not adjust "free meals", it turns it into free shirts while the name
     * and description still say meals — a different rule wearing the old one's label.
     * Same for the scope and effect themselves.
     *
     * The admin form shows everything, but only these as inputs.
     *
     * @return string[]
     */
    public function editableParameters(): array
    {
        return match ($this) {
            self::CODE_CONTAINS, self::TAG, self::TAG_CHEAPEST => [],
            self::TAG_AND_DAY => ['day'],
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
