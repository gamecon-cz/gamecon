<?php

declare(strict_types=1);

namespace App\Service;

use App\Discount\DiscountableItem;
use App\Discount\DiscountCalculation;
use App\Discount\DiscountRuleLoader;
use App\Discount\DiscountSetting;
use App\Discount\PriceStep;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\ProductTagCode;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Cena jedné položky pro konkrétního kupujícího.
 *
 * Počítá se ze stejných pravidel (`discount_rule`) a stejným motorem jako v legacy Ceniku.
 * Dvě sady pravidel by znamenaly dvě různé ceny za totéž — jednu v e-shopu, druhou ve
 * financích.
 */
class DiscountCalculator
{
    public function __construct(
        private readonly DiscountRuleLoader $ruleLoader,
        // SystemoveNastaveni se nevstřikuje: je vázané na request (ročník, „teď") a zbytek
        // symfony/src ho bere přes zGlobals() z téhož důvodu.
        private readonly ?SystemoveNastaveni $systemoveNastaveni = null,
    ) {
    }

    /**
     * @return array{discount: null, discountAmount: string, finalPrice: string, reason: string|null}
     */
    public function calculateDiscount(Product $product, User $user, int $year): array
    {
        $puvodniCena = $product->getCurrentPrice();
        $idUzivatele = $user->getId();
        if ($idUzivatele === null) {
            return $this->bezSlevy($puvodniCena);
        }

        $polozka = $this->polozkaZProduktu($product);
        if ($polozka === null) {
            return $this->bezSlevy($puvodniCena);
        }

        $slevy = (new DiscountCalculation(
            $this->neomezenaPravidla($year),
            $this->ruleLoader->rightsOfUser($idUzivatele, $year),
            $this->hodnotyNastaveni(),
            // Bonus za vedení aktivit rozhoduje jen o prahu u trička zdarma. Storefront
            // ho zatím nemá po ruce, takže se pravidlo s prahem neuplatní; slev držených
            // právem se to netýká.
            0.0,
        ))->apply([$polozka]);

        $sleva = $slevy[$polozka->key] ?? null;
        if ($sleva === null) {
            return $this->bezSlevy($puvodniCena);
        }

        return [
            'discount'       => null,
            'discountAmount' => number_format($sleva->discountAmount, 2, '.', ''),
            'finalPrice'     => number_format($sleva->finalPrice, 2, '.', ''),
            'reason'         => $sleva->ruleName,
        ];
    }

    /**
     * Cenový žebřík: kolikátý kus produktu stojí kolik.
     *
     * Nároky s omezeným počtem (tričko zdarma) se v ceně jedné položky uplatnit nedají —
     * viz `neomezenaPravidla()`. Tady se naopak počítají všechna pravidla, protože žebřík
     * o pořadí kusů ví, a frontend díky němu ukáže cenu dalšího kusu bez dotazu na server.
     *
     * @param int $jizKoupeno kolik kusů zákazník letos má; jejich nároky jsou spotřebované
     *
     * @return array<int, array{fromQuantity: int, price: string, discountAmount: string, ruleCode: string|null, ruleName: string|null}>
     */
    public function priceSteps(Product $product, User $user, int $year, int $jizKoupeno = 0): array
    {
        $idUzivatele = $user->getId();
        $polozka = $this->polozkaZProduktu($product);
        if ($idUzivatele === null || $polozka === null) {
            return [(new PriceStep(1, (float) $product->getCurrentPrice(), 0.0, null, null))->toArray()];
        }

        $steps = (new DiscountCalculation(
            $this->ruleLoader->rulesForYear($year),
            $this->ruleLoader->rightsOfUser($idUzivatele, $year),
            $this->hodnotyNastaveni(),
            0.0,
        ))->priceSteps($polozka, $jizKoupeno);

        return array_map(static fn (PriceStep $step): array => $step->toArray(), $steps);
    }

    /**
     * @param Product[] $products
     *
     * @return array<int, array{discount: null, discountAmount: string, finalPrice: string, reason: string|null}>
     */
    public function calculateDiscountsForProducts(array $products, User $user, int $year): array
    {
        $results = [];
        foreach ($products as $product) {
            $results[$product->getId()] = $this->calculateDiscount($product, $user, $year);
        }

        return $results;
    }

    /**
     * Pravidla bez omezeného počtu.
     *
     * Metoda počítá cenu jedné položky, takže nemá jak vědět, kolik nároků už kupující
     * vyčerpal na ostatních. Pravidlo s maxQuantity by se proto uplatnilo na každou
     * položku znovu — tričko zdarma by bylo zdarma každé, a to i při zápisu do košíku,
     * protože CartService počítá cenu touž cestou. Radši nenabídnout slevu, na kterou
     * možná nárok je, než rozdat tu, na kterou není. Legacy Cenik si čítače drží sám.
     *
     * @return \App\Discount\DiscountRule[]
     */
    private function neomezenaPravidla(int $rocnik): array
    {
        return array_values(array_filter(
            $this->ruleLoader->rulesForYear($rocnik),
            static fn (\App\Discount\DiscountRule $pravidlo): bool => $pravidlo->parameters->maxQuantity === null,
        ));
    }

    /**
     * Vrací null pro položku, na kterou žádné pravidlo nemůže mířit — pravidla se
     * vztahují na tagy, takže produkt bez tagu nemá s čím porovnávat.
     */
    private function polozkaZProduktu(Product $product): ?DiscountableItem
    {
        $tagy = [];
        foreach ($product->getTagNames() as $kod) {
            $tag = ProductTagCode::tryFrom($kod);
            if ($tag !== null) {
                $tagy[] = $tag;
            }
        }

        if ($tagy === []) {
            return null;
        }

        return new DiscountableItem(
            key: $product->getId() ?? 0,
            productCode: $product->getCode(),
            price: (float) $product->getCurrentPrice(),
            tags: $tagy,
            accommodationDay: $product->getAccommodationDay(),
        );
    }

    /**
     * @return array<string, float>
     */
    private function hodnotyNastaveni(): array
    {
        $nastaveni = $this->systemoveNastaveni ?? SystemoveNastaveni::zGlobals();

        return [
            DiscountSetting::OrganizerMealDiscount->value   => (float) $nastaveni->slevaOrguNaJidloCastka(),
            DiscountSetting::FreeShirtBonusThreshold->value => (float) $nastaveni->modreTrickoZdarmaOd(),
        ];
    }

    /**
     * @return array{discount: null, discountAmount: string, finalPrice: string, reason: null}
     */
    private function bezSlevy(string $puvodniCena): array
    {
        return [
            'discount'       => null,
            'discountAmount' => '0.00',
            'finalPrice'     => $puvodniCena,
            'reason'         => null,
        ];
    }
}
