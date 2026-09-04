<?php

declare(strict_types=1);

namespace App\Tests\Discount;

use App\Discount\DiscountEffect;
use App\Discount\DiscountParameters;
use App\Discount\DiscountScope;
use App\Discount\DiscountSetting;
use App\Enum\ProductTagCode;
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

        $this->assertSame(ProductTagCode::JIDLO, $parameters->tag);
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
            'thresholdSetting' => 'freeShirtBonusThreshold',
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
            'amountSetting' => 'organizerMealDiscount',
        ]);

        $this->assertSame(DiscountSetting::OrganizerMealDiscount, $parameters->amountSetting);
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

    public function testUnknownSettingNameIsRejected(): void
    {
        // A settings key written straight into the JSON is exactly what the enum
        // exists to prevent — it would resolve to nothing and the rule would quietly
        // stop discounting.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Neznámé nastavení "SLEVA_ORGU_NA_JIDLO_CASTKA"');

        DiscountParameters::fromArray([
            'scope'         => 'tag',
            'effect'        => 'fixed_amount',
            'tag'           => 'jidlo',
            'amountSetting' => 'SLEVA_ORGU_NA_JIDLO_CASTKA',
        ]);
    }

    public function testUnknownTagIsRejected(): void
    {
        // A typo matches no product, so the rule would validate, store, and then
        // silently never apply — free meals that quietly stop being free.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Neznámý tag "jidla"');

        DiscountParameters::fromArray([
            'scope'  => 'tag',
            'effect' => 'free',
            'tag'    => 'jidla',
        ]);
    }

    public function testTagSurvivesTheRoundTripAsItsCode(): void
    {
        // The JSON stores the tag code, not the case name, because that is what
        // product_tag.code holds and what any SQL against it has to match.
        $parameters = DiscountParameters::fromArray([
            'scope'  => 'tag',
            'effect' => 'free',
            'tag'    => 'ubytovani',
        ]);

        $this->assertSame(ProductTagCode::UBYTOVANI, $parameters->tag);
        $this->assertSame('ubytovani', $parameters->toArray()['tag']);
    }

    public function testEffectNeverDiscountsMoreThanThePrice(): void
    {
        // A 30 Kč discount on a 20 Kč item must not produce a negative price.
        $this->assertSame(20.0, DiscountEffect::FIXED_AMOUNT->discountFrom(20.0, 30.0));
        $this->assertSame(100.0, DiscountEffect::FREE->discountFrom(100.0, 0.0));
        $this->assertSame(25.0, DiscountEffect::PERCENT->discountFrom(100.0, 25.0));
    }
}
