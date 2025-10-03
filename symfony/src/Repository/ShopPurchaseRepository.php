<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShopPurchase;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopPurchase>
 *
 * @method ShopPurchase|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShopPurchase|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ShopPurchase[]    findAll()
 * @method ShopPurchase[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ShopPurchaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopPurchase::class);
    }

    public function save(ShopPurchase $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ShopPurchase $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
