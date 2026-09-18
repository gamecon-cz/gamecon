<?php

declare(strict_types=1);

namespace App\Dto\Cart;

use App\Entity\Product;
use App\Entity\ProductVariant;

/**
 * Flat DTO for meal products — contains only what the meal matrix UI needs.
 * Decoupled from Product entity serialization groups.
 */
class MealProductOutputDto
{
    public string $name;
    public int $day;
    public string $price;
    public int $variantId;
    public ?int $remainingQuantity;

    /**
     * Po termínu `JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE` nejde jídlo objednat **ani zrušit** —
     * počty už jsou nahlášené v jídelně, takže zrušené jídlo by se stejně zaplatilo.
     * Proto se zamyká i to, co účastník má; u ubytování je to naopak.
     *
     * Pro pult zůstává odemčené: doobjednat po termínu je smysl admin obrazovek.
     */
    public bool $locked = false;

    /**
     * Cena podle pořadí kusu; vždy aspoň jeden stupeň. U jídla dnes žádné pravidlo
     * s omezeným počtem není, takže je stupeň jeden — ale tvar je stejný jako u merche.
     *
     * @var array<int, array{fromQuantity: int, price: string, discountAmount: string, ruleCode: string|null, ruleName: string|null, label: string|null}>
     */
    public array $priceSteps = [];

    public static function fromProductAndVariant(Product $product, ProductVariant $variant): self
    {
        $dto = new self();
        $dto->name = $product->getName();
        $dto->day = $variant->getAccommodationDay() ?? $product->getAccommodationDay() ?? 0;
        $dto->price = $variant->getEffectivePrice();
        $dto->variantId = $variant->getId();
        // Záporná zásoba se posílá tak, jak je. Znamená, že se na pultu prodalo víc, než
        // bylo na skladě, a obsluha to má vidět — je to podnět ke kontrole, ne chyba
        // zobrazení. `soldOut` v matici jede na `<= 0`, takže mínus se chová jako nula.
        $dto->remainingQuantity = $variant->getRemainingQuantity();

        return $dto;
    }
}
