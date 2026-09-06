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
            // Priority, not code: which rule wins when two match the same item is an
            // explicit decision, not whatever the alphabet happens to produce.
            'SELECT code, name, required_right, parameters
             FROM discount_rule
             WHERE year = $0 AND active = 1
             ORDER BY priority, code',
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
     * @return int[] id_prava the user holds for that year
     */
    public function rightsOfUser(int $userId, int $year): array
    {
        // Reads the base tables rather than the platne_role_uzivatelu view: that view
        // names the `gamecon` database explicitly, so under the test database it would
        // report the developer's roles instead of the fixtures'.
        //
        // The year predicate has to be repeated here, because filtering by year is the
        // whole point of that view. A role scoped to a past ročník must not still grant
        // its rights, or last year's organizer keeps their free dice and free meals
        // forever. Only rocnik_role = -1 (year-independent) and typ_role = 'ucast'
        // outlive their year.
        $rows = ($this->fetchAll)(
            "SELECT DISTINCT prava_role.id_prava
             FROM uzivatele_role
             JOIN role_seznam ON role_seznam.id_role = uzivatele_role.id_role
             JOIN prava_role ON prava_role.id_role = uzivatele_role.id_role
             WHERE uzivatele_role.id_uzivatele = \$0
               AND (role_seznam.rocnik_role IN (\$1, -1) OR role_seznam.typ_role = 'ucast')",
            [
                0 => $userId,
                1 => $year,
            ],
        );

        return array_map(static fn (array $row): int => (int) $row['id_prava'], $rows);
    }
}
