<?php

declare(strict_types=1);

namespace App\Dto\Kfc;

class KfcProductOutputDto
{
    /**
     * `remaining` je zásoba, když má produkt jedinou variantu; u víc variant je NULL
     * a počty drží jednotlivé varianty, protože každá má vlastní sklad.
     *
     * @param KfcProductVariantOutputDto[] $variants
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly int $price,
        public readonly ?int $remaining,
        public readonly array $variants = [],
        public readonly bool $archived = false,
    ) {
    }
}
