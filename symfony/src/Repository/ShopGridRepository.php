<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShopGrid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopGrid>
 *
 * @method ShopGrid|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShopGrid|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ShopGrid[]    findAll()
 * @method ShopGrid[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ShopGridRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopGrid::class);
    }
}
