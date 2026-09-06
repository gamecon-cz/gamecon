<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityTag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityTag>
 *
 * @method ActivityTag|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivityTag|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ActivityTag[]    findAll()
 * @method ActivityTag[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ActivityTagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityTag::class);
    }
}
