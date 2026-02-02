<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductBundle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductBundle>
 *
 * @method ProductBundle|null find($id, $lockMode = null, $lockVersion = null)
 * @method ProductBundle|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ProductBundle[]    findAll()
 * @method ProductBundle[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ProductBundleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductBundle::class);
    }

    public function save(ProductBundle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ProductBundle $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all forced bundles
     *
     * @return ProductBundle[]
     */
    public function findForced(): array
    {
        return $this->createQueryBuilder('pb')
            ->where('pb.forced = :forced')
            ->setParameter('forced', true)
            ->orderBy('pb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bundles applicable to a specific role
     *
     * @return ProductBundle[]
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('pb')
            ->where('JSON_CONTAINS(pb.applicableToRoles, :role) = 1')
            ->setParameter('role', json_encode($role))
            ->orderBy('pb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find forced bundles applicable to a specific role
     *
     * @return ProductBundle[]
     */
    public function findForcedByRole(string $role): array
    {
        return $this->createQueryBuilder('pb')
            ->where('pb.forced = :forced')
            ->andWhere('JSON_CONTAINS(pb.applicableToRoles, :role) = 1')
            ->setParameter('forced', true)
            ->setParameter('role', json_encode($role))
            ->orderBy('pb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find bundles containing a specific product
     *
     * @return ProductBundle[]
     */
    public function findByProduct(Product $product): array
    {
        return $this->createQueryBuilder('pb')
            ->innerJoin('pb.products', 'p')
            ->where('p = :product')
            ->setParameter('product', $product)
            ->orderBy('pb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find forced bundles containing a specific product
     *
     * @return ProductBundle[]
     */
    public function findForcedByProduct(Product $product): array
    {
        return $this->createQueryBuilder('pb')
            ->innerJoin('pb.products', 'p')
            ->where('p = :product')
            ->andWhere('pb.forced = :forced')
            ->setParameter('product', $product)
            ->setParameter('forced', true)
            ->orderBy('pb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if product is part of a forced bundle for given role
     */
    public function isProductInForcedBundleForRole(Product $product, string $role): bool
    {
        $count = $this->createQueryBuilder('pb')
            ->select('COUNT(pb.id)')
            ->innerJoin('pb.products', 'p')
            ->where('p = :product')
            ->andWhere('pb.forced = :forced')
            ->andWhere('JSON_CONTAINS(pb.applicableToRoles, :role) = 1')
            ->setParameter('product', $product)
            ->setParameter('forced', true)
            ->setParameter('role', json_encode($role))
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Get all forced bundles that user with given roles must comply with
     *
     * @param string[] $userRoles
     *
     * @return ProductBundle[]
     */
    public function findMandatoryBundlesForUser(array $userRoles): array
    {
        if ($userRoles === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('pb')
            ->where('pb.forced = :forced')
            ->setParameter('forced', true);

        // Build OR condition for roles
        $orX = $qb->expr()->orX();
        foreach ($userRoles as $index => $role) {
            $orX->add(sprintf('JSON_CONTAINS(pb.applicableToRoles, :role%s) = 1', $index));
            $qb->setParameter('role' . $index, json_encode($role));
        }

        $qb->andWhere($orX);
        $qb->orderBy('pb.name', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
