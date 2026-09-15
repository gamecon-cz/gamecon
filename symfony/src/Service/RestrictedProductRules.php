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
    private const PERMISSION_BY_TAG = [
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
    public function mayOrder(Product $product, \Uzivatel $customer): bool
    {
        $permission = $this->permissionFor($product);

        return $permission === null || $customer->maPravo($permission);
    }

    /**
     * Loaded once per request: the permission set lives only on the legacy user.
     */
    public function legacyUserFor(User $customer): ?\Uzivatel
    {
        return \Uzivatel::zId((int) $customer->getId(), true);
    }

    /**
     * Omezený produkt vyžaduje právo; ostatní si smí koupit kdokoli.
     */
    public function isRestricted(Product $product): bool
    {
        return $this->permissionFor($product) !== null;
    }

    private function permissionFor(Product $product): ?int
    {
        foreach (self::PERMISSION_BY_TAG as $tagCode => $permission) {
            if ($product->hasTag($tagCode)) {
                return $permission;
            }
        }

        return null;
    }
}
