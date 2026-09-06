<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityRegistrationSpec;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityRegistrationSpec>
 *
 * @method ActivityRegistrationSpec|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivityRegistrationSpec|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method ActivityRegistrationSpec[]    findAll()
 * @method ActivityRegistrationSpec[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class ActivityRegistrationSpecRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityRegistrationSpec::class);
    }
}
