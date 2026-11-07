<?php

declare(strict_types=1);

namespace App\Tests\Discount;

use App\Discount\DiscountableItem;
use App\Discount\DiscountCalculation;
use App\Discount\DiscountParameters;
use App\Discount\DiscountRule;
use App\Enum\ProductTagCode;
use PHPUnit\Framework\TestCase;

/**
 * Mirrors CenikCharakterizacniTest case for case, with the same prices and the same
 * expected outcomes. The point is not that this class works — it is that it produces
 * the numbers Cenik produces, since it is going to replace it.
 */
class DiscountCalculationTest extends TestCase
{
    private const RIGHT_PLACKA = 1002;
    private const RIGHT_KOSTKA = 1003;
    private const RIGHT_JIDLO_SLEVA = 1004;
    private const RIGHT_JIDLO_ZDARMA = 1005;
    private const RIGHT_UBYTOVANI = 1008;
    private const RIGHT_TRICKO_BONUS = 1012;
    private const RIGHT_NOC_STREDA = 1015;
    private const RIGHT_DVE_TRICKA = 1020;
    private const RIGHT_JEDNO_TRICKO = 1035;

    /**
     * @param array<string, mixed> $parameters
     */
    private function rule(string $code, int $right, array $parameters): DiscountRule
    {
        return new DiscountRule($code, $code, $right, DiscountParameters::fromArray($parameters));
    }

    /**
     * The rules as seeded, so the tests exercise what production will run.
     *
     * @return DiscountRule[]
     */
    private function seededRules(): array
    {
        return [
            $this->rule('kostka_zdarma', self::RIGHT_KOSTKA, [
                'scope'        => 'code_contains',
                'effect'       => 'free',
                'codeFragment' => 'kostka',
                'maxQuantity'  => 1,
            ]),
            $this->rule('placka_zdarma', self::RIGHT_PLACKA, [
                'scope'        => 'code_contains',
                'effect'       => 'free',
                'codeFragment' => 'placka',
                'maxQuantity'  => 1,
            ]),
            $this->rule('tricko_za_bonus', self::RIGHT_TRICKO_BONUS, [
                'scope'            => 'tag_cheapest',
                'effect'           => 'free',
                'tag'              => 'tricko',
                'maxQuantity'      => 1,
                'thresholdSetting' => 'freeShirtBonusThreshold',
            ]),
            $this->rule('jedno_tricko_zdarma', self::RIGHT_JEDNO_TRICKO, [
                'scope'       => 'tag',
                'effect'      => 'free',
                'tag'         => 'tricko',
                'maxQuantity' => 1,
            ]),
            $this->rule('dve_tricka_zdarma', self::RIGHT_DVE_TRICKA, [
                'scope'       => 'tag',
                'effect'      => 'free',
                'tag'         => 'tricko',
                'maxQuantity' => 2,
            ]),
            $this->rule('ubytovani_zdarma', self::RIGHT_UBYTOVANI, [
                'scope'  => 'tag',
                'effect' => 'free',
                'tag'    => 'ubytovani',
            ]),
            $this->rule('jidlo_zdarma', self::RIGHT_JIDLO_ZDARMA, [
                'scope'  => 'tag',
                'effect' => 'free',
                'tag'    => 'jidlo',
            ]),
            $this->rule('jidlo_se_slevou', self::RIGHT_JIDLO_SLEVA, [
                'scope'         => 'tag',
                'effect'        => 'fixed_amount',
                'tag'           => 'jidlo',
                'amountSetting' => 'organizerMealDiscount',
            ]),
            $this->rule('ubytovani_stredecni_noc_zdarma', self::RIGHT_NOC_STREDA, [
                'scope'  => 'tag_and_day',
                'effect' => 'free',
                'tag'    => 'ubytovani',
                'day'    => 0,
            ]),
        ];
    }

    /**
     * @param int[] $rights
     */
    private function calculation(array $rights, float $earnedBonus = 0.0): DiscountCalculation
    {
        return new DiscountCalculation(
            $this->seededRules(),
            $rights,
            [
                'organizerMealDiscount'   => 25.0,
                'freeShirtBonusThreshold' => 1320.0,
            ],
            $earnedBonus,
        );
    }

    private function kostka(int|string $key = 'kostka'): DiscountableItem
    {
        return new DiscountableItem($key, 'kostka_test_2026', 100.0, [ProductTagCode::PREDMET]);
    }

    private function placka(): DiscountableItem
    {
        return new DiscountableItem('placka', 'placka_test_2026', 50.0, [ProductTagCode::PREDMET]);
    }

    private function tricko(int|string $key, float $price): DiscountableItem
    {
        return new DiscountableItem($key, 'tricko_test_2026', $price, [ProductTagCode::TRICKO]);
    }

    private function ubytovani(int|string $key, int $day): DiscountableItem
    {
        return new DiscountableItem($key, 'ubytovani_test_2026', 400.0, [ProductTagCode::UBYTOVANI], $day);
    }

    private function jidlo(): DiscountableItem
    {
        return new DiscountableItem('jidlo', 'obed_test_2026', 150.0, [ProductTagCode::JIDLO], 1);
    }

    public function testWithoutRightsNothingIsDiscounted(): void
    {
        $applied = $this->calculation([])->apply([
            $this->kostka(), $this->placka(), $this->tricko('t', 300.0), $this->jidlo(),
        ]);

        $this->assertSame([], $applied);
    }

    public function testFreeDiceAppliesOnlyToTheFirstDice(): void
    {
        $applied = $this->calculation([self::RIGHT_KOSTKA])->apply([
            $this->kostka('first'), $this->kostka('second'), $this->placka(),
        ]);

        $this->assertSame(0.0, $applied['first']->finalPrice);
        $this->assertArrayNotHasKey('second', $applied, 'Druhá kostka už za plnou cenu');
        $this->assertArrayNotHasKey('placka', $applied, 'Placka má vlastní právo');
    }

    public function testFreeBadgeDoesNotConsumeTheDiceEntitlement(): void
    {
        $applied = $this->calculation([self::RIGHT_PLACKA])->apply([
            $this->kostka(), $this->placka(),
        ]);

        $this->assertSame(0.0, $applied['placka']->finalPrice);
        $this->assertArrayNotHasKey('kostka', $applied);
    }

    public function testOneFreeShirtTakesTheCheapest(): void
    {
        // Cenik prices from the cheapest up, so a single entitlement lands on the
        // cheapest shirt regardless of the order they were added.
        $applied = $this->calculation([self::RIGHT_JEDNO_TRICKO])->apply([
            $this->tricko('drahe', 500.0), $this->tricko('levne', 300.0),
        ]);

        $this->assertSame(0.0, $applied['levne']->finalPrice);
        $this->assertArrayNotHasKey('drahe', $applied);
    }

    public function testTwoFreeShirtsCoverTwoOfThree(): void
    {
        $applied = $this->calculation([self::RIGHT_DVE_TRICKA])->apply([
            $this->tricko('a', 300.0), $this->tricko('b', 400.0), $this->tricko('c', 500.0),
        ]);

        $this->assertSame(0.0, $applied['a']->finalPrice);
        $this->assertSame(0.0, $applied['b']->finalPrice);
        $this->assertArrayNotHasKey('c', $applied);
    }

    public function testBonusShirtNeedsTheThreshold(): void
    {
        $belowThreshold = $this->calculation([self::RIGHT_TRICKO_BONUS], 1000.0)->apply([
            $this->tricko('t', 300.0),
        ]);
        $this->assertSame([], $belowThreshold, 'Pod prahem bonusu se sleva neuplatní');

        $atThreshold = $this->calculation([self::RIGHT_TRICKO_BONUS], 1320.0)->apply([
            $this->tricko('t', 300.0),
        ]);
        $this->assertSame(0.0, $atThreshold['t']->finalPrice);
    }

    public function testFreeAccommodationCoversEveryNight(): void
    {
        $applied = $this->calculation([self::RIGHT_UBYTOVANI])->apply([
            $this->ubytovani('st', 0), $this->ubytovani('ct', 1),
        ]);

        $this->assertSame(0.0, $applied['st']->finalPrice);
        $this->assertSame(0.0, $applied['ct']->finalPrice);
    }

    public function testFreeNightAppliesToThatNightOnlyAndIsNotConsumed(): void
    {
        $applied = $this->calculation([self::RIGHT_NOC_STREDA])->apply([
            $this->ubytovani('st1', 0), $this->ubytovani('st2', 0), $this->ubytovani('ct', 1),
        ]);

        // Not consumed: unlike a dice, the entitlement covers every Wednesday night.
        $this->assertSame(0.0, $applied['st1']->finalPrice);
        $this->assertSame(0.0, $applied['st2']->finalPrice);
        $this->assertArrayNotHasKey('ct', $applied, 'Čtvrteční noc se platí');
    }

    public function testMealDiscountSubtractsAFixedAmount(): void
    {
        $applied = $this->calculation([self::RIGHT_JIDLO_SLEVA])->apply([$this->jidlo()]);

        // The only rule that is not 100 % off.
        $this->assertSame(125.0, $applied['jidlo']->finalPrice);
        $this->assertSame(25.0, $applied['jidlo']->discountAmount);
    }

    public function testMealDiscountNeverGoesBelowZero(): void
    {
        $cheapMeal = new DiscountableItem('m', 'obed_test_2026', 20.0, [ProductTagCode::JIDLO], 1);
        $applied = $this->calculation([self::RIGHT_JIDLO_SLEVA])->apply([$cheapMeal]);

        $this->assertSame(0.0, $applied['m']->finalPrice);
        $this->assertSame(20.0, $applied['m']->discountAmount, 'Sleva se ořízne na cenu položky');
    }

    public function testRightsForOneKindDoNotDiscountAnother(): void
    {
        $applied = $this->calculation([self::RIGHT_JIDLO_ZDARMA, self::RIGHT_UBYTOVANI])->apply([
            $this->kostka(), $this->tricko('t', 300.0),
        ]);

        $this->assertSame([], $applied);
    }

    public function testSnapshotRecordsTheRuleAsApplied(): void
    {
        $applied = $this->calculation([self::RIGHT_TRICKO_BONUS], 1500.0)->apply([
            $this->tricko('t', 300.0),
        ]);

        $snapshot = $applied['t']->snapshot;
        $this->assertSame('tricko_za_bonus', $snapshot['ruleCode']);
        $this->assertSame(self::RIGHT_TRICKO_BONUS, $snapshot['requiredRight']);
        $this->assertSame(300.0, $snapshot['originalPrice']);
        $this->assertSame(300.0, $snapshot['discountAmount']);

        // The threshold as it resolved at that moment, so changing the setting later
        // cannot rewrite what this purchase was entitled to.
        $this->assertSame(1320.0, $snapshot['resolved']['threshold']);
        $this->assertSame(1500.0, $snapshot['resolved']['earnedBonus']);
    }

    public function testMissingSettingIsRefusedRatherThanTreatedAsZero(): void
    {
        $calculation = new DiscountCalculation(
            $this->seededRules(),
            [self::RIGHT_JIDLO_SLEVA],
            [], // caller forgot to resolve the settings
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chybí hodnota nastavení "organizerMealDiscount"');

        $calculation->apply([$this->jidlo()]);
    }

    public function testOnlyOneRuleAppliesPerItem(): void
    {
        // Holding both shirt rights must not stack into a double discount on one shirt.
        $applied = $this->calculation([self::RIGHT_JEDNO_TRICKO, self::RIGHT_DVE_TRICKA])->apply([
            $this->tricko('t', 300.0),
        ]);

        $this->assertCount(1, $applied);
        $this->assertSame(300.0, $applied['t']->discountAmount);
    }
}
