<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShopGridCell;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShopGridCell>
 *
 * @method ShopGridCell|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShopGridCell|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ShopGridCell[]    findAll()
 * @method ShopGridCell[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ShopGridCellRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShopGridCell::class);
    }
}
