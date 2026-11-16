<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CategoryTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryTag>
 *
 * @method CategoryTag|null find($id, $lockMode = null, $lockVersion = null)
 * @method CategoryTag|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method CategoryTag[]    findAll()
 * @method CategoryTag[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class CategoryTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryTag::class);
    }
}
