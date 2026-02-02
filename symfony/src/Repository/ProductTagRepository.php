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
     * Get all type tags (predmet, ubytovani, tricko, etc.)
     *
     * @return ProductTag[]
     */
    public function findTypeTags(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.name IN (:types)')
            ->setParameter('types', [
                'predmet',
                'ubytovani',
                'tricko',
                'jidlo',
                'vstupne',
                'parcon',
                'proplaceni-bonusu',
            ])
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get tags with product counts
     *
     * @return array<array{tag: ProductTag, productCount: int}>
     */
    public function findWithProductCounts(): array
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'COUNT(p.id) as productCount')
            ->leftJoin('t.products', 'p')
            ->groupBy('t.id')
            ->orderBy('productCount', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
