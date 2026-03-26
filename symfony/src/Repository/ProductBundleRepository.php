<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductBundle;
use App\Entity\ProductVariant;
use App\Enum\RoleMeaning;
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
     * Find bundles containing a specific variant
     *
     * @return ProductBundle[]
     */
    public function findByVariant(ProductVariant $variant): array
    {
        return $this->createQueryBuilder('pb')
            ->innerJoin('pb.variants', 'v')
            ->where('v = :variant')
            ->setParameter('variant', $variant)
            ->orderBy('pb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find forced bundles containing a specific variant
     *
     * @return ProductBundle[]
     */
    public function findForcedByVariant(ProductVariant $variant): array
    {
        return $this->createQueryBuilder('pb')
            ->innerJoin('pb.variants', 'v')
            ->where('v = :variant')
            ->andWhere('pb.forced = :forced')
            ->setParameter('variant', $variant)
            ->setParameter('forced', true)
            ->orderBy('pb.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if variant is part of a forced bundle for given role
     */
    public function isVariantInForcedBundleForRole(ProductVariant $variant, string $role): bool
    {
        $count = $this->createQueryBuilder('pb')
            ->select('COUNT(pb.id)')
            ->innerJoin('pb.variants', 'v')
            ->where('v = :variant')
            ->andWhere('pb.forced = :forced')
            ->andWhere('JSON_CONTAINS(pb.applicableToRoles, :role) = 1')
            ->setParameter('variant', $variant)
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

    /**
     * Find the forced bundle a variant belongs to for the given roles.
     * Returns null if the variant can be purchased individually.
     *
     * @param RoleMeaning[] $roleMeanings
     */
    public function findMandatoryBundleForVariant(ProductVariant $variant, array $roleMeanings): ?ProductBundle
    {
        if ($roleMeanings === []) {
            return null;
        }

        $qb = $this->createQueryBuilder('pb')
            ->innerJoin('pb.variants', 'v')
            ->where('v = :variant')
            ->andWhere('pb.forced = :forced')
            ->setParameter('variant', $variant)
            ->setParameter('forced', true);

        $orX = $qb->expr()->orX();
        foreach ($roleMeanings as $index => $meaning) {
            $orX->add(sprintf('JSON_CONTAINS(pb.applicableToRoles, :role%s) = 1', $index));
            $qb->setParameter('role' . $index, json_encode($meaning->value));
        }

        $qb->andWhere($orX);
        $qb->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
