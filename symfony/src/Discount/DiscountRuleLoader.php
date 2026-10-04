<?php

declare(strict_types=1);

namespace App\Discount;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Reads the rules, the buyer's rights and the settings a rule refers to.
 *
 * Plain SQL rather than Doctrine, because legacy Cenik has no entity manager and this
 * has to serve both sides. Once the storefront no longer goes through Cenik this can
 * become a repository like everything else.
 *
 * Rules are read once per year and request: the price grid asks for them per product, and
 * only an admin import changes them. Rights are not cached — see rightsOfUser().
 */
final class DiscountRuleLoader implements ResetInterface
{
    /**
     * @var \Closure(string, array<int, mixed>): array<int, array<string, mixed>>
     */
    private readonly \Closure $fetchAll;

    /**
     * @var array<int, DiscountRule[]>
     */
    private array $rulesByYear = [];

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
        return $this->rulesByYear[$year] ??= $this->loadRulesForYear($year);
    }

    public function reset(): void
    {
        $this->rulesByYear = [];
    }

    /**
     * @return DiscountRule[]
     */
    private function loadRulesForYear(int $year): array
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
     * The product a year's rule names as this year's item of that kind (kostka, placka).
     */
    public function namedItemCode(int $year, string $codeFragment): ?string
    {
        foreach ($this->rulesForYear($year) as $rule) {
            if ($rule->parameters->scope === DiscountScope::PRODUCT_CODE && $rule->parameters->codeFragment === $codeFragment) {
                return $rule->parameters->productCode;
            }
        }

        return null;
    }

    /**
     * @return int[] id_prava the user holds for that year
     */
    public function rightsOfUser(int $userId, int $year): array
    {
        // For callers that have no Uzivatel. Anything holding one should pass
        // Uzivatel::prava() instead — it is already loaded, cached, and filtered.
        // Never cached here: a role change reprices the cart within the same request.
        //
        // The year predicate is spelled out rather than delegated to the
        // platne_role_uzivatelu view, so that review can see it. Losing it is not a
        // subtle bug: a role scoped to a past ročník would keep granting its rights,
        // and last year's organizer would have free dice and free meals forever. Only
        // rocnik_role = -1 (year-independent) and typ_role = 'ucast' outlive a year.
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
