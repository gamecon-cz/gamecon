<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Discount\DiscountableItem;
use App\Discount\DiscountCalculation;
use App\Discount\DiscountRuleLoader;
use App\Discount\DiscountSetting;
use App\Enum\ProductTagCode;
use App\Tests\AbstractDatabaseKernelTestCase;

/**
 * Brigádník má právo „jídlo se slevou" (1004), takže mu pravidlo `jidlo_se_slevou` musí
 * jídlo zlevnit. Legacy i nová vrstva počítají týmž `DiscountCalculation` nad týmiž
 * pravidly, takže se nesmí rozejít.
 *
 * Částku si test dodává sám — hlídá mechanismus (právo → pravidlo → odečet), ne hodnotu
 * `SLEVA_ORGU_NA_JIDLO_CASTKA`; tu čte `DiscountSettingValues` a pokrývá vlastní test.
 */
class DiscountCalculatorBrigadnikTest extends AbstractDatabaseKernelTestCase
{
    private const PRAVO_JIDLO_SE_SLEVOU = 1004;

    private function ruleLoader(): DiscountRuleLoader
    {
        return static::getContainer()->get(DiscountRuleLoader::class);
    }

    private function jidlo(float $cena): DiscountableItem
    {
        return new DiscountableItem(
            key: 'jidlo',
            productCode: 'snidane-ctvrtek',
            price: $cena,
            tags: [ProductTagCode::JIDLO],
        );
    }

    /**
     * @test
     */
    public function pravidloJidlaSeSlevouNemaOmezenyPocet(): void
    {
        $pravidla = $this->ruleLoader()->rulesForYear(ROCNIK);
        $jidloSeSlevou = null;
        foreach ($pravidla as $pravidlo) {
            if ($pravidlo->code === 'jidlo_se_slevou') {
                $jidloSeSlevou = $pravidlo;
                break;
            }
        }

        self::assertNotNull($jidloSeSlevou, 'Pravidlo jidlo_se_slevou musí v datech být');
        self::assertNull(
            $jidloSeSlevou->parameters->maxQuantity,
            'Kdyby mělo omezený počet, storefront by ho odfiltroval jako omezené pravidlo',
        );
        self::assertSame(self::PRAVO_JIDLO_SE_SLEVOU, $jidloSeSlevou->requiredRight);
    }

    /**
     * @test
     */
    public function brigadnikDostaneSlevuNaJidlo(): void
    {
        $slevy = (new DiscountCalculation(
            $this->ruleLoader()->rulesForYear(ROCNIK),
            [self::PRAVO_JIDLO_SE_SLEVOU],
            [
                DiscountSetting::OrganizerMealDiscount->value => 30.0,
            ],
        ))->apply([$this->jidlo(180.0)]);

        self::assertArrayHasKey('jidlo', $slevy, 'Právo 1004 musí slevu přiznat');
        self::assertSame('jidlo_se_slevou', $slevy['jidlo']->ruleCode);
        self::assertSame(150.0, $slevy['jidlo']->finalPrice);
    }

    /**
     * Kontrola opačným směrem: bez práva se sleva přiznat nesmí.
     *
     * @test
     */
    public function bezPravaZadnaSleva(): void
    {
        $pravidla = $this->ruleLoader()->rulesForYear(ROCNIK);
        // Prázdná sada pravidel by tenhle test nechala projít, aniž by cokoli tvrdila —
        // a to je přesně stav po přelomu ročníku, než se pravidla nasypou do nového.
        self::assertNotSame([], $pravidla, 'Bez pravidel netvrdí tenhle test nic');

        $slevy = (new DiscountCalculation(
            $pravidla,
            [],
            [
                DiscountSetting::OrganizerMealDiscount->value => 30.0,
            ],
        ))->apply([$this->jidlo(180.0)]);

        self::assertArrayNotHasKey('jidlo', $slevy);
    }
}
