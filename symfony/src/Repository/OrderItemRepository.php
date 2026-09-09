<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 *
 * @method OrderItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method OrderItem|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method OrderItem[]    findAll()
 * @method OrderItem[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class OrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    /**
     * Find purchases by customer and year
     *
     * @return OrderItem[]
     */
    public function findByCustomerAndYear(User $customer, int $year): array
    {
        return $this->createQueryBuilder('oi')
            ->where('oi.customer = :customer')
            ->andWhere('oi.year = :year')
            ->setParameter('customer', $customer)
            ->setParameter('year', $year)
            ->orderBy('oi.purchasedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count how many times customer purchased product in year
     */
    public function countCustomerPurchases(User $customer, Product $product, int $year): int
    {
        return (int) $this->createQueryBuilder('oi')
            ->select('COUNT(oi.id)')
            ->where('oi.customer = :customer')
            ->andWhere('oi.product = :product')
            ->andWhere('oi.year = :year')
            ->setParameter('customer', $customer)
            ->setParameter('product', $product)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Counts both sale paths, since OrderItem maps to shop_nakupy. Pair it only with
     * {@see ProductRepository::producedQuantityByVariantCode()}, never with
     * remaining_quantity — CapacityManager already decrements that for new-cart sales.
     *
     * @param int[] $variantIds
     *
     * @return array<int, int>
     */
    public function countSoldByVariant(array $variantIds, int $year): array
    {
        if ($variantIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('oi')
            ->select('IDENTITY(oi.variant) AS variantId', 'COUNT(oi.id) AS sold')
            ->where('oi.variant IN (:variantIds)')
            ->andWhere('oi.year = :year')
            ->groupBy('oi.variant')
            ->setParameter('variantIds', $variantIds)
            ->setParameter('year', $year)
            ->getQuery()
            ->getArrayResult();

        $prodano = [];
        foreach ($rows as $row) {
            $prodano[(int) $row['variantId']] = (int) $row['sold'];
        }

        return $prodano;
    }

    /**
     * Added back into the remaining count, as legacy does, so the last bed still reads as
     * available once it is yours instead of sold out under its own ticked checkbox.
     *
     * @param int[] $variantIds
     *
     * @return array<int, int>
     */
    public function countHeldByCustomer(array $variantIds, User $customer, int $year): array
    {
        if ($variantIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('oi')
            ->select('IDENTITY(oi.variant) AS variantId', 'COUNT(oi.id) AS held')
            ->where('oi.variant IN (:variantIds)')
            ->andWhere('oi.year = :year')
            ->andWhere('oi.customer = :customer')
            ->groupBy('oi.variant')
            ->setParameter('variantIds', $variantIds)
            ->setParameter('year', $year)
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getArrayResult();

        $drzeno = [];
        foreach ($rows as $row) {
            $drzeno[(int) $row['variantId']] = (int) $row['held'];
        }

        return $drzeno;
    }
}
