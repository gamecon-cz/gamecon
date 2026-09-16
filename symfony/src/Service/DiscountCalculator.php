<?php

declare(strict_types=1);

namespace App\Service;

use App\Discount\DiscountableItem;
use App\Discount\DiscountCalculation;
use App\Discount\DiscountRuleLoader;
use App\Discount\DiscountSetting;
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
    /**
     * @var array<string, int[]> práva kupujícího, klíčem "uživatel-ročník"
     */
    private array $prava = [];

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

        // Jedna položka na volání, protože takové je rozhraní téhle metody — stejné omezení
        // jako v legacy Ceniku. Pravidla s maxQuantity (tričko zdarma) proto v mřížce zlevní
        // každé tričko; kolik jich účastník reálně dostane zdarma, rozhodne až košík.
        $slevy = (new DiscountCalculation(
            $this->ruleLoader->rulesForYear($year),
            $this->pravaUzivatele($idUzivatele, $year),
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
     * @return int[]
     */
    private function pravaUzivatele(int $idUzivatele, int $rocnik): array
    {
        return $this->prava[$idUzivatele . '-' . $rocnik] ??= $this->ruleLoader->rightsOfUser($idUzivatele, $rocnik);
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
