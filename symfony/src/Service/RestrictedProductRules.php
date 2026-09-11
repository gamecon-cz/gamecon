<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Enum\ProductTagCode;
use Gamecon\Pravo;

class RestrictedProductRules
{
    /**
     * Sub-tag => the legacy permission that allows ordering it.
     */
    private const PRAVO_PODLE_TAGU = [
        ProductTagCode::TRICKO_MODRE->value   => Pravo::MUZE_OBJEDNAVAT_MODRA_TRICKA,
        ProductTagCode::TRICKO_CERVENE->value => Pravo::MUZE_OBJEDNAVAT_CERVENA_TRICKA,
    ];

    /**
     * Whether this customer may order the product at all, as opposed to what it costs them.
     * Only restricted products can answer false; everything else is unrestricted.
     *
     * The legacy user is a parameter rather than looked up here, so a caller iterating an
     * order resolves it once instead of per item.
     */
    public function smiObjednat(Product $product, \Uzivatel $customer): bool
    {
        $pravo = $this->pravoProProdukt($product);

        return $pravo === null || $customer->maPravo($pravo);
    }

    /**
     * Loaded once per request: the permission set lives only on the legacy user.
     */
    public function dejLegacyUzivatele(User $customer): ?\Uzivatel
    {
        return \Uzivatel::zId((int) $customer->getId(), true);
    }

    private function pravoProProdukt(Product $product): ?int
    {
        foreach (self::PRAVO_PODLE_TAGU as $kodTagu => $pravo) {
            if ($product->hasTag($kodTagu)) {
                return $pravo;
            }
        }

        return null;
    }
}
