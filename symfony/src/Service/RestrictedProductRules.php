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

    public function __construct(
        private readonly UserPermissions $userPermissions,
    ) {
    }

    /**
     * Whether this customer may order the product at all, as opposed to what it costs them.
     * Only restricted products can answer false; everything else is unrestricted.
     */
    public function mayOrder(Product $product, User $customer, int $year): bool
    {
        $permission = $this->permissionFor($product);

        return $permission === null || $this->userPermissions->has($customer, $permission, $year);
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
