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
            [
                'code'           => 'kostka_zdarma',
                'name'           => 'Kostka zdarma',
                'required_right' => '1003',
                'parameters'     => '{"scope":"code_contains","effect":"free","codeFragment":"kostka","maxQuantity":1}',
            ],
        ]);

        $rules = $loader->rulesForYear(2026);

        $this->assertCount(1, $rules);
        $this->assertSame('kostka_zdarma', $rules[0]->code);
        $this->assertSame(1003, $rules[0]->requiredRight, 'Právo se přetypuje na int');
        $this->assertSame(DiscountScope::CODE_CONTAINS, $rules[0]->parameters->scope);
        $this->assertSame(1, $rules[0]->parameters->maxQuantity);
    }

    public function testOnlyActiveRulesOfThatYearAreAsked(): void
    {
        $captured = null;
        $loader = new DiscountRuleLoader(static function (string $sql, array $params) use (&$captured): array {
            $captured = [$sql, $params];

            return [];
        });

        $loader->rulesForYear(2025);

        [$sql, $params] = $captured;
        $this->assertStringContainsString('active = 1', $sql);
        $this->assertStringContainsString('year = $0', $sql);
        $this->assertSame([
            0 => 2025,
        ], $params);
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

        $rights = $loader->rightsOfUser(335);

        [$sql, $params] = $captured;
        // platne_role_uzivatelu is a view defined against the `gamecon` database by
        // name, so under the test database it reports the developer's roles. Reading
        // uzivatele_role directly is what makes this correct under test.
        $this->assertStringContainsString('uzivatele_role', $sql);
        $this->assertStringNotContainsString('platne_role_uzivatelu', $sql);
        $this->assertSame([
            0 => 335,
        ], $params);
        $this->assertSame([1003, 1012], $rights);
    }

    public function testMalformedParametersAreRefused(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            [
                'code'           => 'rozbite',
                'name'           => 'Rozbité',
                'required_right' => '1003',
                'parameters'     => '{"scope":"tag","effect":"free","tag":"jidla"}',
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Neznámý tag "jidla"');

        $loader->rulesForYear(2026);
    }

    public function testTagSurvivesLoadingAsTheEnum(): void
    {
        $loader = new DiscountRuleLoader(static fn (): array => [
            [
                'code'           => 'jidlo_zdarma',
                'name'           => 'Jídlo zdarma',
                'required_right' => '1005',
                'parameters'     => '{"scope":"tag","effect":"free","tag":"jidlo"}',
            ],
        ]);

        $this->assertSame(ProductTagCode::JIDLO, $loader->rulesForYear(2026)[0]->parameters->tag);
    }
}
