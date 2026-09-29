<?php

declare(strict_types=1);

namespace App\Serializer;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Service\CapacityManager;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Adds the remaining stock to a variant in the product admin. It is counted, not stored,
 * so it cannot be an entity property; other serializations of a variant skip the query.
 */
final class ProductVariantRemainingNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'product_variant_remaining_normalizer_already_called';

    public function __construct(
        private readonly CapacityManager $capacityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|string|int|float|bool|\ArrayObject<string, mixed>|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED] = true;
        $normalized = $this->normalizer->normalize($data, $format, $context);

        if (is_array($normalized)) {
            $normalized['remaining'] = $this->capacityManager->remaining($data);
        }

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (! $data instanceof ProductVariant || isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        $groups = (array) ($context['groups'] ?? []);

        return in_array(Product::READ, $groups, true) || in_array(ProductVariant::READ, $groups, true);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            ProductVariant::class => false,
        ];
    }
}
