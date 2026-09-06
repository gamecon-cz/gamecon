<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserUrl;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserUrl>
 *
 * @method UserUrl|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserUrl|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method UserUrl[]    findAll()
 * @method UserUrl[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class UserUrlRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserUrl::class);
    }
}
