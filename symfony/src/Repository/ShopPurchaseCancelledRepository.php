<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShopPurchaseCancelled;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopPurchaseCancelled>
 *
 * @method ShopPurchaseCancelled|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShopPurchaseCancelled|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ShopPurchaseCancelled[]    findAll()
 * @method ShopPurchaseCancelled[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ShopPurchaseCancelledRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopPurchaseCancelled::class);
    }

    public function save(ShopPurchaseCancelled $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ShopPurchaseCancelled $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
