<?php

declare(strict_types=1);

namespace App\Discount;

/**
 * Reads the rules, the buyer's rights and the settings a rule refers to.
 *
 * Plain SQL rather than Doctrine, because legacy Cenik has no entity manager and this
 * has to serve both sides. Once the storefront no longer goes through Cenik this can
 * become a repository like everything else.
 */
final class DiscountRuleLoader
{
    /**
     * @var \Closure(string, array<int, mixed>): array<int, array<string, mixed>>
     */
    private readonly \Closure $fetchAll;

    /**
     * @param callable(string, array<int, mixed>): array<int, array<string, mixed>> $fetchAll
     *                                                                                        usually dbFetchAll; injected so the loader can be tested without a database
     */
    public function __construct(callable $fetchAll)
    {
        $this->fetchAll = $fetchAll(...);
    }

    /**
     * @return DiscountRule[]
     */
    public function rulesForYear(int $year): array
    {
        $rows = ($this->fetchAll)(
            'SELECT code, name, required_right, parameters
             FROM discount_rule
             WHERE year = $0 AND active = 1
             ORDER BY code',
            [
                0 => $year,
            ],
        );

        return array_map(
            static fn (array $row): DiscountRule => DiscountRule::fromRow($row),
            $rows,
        );
    }

    /**
     * @return int[] id_prava the user holds
     */
    public function rightsOfUser(int $userId): array
    {
        // uzivatele_role, not the platne_role_uzivatelu view: that view is defined
        // against the `gamecon` database by name, so under the test database it would
        // report the developer's roles instead of the fixtures'.
        $rows = ($this->fetchAll)(
            'SELECT DISTINCT prava_role.id_prava
             FROM uzivatele_role
             JOIN prava_role ON prava_role.id_role = uzivatele_role.id_role
             WHERE uzivatele_role.id_uzivatele = $0',
            [
                0 => $userId,
            ],
        );

        return array_map(static fn (array $row): int => (int) $row['id_prava'], $rows);
    }
}
