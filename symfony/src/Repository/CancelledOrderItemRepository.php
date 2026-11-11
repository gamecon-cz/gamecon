<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CancelledOrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CancelledOrderItem>
 */
class CancelledOrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CancelledOrderItem::class);
    }

    /**
     * @param int[] $variantIds
     *
     * @return int[]
     */
    public function cancelledVariantIds(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        return array_map('intval', $this->createQueryBuilder('cancelled_item')
            ->select('DISTINCT IDENTITY(cancelled_item.variant)')
            ->where('cancelled_item.variant IN (:variantIds)')
            ->setParameter('variantIds', $variantIds)
            ->getQuery()
            ->getSingleColumnResult());
    }

    /**
     * @return array<int, string> code of every variant ever bought this way, by its id
     */
    public function everCancelledVariantCodes(): array
    {
        $rows = $this->createQueryBuilder('cancelled_item')
            ->select('DISTINCT variant.id, variant.code')
            ->innerJoin('cancelled_item.variant', 'variant')
            ->getQuery()
            ->getArrayResult();

        return array_column($rows, 'code', 'id');
    }
}
