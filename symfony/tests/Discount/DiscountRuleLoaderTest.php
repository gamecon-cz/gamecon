<?php

declare(strict_types=1);

namespace App\Tests\Discount;

use App\Discount\DiscountRuleLoader;
use App\Discount\DiscountScope;
use App\Enum\ProductTagCode;
use PHPUnit\Framework\TestCase;

/**
 * The loader takes its fetcher as a callable, so these run without a database and
 * assert on the SQL as much as on the result — the queries are the part that can go
 * quietly wrong.
 */
class DiscountRuleLoaderTest extends TestCase
{
    public function testRulesAreParsedFromTheStoredJson(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            self::row('kostka_zdarma', '{"scope":"code_contains","effect":"free","codeFragment":"kostka","maxQuantity":1}'),
        ]);

        $rules = $loader->rulesForYear(2026);

        $this->assertCount(1, $rules);
        $this->assertSame('kostka_zdarma', $rules[0]->code);
        $this->assertSame(1003, $rules[0]->requiredRight, 'Právo se přetypuje na int');
        $this->assertSame(DiscountScope::CODE_CONTAINS, $rules[0]->parameters->scope);
        $this->assertSame(1, $rules[0]->parameters->maxQuantity);
    }

    public function testRulesAreAskedInOrderOfPriorityThenCode(): void
    {
        $captured = null;
        $loader = new DiscountRuleLoader(static function (string $sql) use (&$captured): array {
            $captured = $sql;

            return [];
        });

        $loader->rulesForYear(2025);

        $this->assertStringContainsString('ORDER BY priority, code', $captured);
    }

    public function testRulesOfThatYearAndOfEveryYearAreAsked(): void
    {
        $captured = null;
        $loader = new DiscountRuleLoader(static function (string $sql, array $params) use (&$captured): array {
            $captured = [$sql, $params];

            return [];
        });

        $loader->rulesForYear(2025);

        [$sql, $params] = $captured;
        $this->assertStringContainsString('year IN ($0, $1)', $sql);
        $this->assertSame([
            0 => 2025,
            1 => DiscountRuleLoader::ANY_YEAR,
        ], $params);
    }

    public function testRuleOfEveryYearAppliesInAnyYear(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            self::row('jidlo_zdarma', '{"scope":"tag","effect":"free","tag":"jidlo"}'),
        ]);

        $this->assertSame(['jidlo_zdarma'], array_map(static fn ($rule) => $rule->code, $loader->rulesForYear(2031)));
    }

    /**
     * The generic row of a rule that names a product has no code, so it matches nothing, until a year
     * gets a row of its own with the code.
     */
    public function testRuleOfTheYearReplacesTheGenericOneOfTheSameCode(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            self::row('jidlo_zdarma', '{"scope":"tag","effect":"free","tag":"jidlo"}'),
            self::row('kostka_zdarma', '{"scope":"product_code","effect":"free","codeFragment":"kostka","maxQuantity":1}'),
            self::row('kostka_zdarma', '{"scope":"product_code","effect":"free","codeFragment":"kostka","productCode":"kostka_2026_verne","maxQuantity":1}', year: 2026),
        ]);

        $rules = $loader->rulesForYear(2026);

        $this->assertSame(['jidlo_zdarma', 'kostka_zdarma'], array_map(static fn ($rule) => $rule->code, $rules), 'Jedno pravidlo na kód, v pořadí z databáze');
        $this->assertSame('kostka_2026_verne', $loader->namedItemCode(2026, 'kostka'));
    }

    public function testRuleOfAnotherYearIsNotUsed(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            self::row('kostka_zdarma', '{"scope":"product_code","effect":"free","codeFragment":"kostka","maxQuantity":1}'),
            self::row('kostka_zdarma', '{"scope":"product_code","effect":"free","codeFragment":"kostka","productCode":"kostka_2026_verne","maxQuantity":1}', year: 2026),
        ]);

        $this->assertNull($loader->namedItemCode(2027, 'kostka'), 'Loňský předmět se v novém ročníku nezdědí');
    }

    public function testInactiveRuleOfTheYearSwitchesTheGenericOneOffForThatYear(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            self::row('jidlo_zdarma', '{"scope":"tag","effect":"free","tag":"jidlo"}'),
            self::row('jidlo_zdarma', '{"scope":"tag","effect":"free","tag":"jidlo"}', year: 2026, active: 0),
        ]);

        $this->assertSame([], $loader->rulesForYear(2026));
        $this->assertCount(1, $loader->rulesForYear(2027));
    }

    public function testActiveRuleOfTheYearSwitchesTheInactiveGenericOneOnForThatYear(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            self::row('jidlo_zdarma', '{"scope":"tag","effect":"free","tag":"jidlo"}', active: 0),
            self::row('jidlo_zdarma', '{"scope":"tag","effect":"free","tag":"jidlo"}', year: 2026),
        ]);

        $this->assertCount(1, $loader->rulesForYear(2026));
        $this->assertSame([], $loader->rulesForYear(2027));
    }

    public function testInactiveGenericRuleIsNotUsed(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            self::row('jidlo_zdarma', '{"scope":"tag","effect":"free","tag":"jidlo"}', active: 0),
        ]);

        $this->assertSame([], $loader->rulesForYear(2026));
    }

    public function testRightsComeFromTheBaseTableNotTheView(): void
    {
        $captured = null;
        $loader = new DiscountRuleLoader(static function (string $sql, array $params) use (&$captured): array {
            $captured = [$sql, $params];

            return [
                [
                    'id_prava' => '1003',
                ],
                [
                    'id_prava' => '1012',
                ],
            ];
        });

        $rights = $loader->rightsOfUser(335, 2026);

        [$sql, $params] = $captured;
        // platne_role_uzivatelu is a view defined against the `gamecon` database by
        // name, so under the test database it reports the developer's roles. Reading
        // uzivatele_role directly is what makes this correct under test.
        $this->assertStringContainsString('uzivatele_role', $sql);
        $this->assertStringNotContainsString('platne_role_uzivatelu', $sql);
        // Dropping the year predicate would let a role from a past ročník keep granting
        // its rights — last year's organizer with free meals forever.
        $this->assertStringContainsString('rocnik_role', $sql);
        $this->assertStringContainsString("typ_role = 'ucast'", $sql);
        $this->assertSame([
            0 => 335,
            1 => 2026,
        ], $params);
        $this->assertSame([1003, 1012], $rights);
    }

    public function testMalformedParametersAreRefused(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            self::row('rozbite', '{"scope":"tag","effect":"free","tag":"jidla"}'),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Neznámý tag "jidla"');

        $loader->rulesForYear(2026);
    }

    public function testTagSurvivesLoadingAsTheEnum(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            self::row('jidlo_zdarma', '{"scope":"tag","effect":"free","tag":"jidlo"}'),
        ]);

        $this->assertSame(ProductTagCode::JIDLO, $loader->rulesForYear(2026)[0]->parameters->tag);
    }

    public function testRulesOfAYearAreReadOnce(): void
    {
        $dotazy = [];
        $loader = new DiscountRuleLoader(static function (string $sql, array $params) use (&$dotazy): array {
            $dotazy[] = $params[0];

            return [];
        });

        $loader->rulesForYear(2026);
        $loader->rulesForYear(2026);
        $loader->namedItemCode(2026, 'kostka');
        $loader->rulesForYear(2025);

        $this->assertSame([2026, 2025], $dotazy);
    }

    /**
     * A role change reprices the cart within the request that made it, so rights cached
     * from before the change would price it with the old ones.
     */
    public function testRightsAreReadAgainEveryTime(): void
    {
        $dotazu = 0;
        $loader = new DiscountRuleLoader(static function () use (&$dotazu): array {
            ++$dotazu;

            return [];
        });

        $loader->rightsOfUser(335, 2026);
        $loader->rightsOfUser(335, 2026);

        $this->assertSame(2, $dotazu);
    }

    public function testResetForgetsTheRules(): void
    {
        $dotazu = 0;
        $loader = new DiscountRuleLoader(static function () use (&$dotazu): array {
            ++$dotazu;

            return [];
        });

        $loader->rulesForYear(2026);
        $loader->reset();
        $loader->rulesForYear(2026);

        $this->assertSame(2, $dotazu);
    }

    /**
     * A row as the database returns it: every value a string.
     *
     * @return array<string, string>
     */
    private static function row(string $code, string $parameters, int $year = DiscountRuleLoader::ANY_YEAR, int $active = 1): array
    {
        return [
            'code'           => $code,
            'name'           => $code,
            'required_right' => '1003',
            'parameters'     => $parameters,
            'year'           => (string) $year,
            'active'         => (string) $active,
        ];
    }
}
