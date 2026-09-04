<?php

declare(strict_types=1);

namespace App\Tests\Discount;

use App\Discount\DiscountEffect;
use App\Discount\DiscountParameters;
use App\Discount\DiscountScope;
use PHPUnit\Framework\TestCase;

class DiscountParametersTest extends TestCase
{
    public function testFreeDiceRule(): void
    {
        $parameters = DiscountParameters::fromArray([
            'scope'        => 'code_contains',
            'effect'       => 'free',
            'codeFragment' => 'kostka',
            'maxQuantity'  => 1,
        ]);

        $this->assertSame(DiscountScope::CODE_CONTAINS, $parameters->scope);
        $this->assertSame(DiscountEffect::FREE, $parameters->effect);
        $this->assertSame('kostka', $parameters->codeFragment);
        $this->assertSame(1, $parameters->maxQuantity);
    }

    public function testMealDiscountKeepsItsAmount(): void
    {
        $parameters = DiscountParameters::fromArray([
            'scope'  => 'tag',
            'effect' => 'fixed_amount',
            'tag'    => 'jidlo',
            'amount' => 30,
        ]);

        $this->assertSame(30.0, $parameters->amount);
        $this->assertNull($parameters->maxQuantity, 'Sleva na jídlo platí na každé jídlo');
    }

    public function testAccommodationNightCarriesTheDay(): void
    {
        $parameters = DiscountParameters::fromArray([
            'scope'  => 'tag_and_day',
            'effect' => 'free',
            'tag'    => 'ubytovani',
            'day'    => 0,
        ]);

        $this->assertSame(0, $parameters->day);
        $this->assertFalse(
            $parameters->scope->isConsumable(),
            'Nárok na noc se nevyčerpá — platí na každou takovou noc',
        );
    }

    public function testMissingRequiredParameterIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('vyžaduje parametr "amount"');

        DiscountParameters::fromArray([
            'scope'  => 'tag',
            'effect' => 'fixed_amount',
            'tag'    => 'jidlo',
        ]);
    }

    public function testUnknownParameterIsRejected(): void
    {
        // A typo would otherwise be ignored and the rule would behave as if the
        // value had never been set — the failure mode JSON storage invites.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Neznámé parametry slevy: maxQantity');

        DiscountParameters::fromArray([
            'scope'      => 'tag',
            'effect'     => 'free',
            'tag'        => 'tricko',
            'maxQantity' => 1,
        ]);
    }

    public function testDayOutsideTheFestivalIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Den ubytování musí být 0–4');

        DiscountParameters::fromArray([
            'scope'  => 'tag_and_day',
            'effect' => 'free',
            'tag'    => 'ubytovani',
            'day'    => 7,
        ]);
    }

    public function testRoundTripThroughArray(): void
    {
        $original = [
            'scope'            => 'tag_cheapest',
            'effect'           => 'free',
            'tag'              => 'tricko',
            'maxQuantity'      => 1,
            'thresholdSetting' => 'MODRE_TRICKO_ZDARMA_OD',
        ];

        $this->assertSame($original, DiscountParameters::fromArray($original)->toArray());
    }

    public function testFixedAmountAcceptsASettingInsteadOfANumber(): void
    {
        // The meal discount follows SLEVA_ORGU_NA_JIDLO_CASTKA rather than freezing
        // today's value, so an admin changing the setting changes the rule.
        $parameters = DiscountParameters::fromArray([
            'scope'         => 'tag',
            'effect'        => 'fixed_amount',
            'tag'           => 'jidlo',
            'amountSetting' => 'SLEVA_ORGU_NA_JIDLO_CASTKA',
        ]);

        $this->assertSame('SLEVA_ORGU_NA_JIDLO_CASTKA', $parameters->amountSetting);
        $this->assertNull($parameters->amount);
    }

    public function testFixedAmountNeedsEitherAnAmountOrASetting(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('vyžaduje parametr "amount" nebo "amountSetting"');

        DiscountParameters::fromArray([
            'scope'  => 'tag',
            'effect' => 'fixed_amount',
            'tag'    => 'jidlo',
        ]);
    }

    public function testEffectNeverDiscountsMoreThanThePrice(): void
    {
        // A 30 Kč discount on a 20 Kč item must not produce a negative price.
        $this->assertSame(20.0, DiscountEffect::FIXED_AMOUNT->discountFrom(20.0, 30.0));
        $this->assertSame(100.0, DiscountEffect::FREE->discountFrom(100.0, 0.0));
        $this->assertSame(25.0, DiscountEffect::PERCENT->discountFrom(100.0, 25.0));
    }
}
