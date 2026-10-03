<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Service\SoldCatalog;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Tells the product admin which products and variants were ever sold, so it can disable deleting
 * them before the server refuses it.
 */
final class SoldNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    // One flag per class: a product's context reaches its nested variants, which need their own pass.
    private const ALREADY_CALLED_PRODUCT = 'sold_normalizer_product_already_called';
    private const ALREADY_CALLED_VARIANT = 'sold_normalizer_variant_already_called';

    public function __construct(
        private readonly SoldCatalog $soldCatalog,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[$data instanceof Product ? self::ALREADY_CALLED_PRODUCT : self::ALREADY_CALLED_VARIANT] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);

        if (is_array($normalized)) {
            $normalized['sold'] = $data instanceof Product
                ? $this->soldCatalog->isProductSold($data)
                : $this->soldCatalog->isVariantSold($data);
        }

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        $alreadyCalled = match (true) {
            $data instanceof Product        => isset($context[self::ALREADY_CALLED_PRODUCT]),
            $data instanceof ProductVariant => isset($context[self::ALREADY_CALLED_VARIANT]),
            default                         => true,
        };
        if ($alreadyCalled) {
            return false;
        }

        $groups = (array) ($context['groups'] ?? []);

        return in_array(Product::READ, $groups, true) || in_array(ProductVariant::READ, $groups, true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Product::class        => false,
            ProductVariant::class => false,
        ];
    }
}
