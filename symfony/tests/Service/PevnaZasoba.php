<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\CapacityManager;

/**
 * Stock answered from a map instead of counted in the database, for unit tests that build
 * their products in memory. The reserve rules stay the real ones.
 */
final class PevnaZasoba extends CapacityManager
{
    /**
     * @param array<int, int|null> $zbyvaNaVariantu variant id => remaining; null = unlimited
     */
    public function __construct(
        private array $zbyvaNaVariantu = [],
    ) {
    }

    public function nastav(int $variantId, ?int $zbyva): void
    {
        $this->zbyvaNaVariantu[$variantId] = $zbyva;
    }

    public function remainingByVariantId(array $variantIds): array
    {
        $zbyva = [];
        foreach ($variantIds as $variantId) {
            $zbyva[$variantId] = $this->zbyvaNaVariantu[$variantId] ?? null;
        }

        return $zbyva;
    }
}
