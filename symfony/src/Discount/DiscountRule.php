<?php

declare(strict_types=1);

namespace App\Discount;

/**
 * One row of discount_rule, with its JSON already parsed.
 *
 * Deliberately not a Doctrine entity: the calculator is pure, so it can be tested
 * without a database and called from legacy Cenik, which has no entity manager.
 */
final readonly class DiscountRule
{
    public function __construct(
        public string $code,
        public string $name,
        /**
         * id_prava the buyer must hold — see Pravo.
         */
        public int $requiredRight,
        public DiscountParameters $parameters,
    ) {
    }

    /**
     * @param array<string, mixed> $row as stored in discount_rule
     */
    public static function fromRow(array $row): self
    {
        $parameters = json_decode((string) $row['parameters'], true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($parameters)) {
            throw new \InvalidArgumentException(sprintf('Pravidlo "%s" nemá parametry jako objekt', (string) $row['code']));
        }

        return new self(
            code: (string) $row['code'],
            name: (string) $row['name'],
            requiredRight: (int) $row['required_right'],
            parameters: DiscountParameters::fromArray($parameters),
        );
    }
}
