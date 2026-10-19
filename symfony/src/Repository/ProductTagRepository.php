<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductTag>
 */
class ProductTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductTag::class);
    }

    /**
     * Find tag by name or return null
     */
    public function findByName(string $name): ?ProductTag
    {
        return $this->findOneBy([
            'name' => strtolower(trim($name)),
        ]);
    }

    /**
     * Find or create a tag by name
     */
    public function findOrCreate(string $name, ?string $description = null): ProductTag
    {
        $tag = $this->findByName($name);

        if ($tag === null) {
            $tag = new ProductTag();
            $tag->setCode($name);
            if ($description !== null) {
                $tag->setDescription($description);
            }
            $this->getEntityManager()->persist($tag);
        }

        return $tag;
    }

    /**
     * Replace all tags for a product
     *
     * @param string[] $tagNames
     */
    public function replaceProductTags(\App\Entity\Product $product, array $tagNames): void
    {
        // Remove all existing tags
        foreach ($product->getTags()->toArray() as $existingTag) {
            $product->removeTag($existingTag);
        }

        // Add new tags
        foreach ($tagNames as $tagName) {
            $product->addTag($this->findOrCreate($tagName));
        }

        $this->getEntityManager()->flush();
    }
}
