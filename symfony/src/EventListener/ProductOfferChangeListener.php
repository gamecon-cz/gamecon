<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Product;
use App\Entity\ProductVariant;
use App\Service\VariantStateMirror;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Mirrors in postUpdate, inside the flush's transaction and after the row is written; the
 * variants already loaded are refreshed only in postFlush, as refreshing mid-flush is not allowed.
 */
#[AsEntityListener(event: Events::postUpdate, entity: Product::class)]
#[AsDoctrineListener(event: Events::postFlush)]
class ProductOfferChangeListener
{
    /**
     * @var array<int, string> product id => its code
     */
    private array $changed = [];

    public function __construct(
        private readonly VariantStateMirror $variantStateMirror,
    ) {
    }

    public function postUpdate(Product $product, PostUpdateEventArgs $args): void
    {
        $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($product);
        if (! isset($changeSet['state']) && ! isset($changeSet['archivedAt'])) {
            return;
        }

        $this->variantStateMirror->mirror([(int) $product->getId()]);
        $this->changed[(int) $product->getId()] = $product->getCode();
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->changed === []) {
            return;
        }
        $changed = $this->changed;
        $this->changed = [];

        $entityManager = $args->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();
        foreach ($unitOfWork->getIdentityMap()[ProductVariant::class] ?? [] as $variant) {
            /** @var ProductVariant $variant */
            if ($unitOfWork->isUninitializedObject($variant)) {
                continue;
            }
            if (isset($changed[(int) $variant->getProduct()->getId()]) || in_array($variant->getCode(), $changed, true)) {
                $entityManager->refresh($variant);
            }
        }
    }
}
